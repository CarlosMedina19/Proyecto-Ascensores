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
      '/perfil': { datos: { id: 1, nombre: 'Coordinador', rol: { nombre: 'coordinador', permisos: ['clientes.ver', 'edificios.ver', 'equipos.ver'] } } },
      '/clientes': {
        datos: [{ id: 3, nombre: 'Administraciones Sur', tipo: 'juridica', nit: '900111222-1', estado: true, edificios_count: 2 }],
        metadatos: { pagina_actual: 1, ultima_pagina: 1, total: 1, desde: 1, hasta: 1 },
      },
      '/clientes/3/estructura': {
        datos: [
          {
            tipo: 'conjunto',
            nombre: 'Conjunto',
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
          { tipo: 'institucional', nombre: 'Institucional', edificios: [{ id: 9, nombre: 'Colegio Sur', direccion: 'Calle 2', ciudad: 'Bogotá', activo: true, equipos: [] }] },
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

    await userEvent.click(await screen.findByRole('button', { name: 'Ver edificios de Administraciones Sur' }));

    // Solo aparecen los tipos que tiene el cliente.
    expect(await screen.findByRole('region', { name: 'Conjuntos' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Institucionales' })).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Residenciales' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Torre 1' })).toHaveAttribute('href', '/edificios/8');

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar Torre 1' }));
    expect(screen.getByRole('link', { name: /ASC-T1/ })).toHaveAttribute('href', '/equipos/21');
  });

  it('en el menú cada tipo se despliega en Edificios y Edificios en Ascensores', async () => {
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
    expect(screen.queryByRole('link', { name: 'Edificios de conjuntos' })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar Conjuntos' }));
    expect(screen.getByRole('link', { name: 'Edificios de conjuntos' })).toHaveAttribute('href', '/clientes/conjuntos');
    expect(screen.queryByRole('link', { name: 'Ascensores de conjuntos' })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Desplegar edificios de conjuntos' }));
    expect(screen.getByRole('link', { name: 'Ascensores de conjuntos' })).toHaveAttribute('href', '/clientes/conjuntos/ascensores');
  });
});
