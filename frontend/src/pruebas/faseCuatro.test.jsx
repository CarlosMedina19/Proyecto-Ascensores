import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import PlanillaOrden from '../paginas/ordenes/PlanillaOrden';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

function simularApi(rutas) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const direccion = new URL(url);
      const ruta = direccion.pathname.replace('/api/v1', '');
      const respuesta = rutas[`${opciones.method || 'GET'} ${ruta}`] ?? rutas[ruta];
      return respuestaJson(typeof respuesta === 'function' ? respuesta(direccion, opciones) : (respuesta ?? { datos: [] }));
    }),
  );
}

const ITEMS = [
  { id: 1, seccion: 'cuarto_maquinas', codigo: 'CM-01', nombre: 'Freno de máquina', critico: true },
  { id: 2, seccion: 'cuarto_maquinas', codigo: 'CM-02', nombre: 'Aceite del motor', critico: false },
  { id: 3, seccion: 'cabina', codigo: 'CA-01', nombre: 'Iluminación', critico: false },
];

const ORDEN = { id: 4, numero: 'OT-2026-00004', servicio: 'preventivo', estado: 'en_proceso', lleva_planilla: true, permisos: { ejecutar: true } };

function montar(orden, notificar = vi.fn()) {
  render(
    <MemoryRouter>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <PlanillaOrden orden={orden} alCambiar={vi.fn()} notificar={notificar} />
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
  return notificar;
}

describe('planilla de mantenimiento (fase 4)', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('exige observación en estado Malo y luego envía el ítem', async () => {
    guardarToken('token');
    let enviado = null;
    simularApi({
      '/perfil': { datos: { id: 5, nombre: 'Andrés Rojas', rol: { nombre: 'tecnico', permisos: ['ordenes.ejecutar'] } } },
      '/checklist/items': { datos: ITEMS },
      'GET /ordenes/4/planilla': { datos: { resultados: [], fotos: [], recomendaciones: null }, faltantes: 3 },
      'PUT /ordenes/4/planilla': (_url, opciones) => {
        enviado = JSON.parse(opciones.body);
        return { datos: { resultados: [{ id: 10, item_id: 1, ...enviado.resultados[0] }], fotos: [], recomendaciones: null }, faltantes: 2 };
      },
    });
    const notificar = montar(ORDEN);

    expect(await screen.findByText('Freno de máquina')).toBeInTheDocument();
    await userEvent.click(screen.getAllByRole('radio', { name: 'INS' })[0]);
    await userEvent.click(screen.getAllByRole('radio', { name: 'M' })[0]);
    await userEvent.click(screen.getByRole('button', { name: 'Guardar planilla' }));

    expect(enviado).toBeNull();
    expect(screen.getByText('Un ítem en estado Regular o Malo exige una observación.')).toBeInTheDocument();

    await userEvent.type(screen.getByLabelText('Observación de Freno de máquina'), 'Zapata gastada');
    await userEvent.click(screen.getByRole('button', { name: 'Guardar planilla' }));

    expect(enviado.resultados).toEqual([{ item_id: 1, resultado: 'INS', estado: 'M', observacion: 'Zapata gastada' }]);
    expect(notificar).toHaveBeenCalledWith('Planilla guardada.');
    // Ya guardado en M, ofrece la orden correctiva derivada (RF-038).
    expect(await screen.findByRole('button', { name: /crear orden correctiva/i })).toBeInTheDocument();
  });

  it('fuera de proceso la planilla es de solo lectura', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': { datos: { id: 6, nombre: 'Supervisor', rol: { nombre: 'supervisor', permisos: ['ordenes.ver'] } } },
      '/checklist/items': { datos: ITEMS },
      'GET /ordenes/4/planilla': {
        datos: { resultados: [{ id: 10, item_id: 1, resultado: 'INS', estado: 'R', observacion: 'Desgaste leve', correctivo_abierto: { id: 5, numero: 'OT-2026-00005' } }], fotos: [], recomendaciones: 'Revisar en un mes' },
        faltantes: 2,
      },
    });
    montar({ ...ORDEN, estado: 'finalizada', permisos: { ejecutar: false } });

    expect(await screen.findByText('Desgaste leve')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Guardar planilla' })).not.toBeInTheDocument();
    expect(screen.queryAllByRole('radio')).toHaveLength(0);
    expect(screen.getByRole('link', { name: /OT-2026-00005/ })).toHaveAttribute('href', '/ordenes/5');
    expect(screen.getByText('Revisar en un mes')).toBeInTheDocument();
  });
});
