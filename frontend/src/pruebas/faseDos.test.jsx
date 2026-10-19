import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import Estructura from '../componentes/Estructura';
import Calendario from '../paginas/mantenimientos/Calendario';
import { dinero, estadoMantenimiento, fechaIso, periodicidad } from '../utilidades/formato';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

/** Simula la API respondiendo según la ruta. */
function simularApi(rutas) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url) => {
      const ruta = new URL(url).pathname.replace('/api/v1', '');
      const respuesta = rutas[ruta];
      return respuestaJson(typeof respuesta === 'function' ? respuesta(url) : (respuesta ?? { datos: [] }));
    }),
  );
}

function perfil(rol, permisos, cliente = null) {
  return { datos: { id: 1, nombre: 'Usuario Demo', rol: { nombre: rol, permisos }, cliente } };
}

function montar(elemento, ruta) {
  return render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>{elemento}</ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

function montarEstructura() {
  return montar(
    <Routes>
      <Route element={<Estructura />}>
        <Route path="/inicio" element={<div>contenido</div>} />
      </Route>
    </Routes>,
    '/inicio',
  );
}

describe('utilidades de la fase 2', () => {
  it('formatea pesos colombianos sin decimales', () => {
    expect(dinero('1250000.00')).toMatch(/\$\s?1\.250\.000/);
    expect(dinero(null)).toBe('—');
  });

  it('nombra las periodicidades de 1 a 6 meses', () => {
    expect(periodicidad(1)).toBe('Mensual (1 mes)');
    expect(periodicidad(3)).toBe('Trimestral (3 meses)');
    expect(periodicidad(5)).toBe('Cada 5 meses');
    expect(periodicidad(6)).toBe('Semestral (6 meses)');
  });

  it('marca como atrasado un mantenimiento pendiente con fecha pasada', () => {
    const ayer = new Date();
    ayer.setDate(ayer.getDate() - 1);
    expect(estadoMantenimiento({ estado: 'programado', fecha_programada: fechaIso(ayer) }).texto).toBe('Atrasado');
    expect(estadoMantenimiento({ estado: 'ejecutado', fecha_programada: fechaIso(ayer) }).texto).toBe('Ejecutado');
    expect(estadoMantenimiento({ estado: 'programado', fecha_programada: fechaIso() }).texto).toBe('Programado');
  });
});

describe('menú de la fase 2 según permisos', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('el coordinador ve calendario, contratos y planes, pero no la configuración', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': perfil('coordinador', ['equipos.ver', 'mantenimientos.ver', 'contratos.ver', 'planes.ver']),
    });
    montarEstructura();

    expect(await screen.findByRole('link', { name: /calendario/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /^contratos$/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /planes y precios/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /configuración/i })).not.toBeInTheDocument();
  });

  it('el cliente ve sus contratos y el calendario, pero no el catálogo de planes', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': perfil('cliente', ['equipos.ver', 'mantenimientos.ver', 'contratos.ver', 'asignaciones.ver'], { id: 1, nombre: 'Conjunto Mirador' }),
    });
    montarEstructura();

    expect(await screen.findByRole('link', { name: /mis contratos/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /calendario/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /planes y precios/i })).not.toBeInTheDocument();
  });

  it('la campana muestra las notificaciones sin leer', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': perfil('administrador', ['configuracion.editar']),
      '/notificaciones': {
        datos: [
          { id: 'a1', titulo: 'Mantenimiento en 7 días', mensaje: 'ASC-001 el martes.', leida: false, tipo: 'AvisoMantenimientoNotification', created_at: '2026-10-17T10:00:00-05:00' },
          { id: 'a2', titulo: 'Contrato por vencer', mensaje: 'CT-2026-0001.', leida: true, tipo: 'AlertaVencimientoContratoNotification', created_at: '2026-10-16T10:00:00-05:00' },
        ],
        no_leidas: 1,
      },
    });
    montarEstructura();

    const campana = await screen.findByRole('button', { name: 'Notificaciones: 1 sin leer' });
    expect(screen.getByRole('link', { name: /configuración/i })).toBeInTheDocument();

    await userEvent.click(campana);
    const panel = screen.getByRole('dialog', { name: 'Notificaciones' });
    expect(within(panel).getByText('Mantenimiento en 7 días')).toBeInTheDocument();
    expect(within(panel).getByText('Contrato por vencer')).toBeInTheDocument();
  });
});

describe('calendario de mantenimientos', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('consulta el rango del mes y muestra las visitas en su día', async () => {
    guardarToken('token');
    const hoy = fechaIso();
    simularApi({
      '/perfil': perfil('supervisor', ['mantenimientos.ver']),
      '/mantenimientos': {
        datos: [
          {
            id: 9,
            equipo_id: 3,
            equipo: { id: 3, codigo: 'ASC-003', edificio: { id: 1, nombre: 'Torre A', cliente: { id: 1, nombre: 'Mirador' } } },
            plan: { id: 1, nombre: 'Plan Integral' },
            periodicidad: { id: 1, meses: 1, nombre: 'Mensual', activo: true },
            fecha_programada: hoy,
            fecha_teorica: hoy,
            precio: '620000.00',
            estado: 'programado',
          },
        ],
      },
    });
    montar(<Calendario />, '/calendario');

    const visita = await screen.findByRole('button', { name: /ASC-003/ });
    expect(visita).toBeInTheDocument();

    const llamada = fetch.mock.calls.map(([url]) => new URL(url)).find((url) => url.pathname.endsWith('/mantenimientos'));
    expect(llamada.searchParams.get('desde') <= hoy).toBe(true);
    expect(llamada.searchParams.get('hasta') >= hoy).toBe(true);

    // El supervisor solo consulta: el detalle no ofrece acciones.
    await userEvent.click(visita);
    expect(await screen.findByText('Plan Integral')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reprogramar' })).not.toBeInTheDocument();
  });
});
