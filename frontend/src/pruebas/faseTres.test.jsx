import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import Calendario from '../paginas/mantenimientos/Calendario';
import DetalleOrden from '../paginas/ordenes/DetalleOrden';
import SelectorTecnicos from '../paginas/ordenes/SelectorTecnicos';
import { duracion } from '../utilidades/formato';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

/** Simula la API; cada ruta responde un objeto o una función (url, opciones) => objeto. */
function simularApi(rutas) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const direccion = new URL(url);
      const clave = `${opciones.method || 'GET'} ${direccion.pathname.replace('/api/v1', '')}`;
      const respuesta = rutas[clave] ?? rutas[direccion.pathname.replace('/api/v1', '')];
      return respuestaJson(typeof respuesta === 'function' ? respuesta(direccion, opciones) : (respuesta ?? { datos: [] }));
    }),
  );
}

function perfil(rol, permisos) {
  return { datos: { id: 5, nombre: 'Usuario Demo', rol: { nombre: rol, permisos } } };
}

function montar(elemento, ruta, patron = '*') {
  return render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <Routes>
            <Route path={patron} element={elemento} />
            <Route path="/ordenes/:id" element={<div>detalle de la orden</div>} />
          </Routes>
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

const ORDEN = {
  id: 9,
  numero: 'OT-2026-00009',
  servicio: 'correctivo',
  cliente: { id: 1, nombre: 'Mirador' },
  edificio: { id: 2, nombre: 'Torre A' },
  equipo: { id: 3, codigo: 'ASC-002' },
  fecha: '2026-11-03',
  hora: '10:00',
  fin_estimado: '11:30',
  duracion_estimada: 90,
  prioridad: 'alta',
  estado: 'en_proceso',
  inicio_en: '2026-11-03T10:05:00-05:00',
  tecnicos: [{ id: 1, usuario_id: 5, nombre: 'Andrés Rojas', especialidades: ['Mecánica'] }],
  trazabilidad: [
    { id: 2, accion: 'estado_cambiado', campo: 'estado', valor_anterior: 'en_camino', valor_nuevo: 'en_proceso', created_at: '2026-11-03T10:05:00-05:00', usuario: { nombre: 'Andrés Rojas' } },
    { id: 1, accion: 'creada', detalle: 'Orden creada', created_at: '2026-11-02T09:00:00-05:00', usuario: { nombre: 'Coordinador' } },
  ],
  permisos: { editar: false, asignar: false, cancelar: false, ejecutar: true },
};

describe('utilidades de la fase 3', () => {
  it('expresa la duración en horas y minutos', () => {
    expect(duracion(30)).toBe('30 min');
    expect(duracion(90)).toBe('1 h 30 min');
    expect(duracion(120)).toBe('2 h');
    expect(duracion(null)).toBe('—');
  });
});

describe('orden de trabajo', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('el técnico asignado finaliza la orden con informe y ve la trazabilidad', async () => {
    guardarToken('token');
    let cuerpoEnviado = null;
    simularApi({
      '/perfil': perfil('tecnico', ['ordenes.ver', 'ordenes.ejecutar']),
      'GET /ordenes/9': { datos: ORDEN },
      'PATCH /ordenes/9/estado': (_url, opciones) => {
        cuerpoEnviado = JSON.parse(opciones.body);
        return { datos: { ...ORDEN, estado: 'finalizada', informe: cuerpoEnviado.informe } };
      },
    });
    montar(<DetalleOrden />, '/ordenes/9', '/ordenes/:id');

    expect(await screen.findByRole('heading', { name: 'Orden OT-2026-00009' })).toBeInTheDocument();
    expect(screen.getByText('Cambio de estado')).toBeInTheDocument();
    expect(screen.getByText('En camino → En proceso')).toBeInTheDocument();
    // El técnico no reprograma ni cancela.
    expect(screen.queryByRole('button', { name: /reprogramar/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /cancelar/i })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Finalizar orden' }));
    const ventana = screen.getByRole('dialog', { name: 'Finalizar orden' });
    await userEvent.type(within(ventana).getByLabelText(/informe del trabajo/i), 'Se cambiaron los amortiguadores del foso.');
    await userEvent.click(within(ventana).getByRole('button', { name: 'Finalizar orden' }));

    expect(cuerpoEnviado).toMatchObject({ estado: 'finalizada', informe: 'Se cambiaron los amortiguadores del foso.' });
  });

  it('el selector muestra por qué un técnico no se puede asignar', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': perfil('coordinador', ['tecnicos.ver']),
      '/tecnicos/disponibilidad': {
        datos: [
          { id: 1, nombre: 'Andrés Rojas', especialidades: ['Mecánica'], asignable: true, motivo: null, conflictos: [] },
          {
            id: 2,
            nombre: 'Diana Mora',
            especialidades: ['Electricidad'],
            asignable: false,
            motivo: 'Tiene otra orden en ese horario',
            conflictos: [{ id: 4, numero: 'OT-2026-00004', inicio: '09:30', fin: '11:00' }],
          },
        ],
      },
    });
    const alCambiar = vi.fn();
    montar(<SelectorTecnicos fecha="2026-11-03" hora="10:00" duracion={90} seleccion={[]} alCambiar={alCambiar} />, '/');

    const diana = await screen.findByRole('checkbox', { name: /Diana Mora/ });
    expect(diana).toBeDisabled();
    expect(screen.getByText(/OT-2026-00004 \(09:30–11:00\)/)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('checkbox', { name: /Andrés Rojas/ }));
    expect(alCambiar).toHaveBeenCalledWith([1]);
  });
});

describe('calendario con órdenes', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('el técnico ve solo sus órdenes (no las visitas del plan) y abre la orden', async () => {
    guardarToken('token');
    const llamadas = [];
    simularApi({
      '/perfil': perfil('tecnico', ['ordenes.ver', 'mantenimientos.ver']),
      '/ordenes/agenda': (url) => {
        llamadas.push(url.pathname);
        return { datos: [{ ...ORDEN, estado: 'asignada' }] };
      },
      '/mantenimientos': (url) => {
        llamadas.push(url.pathname);
        return { datos: [] };
      },
    });
    montar(<Calendario />, '/calendario?fecha=2026-11-03');

    const orden = await screen.findByRole('button', { name: /ASC-002 · Torre A/ });
    expect(orden).toHaveTextContent('10:00 · Andrés R.');
    expect(llamadas.some((ruta) => ruta.endsWith('/mantenimientos'))).toBe(false);
    expect(screen.queryByText('Visita del plan sin orden')).not.toBeInTheDocument();

    await userEvent.click(orden);
    expect(await screen.findByText('detalle de la orden')).toBeInTheDocument();
  });
});
