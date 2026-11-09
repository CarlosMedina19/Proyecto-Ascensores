import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import ListaClientes from '../paginas/clientes/ListaClientes';
import Estructura from '../componentes/Estructura';

function respuestaJson(cuerpo) {
  return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(cuerpo) });
}

function simularApi(rutas) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url) => {
      const direccion = new URL(url);
      const ruta = direccion.pathname.replace('/api/v1', '');
      const respuesta = rutas[`${ruta}${direccion.search}`] ?? rutas[ruta];
      return respuestaJson(typeof respuesta === 'function' ? respuesta(direccion) : (respuesta ?? { datos: [] }));
    }),
  );
}

describe('clientes desplegables', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('lista las entidades de la categoría y despliega edificios y ascensores bajo demanda', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Coordinador', rol: { nombre: 'coordinador', permisos: ['clientes.ver', 'edificios.ver', 'edificios.crear', 'equipos.ver', 'equipos.crear'] } } },
      '/conjuntos': {
        datos: [
          {
            id: 5, nombre: 'Los Pinos', tipo: 'conjunto', direccion: 'Calle 1', ciudad: 'Bogotá', activo: true, edificios_count: 1, equipos_count: 2,
            nombre_contacto: 'Marta', telefono_contacto: '3001112233', cliente: { id: 3, nombre: 'Administraciones Sur', tipo: 'juridica', nit: '900111222-1' },
          },
        ],
        metadatos: { pagina_actual: 1, ultima_pagina: 1, total: 1, desde: 1, hasta: 1 },
      },
      '/edificios': { datos: [{ id: 8, nombre: 'Torre 1', direccion: 'Calle 1', ciudad: 'Bogotá', activo: true, equipos_count: 1 }], metadatos: {} },
      '/equipos': {
        datos: [{ id: 21, codigo: 'ASC-T1', marca: 'Otis', modelo: 'Gen2', estado: 'mantenimiento', proximo_mantenimiento: '2026-12-01', asignacion_activa: { ultimo_mantenimiento: '2026-11-01' } }],
        metadatos: {},
      },
    });

    render(
      <MemoryRouter>
        <ProveedorNotificaciones>
          <ProveedorAutenticacion>
            <ListaClientes vista="conjunto" />
          </ProveedorAutenticacion>
        </ProveedorNotificaciones>
      </MemoryRouter>,
    );

    // Primer nivel: solo la entidad, con su cliente y sus conteos.
    expect(await screen.findByText('Los Pinos')).toBeInTheDocument();
    expect(screen.getByText('Administraciones Sur')).toBeInTheDocument();
    expect(screen.getByText('900111222-1')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Conjuntos' })).toHaveAttribute('aria-current', 'page');
    expect(screen.queryByText('Torre 1')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Ver edificios de Los Pinos' }));
    expect(await screen.findByRole('link', { name: /Torre 1/ })).toHaveAttribute('href', '/edificios/8');
    expect(screen.getByRole('link', { name: /Crear edificio/ })).toHaveAttribute('href', '/edificios/nuevo?conjunto_id=5');

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar Torre 1' }));
    expect(await screen.findByRole('link', { name: /ASC-T1/ })).toHaveAttribute('href', '/equipos/21');
    expect(screen.getByText('En mantenimiento')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Agregar ascensor/ })).toHaveAttribute('href', '/equipos/nuevo?edificio_id=8');
  });

  it('en el menú cada tipo lleva a su página', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Coordinador', rol: { nombre: 'coordinador', permisos: ['clientes.ver', 'edificios.ver', 'equipos.ver'] } } },
    });

    render(
      <MemoryRouter initialEntries={['/inicio']}>
        <ProveedorNotificaciones>
          <ProveedorAutenticacion>
            <Routes>
              <Route element={<Estructura />}>
                <Route path="/inicio" element={<div>contenido</div>} />
              </Route>
            </Routes>
          </ProveedorAutenticacion>
        </ProveedorNotificaciones>
      </MemoryRouter>,
    );

    await userEvent.click(await screen.findByRole('button', { name: 'Desplegar Clientes' }));
    expect(screen.getByRole('link', { name: 'Conjuntos' })).toHaveAttribute('href', '/clientes/conjuntos');
    expect(screen.getByRole('link', { name: 'Instituciones' })).toHaveAttribute('href', '/clientes/institucionales');
  });
});
