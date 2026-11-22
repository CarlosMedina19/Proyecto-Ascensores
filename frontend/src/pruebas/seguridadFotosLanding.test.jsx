import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import IniciarSesion from '../paginas/autenticacion/IniciarSesion';
import RecuperarContrasena from '../paginas/autenticacion/RecuperarContrasena';
import ChatAsistente from '../componentes/ChatAsistente';
import Landing from '../paginas/publico/Landing';

/** Simula la API y guarda cada llamada (el cuerpo puede ser JSON o FormData). */
function simularApi(rutas) {
  const llamadas = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const ruta = new URL(url).pathname.replace('/api/v1', '');
      const metodo = opciones.method || 'GET';
      const cuerpo = opciones.body instanceof FormData ? opciones.body : opciones.body ? JSON.parse(opciones.body) : null;
      llamadas.push({ ruta, metodo, cuerpo });
      const definida = rutas[`${metodo} ${ruta}`] ?? rutas[ruta] ?? { datos: [] };
      const r = typeof definida === 'function' ? definida(cuerpo) : definida;
      const estado = r.__estado ?? 200;
      return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(r) });
    }),
  );
  return llamadas;
}

function montar(elemento, ruta = '/') {
  render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <Routes>
            <Route path="/inicio" element={<p>Panel de inicio</p>} />
            <Route path="/iniciar-sesion" element={<p>Pantalla de ingreso</p>} />
            <Route path="*" element={elemento} />
          </Routes>
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

const usuario = { id: 1, nombre: 'Admin SIGMA', correo: 'sigma3172409@gmail.com', doble_factor: true, rol: { nombre: 'administrador', permisos: [] } };

beforeEach(() => {
  vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:foto'), revokeObjectURL: vi.fn() }));
});

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('verificación en dos pasos', () => {
  it('después de la contraseña pide el código del correo y entra con él', async () => {
    const llamadas = simularApi({
      'POST /iniciar-sesion': { requiere_verificacion: true, desafio: 'desafio-1', canal: 'correo', destino: 'si***09@gmail.com', minutos: 10, mensaje: 'Enviamos un código de 6 dígitos a si***09@gmail.com.' },
      'POST /iniciar-sesion/verificar': (cuerpo) =>
        cuerpo.codigo === '123456'
          ? { usuario, token_acceso: 'token-2fa', minutos_inactividad: 10 }
          : { __estado: 422, mensaje: 'Datos inválidos.', errores: { codigo: ['El código no es correcto. Le quedan 4 intento(s).'] } },
    });
    montar(<IniciarSesion />, '/iniciar-sesion-2fa');

    await userEvent.type(screen.getByLabelText(/Correo electrónico/), 'sigma3172409@gmail.com');
    await userEvent.type(screen.getByLabelText(/^Contraseña/), 'AdminSigma123');
    await userEvent.click(screen.getByRole('button', { name: /Ingresar/ }));

    expect(await screen.findByRole('heading', { name: 'Verificación en dos pasos' })).toBeInTheDocument();
    expect(screen.getAllByText(/si\*\*\*09@gmail\.com/).length).toBeGreaterThan(0);

    const campo = screen.getByLabelText(/Código de verificación/);
    await userEvent.type(campo, '000000');
    await userEvent.click(screen.getByRole('button', { name: /Verificar e ingresar/ }));
    expect(await screen.findByText('El código no es correcto. Le quedan 4 intento(s).')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText(/Código de verificación/), '12a3456');
    expect(screen.getByLabelText(/Código de verificación/)).toHaveValue('123456');
    await userEvent.click(screen.getByRole('button', { name: /Verificar e ingresar/ }));
    expect(await screen.findByText('Panel de inicio')).toBeInTheDocument();
    expect(llamadas.filter((l) => l.ruta === '/iniciar-sesion/verificar').at(-1).cuerpo).toMatchObject({ desafio: 'desafio-1', codigo: '123456' });
  });
});

describe('recuperar la contraseña con el celular', () => {
  it('envía el código por SMS (simulado en la demostración) y restablece la contraseña', async () => {
    const llamadas = simularApi({
      'POST /recuperar-contrasena/telefono': { desafio: 'desafio-sms', canal: 'sms', destino: '300 *** **67', minutos: 10, codigo_demo: '654321', mensaje: 'Si el número 300 *** **67 está registrado, recibirá un SMS con un código de 6 dígitos.' },
      'POST /restablecer-contrasena/telefono': { mensaje: 'Contraseña restablecida. Ya puede iniciar sesión.' },
    });
    montar(<RecuperarContrasena />, '/recuperar-contrasena');

    await userEvent.click(screen.getByRole('tab', { name: /Por celular/ }));
    await userEvent.type(screen.getByLabelText(/Número de celular/), '300 123 4567');
    await userEvent.click(screen.getByRole('button', { name: /Enviar código por SMS/ }));

    expect(await screen.findByText('654321')).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText(/Código del SMS/), '654321');
    await userEvent.type(screen.getByLabelText(/^Nueva contraseña/), 'NuevaClave2026');
    await userEvent.type(screen.getByLabelText(/Confirmar nueva contraseña/), 'NuevaClave2026');
    await userEvent.click(screen.getByRole('button', { name: /Restablecer contraseña/ }));

    expect(await screen.findByText('Pantalla de ingreso')).toBeInTheDocument();
    expect(llamadas.find((l) => l.ruta === '/recuperar-contrasena/telefono').cuerpo).toEqual({ telefono: '300 123 4567' });
    expect(llamadas.find((l) => l.ruta === '/restablecer-contrasena/telefono').cuerpo).toEqual({ desafio: 'desafio-sms', codigo: '654321', password: 'NuevaClave2026', password_confirmation: 'NuevaClave2026' });
  });
});

describe('chat del panel con fotos', () => {
  it('adjunta una foto de la galería y la envía al asistente como formulario', async () => {
    guardarToken('token');
    const llamadas = simularApi({
      '/perfil': { datos: usuario },
      'POST /asistente': { datos: { texto: 'Análisis de la foto · Requiere revisión.', acciones: [], sugerencias: [], origen: 'local', analisis: { estado: 'revisar', zona: 'puertas' } } },
    });
    montar(<ChatAsistente modo="panel" saludo="Hola" />);

    await userEvent.click(screen.getByRole('button', { name: 'Abrir Asistente SIGMA' }));
    await userEvent.click(screen.getByRole('button', { name: 'Adjuntar una foto del ascensor' }));
    expect(screen.getByRole('menuitem', { name: /Tomar foto/ })).toBeInTheDocument();
    expect(screen.getByTestId('foto-camara')).toHaveAttribute('capture', 'environment');

    const foto = new File(['imagen'], 'puerta.jpg', { type: 'image/jpeg' });
    await userEvent.upload(screen.getByTestId('foto-galeria'), foto);
    expect(screen.getByText(/Foto lista para analizar/)).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText('Mensaje para el asistente'), 'puerta del ASC-201');
    await userEvent.click(screen.getByRole('button', { name: 'Enviar' }));

    expect(await screen.findByText(/Requiere revisión/)).toBeInTheDocument();
    const envio = llamadas.find((l) => l.ruta === '/asistente');
    expect(envio.cuerpo).toBeInstanceOf(FormData);
    expect(envio.cuerpo.get('mensaje')).toBe('puerta del ASC-201');
    expect(envio.cuerpo.get('imagen')).toBeInstanceOf(File);
    expect(screen.getByAltText('Foto enviada al asistente')).toBeInTheDocument();
  });

  it('el chat de la página pública no ofrece fotos', async () => {
    simularApi({});
    montar(<ChatAsistente modo="publico" saludo="Hola" />);
    await userEvent.click(screen.getByRole('button', { name: 'Abrir Asistente SIGMA' }));
    expect(screen.queryByRole('button', { name: 'Adjuntar una foto del ascensor' })).not.toBeInTheDocument();
  });
});

describe('tarjetas ampliables de la página de inicio', () => {
  it('al hacer clic se amplía con más información y se navega entre tarjetas', async () => {
    simularApi({});
    montar(<Landing />);

    await userEvent.click(screen.getByRole('button', { name: 'Ver más sobre Código QR por ascensor' }));
    const ventana = await screen.findByRole('dialog', { name: 'Código QR por ascensor' });
    expect(within(ventana).getByText(/Funcionalidad · 5 de 6/)).toBeInTheDocument();
    expect(within(ventana).getByText('Reporte de emergencias desde el QR')).toBeInTheDocument();

    await userEvent.click(within(ventana).getByRole('button', { name: 'Siguiente' }));
    await waitFor(() => expect(screen.getByRole('dialog', { name: 'Alertas de mantenimiento' })).toBeInTheDocument());

    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cerrar' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });
});
