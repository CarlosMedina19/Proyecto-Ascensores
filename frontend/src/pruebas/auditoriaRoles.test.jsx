import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import ListaRoles from '../paginas/roles/ListaRoles';

// Leaflet necesita un navegador real: en las pruebas el mapa se reemplaza por una lista simple.
vi.mock('../paginas/auditoria/MapaTecnicos', () => ({
  default: ({ tecnicos }) => <div data-testid="mapa">{tecnicos.length} en el mapa</div>,
}));

const { default: ListaAuditoria } = await import('../paginas/auditoria/ListaAuditoria');

function respuestaJson(cuerpo) {
  return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(cuerpo) });
}

function simularApi(rutas) {
  const llamadas = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url) => {
      const direccion = new URL(url);
      llamadas.push(direccion);
      return respuestaJson(rutas[direccion.pathname.replace('/api/v1', '')] ?? { datos: [] });
    }),
  );
  return llamadas;
}

function montar(pagina, rol = 'administrador') {
  guardarToken('token');
  render(
    <MemoryRouter>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>{pagina}</ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
  return rol;
}

const perfil = (rol) => ({ datos: { id: 1, nombre: 'Admin', rol: { nombre: rol, permisos: ['registros_auditoria.ver', 'roles.ver'] } } });

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('auditoría en vivo', () => {
  it('muestra los técnicos en mantenimiento y solo los movimientos recientes hasta buscar en el historial', async () => {
    const llamadas = simularApi({
      '/perfil': perfil('administrador'),
      '/auditoria/en-vivo': {
        datos: [{ tecnico_id: 3, tecnico: 'Andrés Rojas', orden: { id: 9, numero: 'OT-0009' }, equipo: 'ASC-001', edificio: 'Torre A', latitud: 4.7, longitud: -74.1, registrada_en: new Date().toISOString() }],
      },
      '/registros-auditoria': { datos: [{ id: 1, accion: 'creado', modulo: 'clientes', modelo: 'Cliente', modelo_id: 4, usuario: { nombre: 'Admin' }, created_at: new Date().toISOString() }], metadatos: null },
    });
    montar(<ListaAuditoria />);

    expect(await screen.findByText('Andrés Rojas')).toBeInTheDocument();
    expect(await screen.findByTestId('mapa')).toHaveTextContent('1 en el mapa');
    expect(screen.getByText('Movimientos recientes')).toBeInTheDocument();
    await waitFor(() => expect(llamadas.some((u) => u.pathname.endsWith('/registros-auditoria') && u.searchParams.get('recientes_horas') === '24')).toBe(true));
    expect(screen.queryByLabelText('Módulo')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Buscar en el historial/ }));
    expect(screen.getByLabelText('Módulo')).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Buscar por usuario, correo o IP'), 'andres');
    await waitFor(() => expect(llamadas.some((u) => u.searchParams.get('busqueda') === 'andres' && !u.searchParams.has('recientes_horas'))).toBe(true));
  });
});

describe('credenciales en roles y permisos', () => {
  const rutas = (rol) => ({
    '/perfil': perfil(rol),
    '/roles': { datos: [{ id: 2, nombre: 'tecnico', descripcion: 'Técnicos', permisos: [], usuarios_count: 1 }] },
    '/roles/credenciales': { datos: [{ rol_id: 2, correo: 'tecnicosigma@gmail.com', tiene_contrasena: true }] },
    '/roles/2/credencial': { datos: { rol_id: 2, correo: 'tecnicosigma@gmail.com', contrasena: 'tecnicosigma123' } },
  });

  it('el administrador ve el correo y la contraseña oculta hasta tocar el ojo', async () => {
    simularApi(rutas('administrador'));
    montar(<ListaRoles />);
    expect(await screen.findByText('tecnicosigma@gmail.com')).toBeInTheDocument();
    expect(screen.queryByText('tecnicosigma123')).not.toBeInTheDocument();
    expect(screen.getByText('••••••••')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Mostrar contraseña' }));
    expect(await screen.findByText('tecnicosigma123')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Ocultar contraseña' }));
    expect(screen.queryByText('tecnicosigma123')).not.toBeInTheDocument();
  });

  it('otros roles no ven las columnas de credenciales', async () => {
    const llamadas = simularApi(rutas('coordinador'));
    montar(<ListaRoles />);
    expect(await screen.findByText('Técnicos')).toBeInTheDocument();
    expect(screen.queryByText('Correo')).not.toBeInTheDocument();
    expect(screen.queryByText('Contraseña')).not.toBeInTheDocument();
    expect(llamadas.some((u) => u.pathname.endsWith('/roles/credenciales'))).toBe(false);
  });
});
