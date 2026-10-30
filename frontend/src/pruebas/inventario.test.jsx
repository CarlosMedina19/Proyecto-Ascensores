import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import Inventario from '../paginas/inventario/Inventario';
import DetalleRepuesto from '../paginas/inventario/DetalleRepuesto';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

function simularApi(respuestas) {
  const llamadas = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const ruta = new URL(url).pathname.replace('/api/v1', '');
      const clave = `${opciones.method || 'GET'} ${ruta}`;
      llamadas.push({ clave, cuerpo: opciones.body ? JSON.parse(opciones.body) : null });
      const respuesta = respuestas[clave];
      if (respuesta) return respuestaJson(...(Array.isArray(respuesta) ? respuesta : [respuesta]));
      return respuestaJson({ datos: [] });
    }),
  );
  return llamadas;
}

function montar(elemento, ruta, patron) {
  render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <Routes>
            <Route path={patron} element={elemento} />
          </Routes>
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

const PERFIL = { datos: { id: 2, nombre: 'Coordinación', rol: { nombre: 'coordinador', permisos: ['inventario.ver', 'inventario.gestionar', 'inventario.movimientos'] } } };
const ACEITE = { id: 2, codigo: 'ACE-002', nombre: 'Aceite hidráulico', unidad: 'litro', precio: 38000, stock: 6, stock_minimo: 8, bajo_minimo: true, activo: true };

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('inventario de repuestos', () => {
  it('muestra el resumen y marca los repuestos bajo el mínimo', async () => {
    guardarToken('token');
    simularApi({
      'GET /perfil': PERFIL,
      'GET /repuestos': { datos: [ACEITE], metadatos: { pagina_actual: 1, ultima_pagina: 1, total: 1 }, resumen: { repuestos: 1, bajo_minimo: 1, sin_stock: 0, valor: 228000 } },
    });
    montar(<Inventario />, '/inventario', '/inventario');

    expect(await screen.findByText('Aceite hidráulico')).toBeInTheDocument();
    expect(screen.getByText('Bajo el mínimo', { selector: '.insignia, .insignia *' })).toBeInTheDocument();
    expect(screen.getByText('$ 228.000')).toBeInTheDocument();
  });

  it('registra un ajuste con el stock contado y el motivo', async () => {
    guardarToken('token');
    const llamadas = simularApi({
      'GET /perfil': PERFIL,
      'GET /repuestos/2': { datos: ACEITE },
      'POST /repuestos/2/movimientos': [{ datos: { id: 9, tipo: 'ajuste', variacion: 2 }, repuesto: { ...ACEITE, stock: 8 }, mensaje: 'Ajuste registrada. Stock actual: 8 litro.' }, 201],
    });
    montar(<DetalleRepuesto />, '/inventario/2', '/inventario/:id');

    await userEvent.click(await screen.findByRole('button', { name: /Ajuste/ }));
    await userEvent.type(screen.getByLabelText(/Stock contado/), '8');
    await userEvent.type(screen.getByLabelText(/Motivo/), 'Conteo físico del mes');
    await userEvent.click(screen.getByRole('button', { name: 'Registrar ajuste' }));

    const envio = llamadas.find((l) => l.clave === 'POST /repuestos/2/movimientos');
    expect(envio.cuerpo).toEqual({ tipo: 'ajuste', stock_contado: 8, motivo: 'Conteo físico del mes' });
  });
});
