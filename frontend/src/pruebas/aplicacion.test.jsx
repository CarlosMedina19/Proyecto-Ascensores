import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, renderHook, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import { useInactividad } from '../hooks/useInactividad';
import IniciarSesion from '../paginas/autenticacion/IniciarSesion';
import Estructura from '../componentes/Estructura';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

function montar(elemento, ruta = '/') {
  return render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>{elemento}</ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

describe('inicio de sesión', () => {
  beforeEach(() => {
    guardarToken(null);
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => vi.unstubAllGlobals());

  it('muestra el mensaje de la API cuando las credenciales son incorrectas', async () => {
    fetch.mockReturnValueOnce(respuestaJson({ mensaje: 'Credenciales incorrectas.' }, 401));
    montar(<IniciarSesion />, '/iniciar-sesion');

    await userEvent.type(screen.getByLabelText(/correo electrónico/i), 'admin@sigma.test');
    await userEvent.type(screen.getByLabelText(/^contraseña/i), 'incorrecta');
    await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }));

    expect(await screen.findByText('Credenciales incorrectas.')).toBeInTheDocument();
    const [url, opciones] = fetch.mock.calls[0];
    expect(url).toMatch(/\/api\/v1\/iniciar-sesion$/);
    expect(JSON.parse(opciones.body)).toMatchObject({ correo: 'admin@sigma.test', password: 'incorrecta' });
  });

  it('muestra el aviso de bloqueo por intentos fallidos', async () => {
    fetch.mockReturnValueOnce(
      respuestaJson({ mensaje: 'La cuenta está bloqueada por intentos fallidos. Intente de nuevo en 15 minuto(s).' }, 423),
    );
    montar(<IniciarSesion />, '/iniciar-sesion');

    await userEvent.type(screen.getByLabelText(/correo electrónico/i), 'admin@sigma.test');
    await userEvent.type(screen.getByLabelText(/^contraseña/i), 'clave1234');
    await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }));

    expect(await screen.findByText(/bloqueada por intentos fallidos/)).toBeInTheDocument();
  });
});

describe('menú según permisos del rol', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('el cliente solo ve sus módulos de consulta', async () => {
    guardarToken('token-de-prueba');
    vi.stubGlobal(
      'fetch',
      vi.fn(() =>
        respuestaJson({
          datos: {
            id: 7,
            nombre: 'Cliente Demo',
            rol: { nombre: 'cliente', permisos: ['clientes.ver', 'edificios.ver', 'equipos.ver', 'equipos.historial.ver'] },
            cliente: { id: 1, nombre: 'Conjunto Mirador' },
          },
        }),
      ),
    );

    montar(
      <Routes>
        <Route element={<Estructura />}>
          <Route path="/inicio" element={<div>contenido</div>} />
        </Route>
      </Routes>,
      '/inicio',
    );

    // Edificios y ascensores ya no son entradas fijas: se despliegan desde "Mi empresa" por tipo.
    expect(await screen.findByRole('link', { name: /mi empresa/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /mis ascensores/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /mis edificios/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Conjuntos' })).not.toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: /desplegar mi empresa/i }));
    expect(screen.getByRole('button', { name: 'Conjuntos' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Institucionales' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Residenciales' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /usuarios/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /auditoría/i })).not.toBeInTheDocument();
    expect(screen.queryByText('Administración')).not.toBeInTheDocument();
    guardarToken(null);
  });
});

describe('inactividad de la sesión (RF-001)', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('avisa un minuto antes y cierra la sesión al cumplirse el tiempo', () => {
    const alExpirar = vi.fn();
    const { result } = renderHook(() => useInactividad({ minutos: 10, alExpirar, alPing: vi.fn() }));

    act(() => vi.advanceTimersByTime(8 * 60 * 1000));
    expect(result.current.segundosRestantes).toBeNull();

    act(() => vi.advanceTimersByTime(61 * 1000));
    expect(result.current.segundosRestantes).toBeGreaterThan(0);
    expect(result.current.segundosRestantes).toBeLessThanOrEqual(60);

    act(() => vi.advanceTimersByTime(60 * 1000));
    expect(alExpirar).toHaveBeenCalledTimes(1);
  });

  it('mantener la sesión reinicia el contador y avisa al servidor', () => {
    const alPing = vi.fn();
    const alExpirar = vi.fn();
    const { result } = renderHook(() => useInactividad({ minutos: 10, alExpirar, alPing }));

    act(() => vi.advanceTimersByTime(9.5 * 60 * 1000));
    expect(result.current.segundosRestantes).not.toBeNull();

    act(() => result.current.mantenerSesion());
    expect(result.current.segundosRestantes).toBeNull();
    expect(alPing).toHaveBeenCalled();

    act(() => vi.advanceTimersByTime(5 * 60 * 1000));
    expect(alExpirar).not.toHaveBeenCalled();
  });
});

describe('cliente HTTP', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('emite el evento de sesión expirada cuando la API responde 401', async () => {
    const { solicitar, obtenerToken } = await import('../api/cliente');
    guardarToken('vencido');
    vi.stubGlobal('fetch', vi.fn(() => respuestaJson({ mensaje: 'Sesión no válida o expirada.' }, 401)));
    const escucha = vi.fn();
    window.addEventListener('sigma:sesion-expirada', escucha);

    await expect(solicitar('/perfil')).rejects.toMatchObject({ estado: 401 });
    await waitFor(() => expect(escucha).toHaveBeenCalled());
    expect(obtenerToken()).toBeNull();
    window.removeEventListener('sigma:sesion-expirada', escucha);
  });
});
