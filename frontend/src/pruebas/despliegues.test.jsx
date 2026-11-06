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

  it('despliega el cliente en sus tipos, edificios y ascensores sin salir de la lista', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Coordinador', rol: { nombre: 'coordinador', permisos: ['clientes.ver', 'edificios.ver', 'edificios.crear', 'equipos.ver'] } } },
      '/clientes': {
        datos: [{ id: 3, nombre: 'Administraciones Sur', tipo: 'juridica', nit: '900111222-1', estado: true, edificios_count: 2 }],
        metadatos: { pagina_actual: 1, ultima_pagina: 1, total: 1, desde: 1, hasta: 1 },
      },
      '/clientes/3/estructura': {
        datos: [
          {
            tipo: 'conjunto',
            nombre: 'Conjunto',
            conjuntos: [
              {
                id: 5,
                nombre: 'Los Pinos',
                direccion: 'Calle 1',
                ciudad: 'Bogotá',
                activo: true,
                edificios: [
                  {
                    id: 8,
                    nombre: 'Torre 1',
                    direccion: 'Calle 1',
                    ciudad: 'Bogotá',
                    activo: true,
                    equipos: [{ id: 21, codigo: 'ASC-T1', marca: 'Otis', modelo: 'Gen2', estado: 'activo' }],
                  },
                ],
              },
            ],
            sin_conjunto: [],
          },
        ],
      },
    });

    render(
      <MemoryRouter>
        <ProveedorNotificaciones>
          <ProveedorAutenticacion>
            <ListaClientes />
          </ProveedorAutenticacion>
        </ProveedorNotificaciones>
      </MemoryRouter>,
    );

    await userEvent.click(await screen.findByRole('button', { name: 'Ver conjuntos de Administraciones Sur' }));

    // Solo aparecen los tipos que tiene el cliente; los que no tiene se ofrecen para crearlos.
    expect(await screen.findByRole('region', { name: 'Conjuntos' })).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Institucionales' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Crear institucional' })).toHaveAttribute('href', '/conjuntos/nuevo?cliente_id=3&tipo=institucional');

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar Conjuntos' }));
    expect(screen.getByRole('link', { name: 'Los Pinos' })).toHaveAttribute('href', '/conjuntos/5');
    expect(screen.getByRole('link', { name: 'Crear conjunto' })).toHaveAttribute('href', '/conjuntos/nuevo?cliente_id=3&tipo=conjunto');

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar Los Pinos' }));
    expect(screen.getByRole('link', { name: 'Torre 1' })).toHaveAttribute('href', '/edificios/8');
    expect(screen.getByRole('link', { name: 'Crear edificio' })).toHaveAttribute('href', '/edificios/nuevo?conjunto_id=5');

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar Torre 1' }));
    expect(screen.getByRole('link', { name: /ASC-T1/ })).toHaveAttribute('href', '/equipos/21');
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
    expect(screen.getByRole('link', { name: 'Institucionales' })).toHaveAttribute('href', '/clientes/institucionales');
  });
});
