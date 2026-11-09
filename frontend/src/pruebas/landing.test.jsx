import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import Landing from '../paginas/publico/Landing';
function respuestaJson(cuerpo) {
  return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(cuerpo) });
}

function simularApi(rutas) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url) => respuestaJson(rutas[new URL(url).pathname.replace('/api/v1', '')] ?? { datos: [] })),
  );
}

function montar() {
  render(
    <MemoryRouter>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <Landing />
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('página de inicio pública', () => {
  it('presenta SIGMA y lleva al inicio de sesión', async () => {
    simularApi({});
    montar();
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('bajo control');
    expect(screen.getAllByRole('link', { name: 'Iniciar sesión' })[0]).toHaveAttribute('href', '/iniciar-sesion');
    for (const seccion of ['Funcionalidades', 'Aplicación móvil', 'Planes', 'Preguntas frecuentes', 'Contacto']) {
      expect(screen.getAllByText(seccion).length).toBeGreaterThan(0);
    }
    expect(document.title).toMatch(/SIGMA/);
  });

  it('con sesión activa el botón lleva al panel', async () => {
    guardarToken('token');
    simularApi({ '/perfil': { datos: { id: 1, nombre: 'Coordinación', rol: { nombre: 'coordinador', permisos: [] } } } });
    montar();
    expect(await screen.findByRole('link', { name: 'Ir al panel' })).toHaveAttribute('href', '/inicio');
  });
});
