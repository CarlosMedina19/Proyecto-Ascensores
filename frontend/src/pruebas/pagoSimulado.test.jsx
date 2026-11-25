import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import PagarEnLinea from '../paginas/pagos/PagarEnLinea';
import SimuladorPago from '../paginas/pagos/SimuladorPago';

function simularApi(respuestas) {
  const llamadas = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const ruta = new URL(url).pathname.replace('/api/v1', '');
      const clave = `${opciones.method || 'GET'} ${ruta}`;
      llamadas.push({ clave, cuerpo: opciones.body ? JSON.parse(opciones.body) : null });
      const [cuerpo, estado = 200] = [].concat(respuestas[clave] ?? { datos: [] });
      return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
    }),
  );
  return llamadas;
}

function montar(ruta, rutas) {
  render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <Routes>
            {Object.entries(rutas).map(([patron, elemento]) => (
              <Route key={patron} path={patron} element={elemento} />
            ))}
          </Routes>
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

const PERFIL = { datos: { id: 5, nombre: 'Cliente demo', correo: 'cliente@ejemplo.co', cliente_id: 3, rol: { nombre: 'cliente', permisos: ['cartera.ver'] } } };
const FACTURA = { id: 7, numero: 'FV-120', concepto: 'Mantenimiento de noviembre', fecha_emision: '2026-11-01', fecha_vencimiento: '2026-11-30', valor: 850000, saldo: 850000, estado: 'pendiente', dias_mora: 0 };
const REFERENCIA = 'SIGMA-FV-120-ABCD1234';
const PAGO = { referencia: REFERENCIA, estado: 'pendiente', monto: 850000, simulado: true, transaccion_id: null, cliente: 'Torres de Castilla', correo: 'cliente@ejemplo.co', factura: { id: 7, numero: 'FV-120', concepto: 'Mantenimiento de noviembre', saldo: 850000 } };

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('pago en línea simulado del cliente', () => {
  it('muestra el modo demostración, las facturas con saldo y abre la pasarela de prueba', async () => {
    guardarToken('token');
    const llamadas = simularApi({
      'GET /perfil': PERFIL,
      'GET /pagos-en-linea/configuracion': { datos: { habilitado: false, pruebas: false, simulado: true } },
      'GET /facturas': { datos: [FACTURA], metadatos: { pagina_actual: 1, ultima_pagina: 1, total: 1 } },
      'POST /facturas/7/pago-en-linea': [{ datos: { url: `/pagos/simulador?referencia=${REFERENCIA}`, referencia: REFERENCIA, monto: 850000, simulado: true } }, 201],
    });
    montar('/pagos', { '/pagos': <PagarEnLinea />, '/pagos/simulador': <p>Pasarela abierta</p> });

    expect(await screen.findByText('FV-120')).toBeInTheDocument();
    expect(screen.getByText('Modo demostración.')).toBeInTheDocument();
    expect(llamadas.find((l) => l.clave === 'GET /facturas')).toBeTruthy();

    await userEvent.click(screen.getByRole('button', { name: /Pagar$/ }));
    await userEvent.click(screen.getByRole('button', { name: /Continuar al pago/ }));
    expect(await screen.findByText('Pasarela abierta')).toBeInTheDocument();
    expect(llamadas.find((l) => l.clave === 'POST /facturas/7/pago-en-linea').cuerpo).toEqual({});
  });

  it('pide los datos del pago, va al banco de prueba y aprueba el pago', async () => {
    guardarToken('token');
    const llamadas = simularApi({
      'GET /perfil': PERFIL,
      [`GET /pagos-en-linea/${REFERENCIA}`]: { datos: PAGO },
      [`POST /pagos-en-linea/${REFERENCIA}/simular`]: { datos: { ...PAGO, estado: 'aprobado', transaccion_id: 'SIM-XYZ' } },
    });
    montar(`/pagos/simulador?referencia=${REFERENCIA}`, { '/pagos/simulador': <SimuladorPago />, '/pagos/resultado': <p>Resultado del pago</p> });

    expect(await screen.findByText('$ 850.000')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: 'Ir al banco' }));
    expect(screen.getByText('Elija su banco.')).toBeInTheDocument();

    await userEvent.selectOptions(screen.getByLabelText(/^Banco/), 'Banco de Prueba Andino');
    await userEvent.type(screen.getByLabelText(/Número de documento/), '900123456');
    await userEvent.click(screen.getByRole('checkbox'));
    await userEvent.click(screen.getByRole('button', { name: 'Ir al banco' }));

    await userEvent.click(await screen.findByRole('button', { name: 'Aprobar el pago' }));
    expect(await screen.findByText('Resultado del pago', {}, { timeout: 4000 })).toBeInTheDocument();
    expect(llamadas.find((l) => l.clave.endsWith('/simular')).cuerpo).toEqual({ resultado: 'aprobado', banco: 'Banco de Prueba Andino' });
  });
});
