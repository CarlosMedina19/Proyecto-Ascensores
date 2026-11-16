import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import PlantillaPlanilla from '../paginas/configuracion/PlantillaPlanilla';
import ChatAsistente from '../componentes/ChatAsistente';

function respuestaJson(cuerpo) {
  return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve(cuerpo) });
}

/** Simula la API: cada ruta responde con un objeto o con una función (url, opciones) => objeto. */
function simularApi(rutas) {
  const llamadas = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const direccion = new URL(url);
      const ruta = direccion.pathname.replace('/api/v1', '');
      llamadas.push({ ruta, metodo: opciones.method || 'GET', cuerpo: opciones.body ? JSON.parse(opciones.body) : null });
      const r = rutas[`${opciones.method || 'GET'} ${ruta}`] ?? rutas[ruta] ?? { datos: [] };
      return respuestaJson(typeof r === 'function' ? r(opciones) : r);
    }),
  );
  return llamadas;
}

function montar(componente) {
  guardarToken('token');
  render(
    <MemoryRouter>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>{componente}</ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

const plantilla = [
  { id: 1, clave: 'cabina', nombre: 'Cabina', items: [{ id: 10, codigo: 'CB-01', nombre: 'Verificación de cabina.', critico: false }] },
  { id: 2, clave: 'foso', nombre: 'En foso', items: [{ id: 20, codigo: 'EF-02', nombre: 'Verificación del stop de emergencia.', critico: true }] },
];

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('planilla de asistencia técnica (configuración)', () => {
  it('muestra la plantilla como la planilla y agrega una actividad a la sección', async () => {
    const llamadas = simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Admin', rol: { nombre: 'administrador', permisos: ['configuracion.editar'] } } },
      'GET /planilla/plantilla': { datos: plantilla },
      'POST /planilla/items': (opciones) => {
        const cuerpo = JSON.parse(opciones.body);
        return { datos: [{ ...plantilla[0], items: [...plantilla[0].items, { id: 11, codigo: 'CB-02', nombre: cuerpo.nombre, critico: false }] }, plantilla[1]], mensaje: 'Actividad agregada.' };
      },
    });
    montar(<PlantillaPlanilla />);

    expect(await screen.findByText('Verificación de cabina.')).toBeInTheDocument();
    expect(screen.getByText('PLANILLA DE ASISTENCIA TÉCNICA')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Quitar crítica a Verificación del stop de emergencia.' })).toHaveAttribute('aria-pressed', 'true');

    const cabina = screen.getByRole('region', { name: 'Cabina' });
    await userEvent.type(within(cabina).getByLabelText('Nueva actividad en Cabina'), 'Verificación y limpieza de la botonera.');
    await userEvent.click(within(cabina).getByRole('button', { name: /Agregar/ }));
    expect(await screen.findByText('Verificación y limpieza de la botonera.')).toBeInTheDocument();
    expect(llamadas.find((l) => l.metodo === 'POST' && l.ruta === '/planilla/items').cuerpo).toMatchObject({ seccion_id: 1, nombre: 'Verificación y limpieza de la botonera.', critico: false });
  });
});

describe('asistente', () => {
  it('el del panel responde y ofrece descargar el PDF', async () => {
    const llamadas = simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Admin', rol: { nombre: 'administrador', permisos: [] } } },
      'POST /asistente': { datos: { texto: 'Aquí tiene el PDF de los indicadores.', acciones: [{ tipo: 'pdf', texto: 'Descargar indicadores', ruta: '/indicadores/servicio/pdf', nombre: 'SIGMA_Indicadores.pdf' }], sugerencias: [], origen: 'local' } },
    });
    montar(<ChatAsistente modo="panel" saludo="Hola" sugerencias={['Emergencias abiertas']} />);

    await userEvent.click(screen.getByRole('button', { name: 'Abrir Asistente SIGMA' }));
    await userEvent.type(screen.getByLabelText('Mensaje para el asistente'), 'pdf de indicadores');
    await userEvent.click(screen.getByRole('button', { name: 'Enviar' }));
    expect(await screen.findByText('Aquí tiene el PDF de los indicadores.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Descargar indicadores/ })).toBeInTheDocument();
    await waitFor(() => expect(llamadas.some((l) => l.ruta === '/asistente' && l.cuerpo.mensaje === 'pdf de indicadores')).toBe(true));
  });

  it('el de la página pública usa la ruta pública y muestra el contacto', async () => {
    const llamadas = simularApi({
      'POST /publico/asistente': { datos: { texto: 'Hay un solo plan que incluye todo.', acciones: [{ tipo: 'externo', texto: 'Escribir por WhatsApp', ruta: 'https://wa.me/573000000000' }], sugerencias: [], origen: 'local' } },
    });
    guardarToken(null);
    render(
      <MemoryRouter>
        <ChatAsistente modo="publico" saludo="Hola" sugerencias={['¿Cuánto cuesta?']} />
      </MemoryRouter>,
    );
    await userEvent.click(screen.getByRole('button', { name: 'Abrir Asistente SIGMA' }));
    await userEvent.click(screen.getByRole('button', { name: '¿Cuánto cuesta?' }));
    expect(await screen.findByText('Hay un solo plan que incluye todo.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Escribir por WhatsApp/ })).toHaveAttribute('href', 'https://wa.me/573000000000');
    expect(llamadas.some((l) => l.ruta === '/publico/asistente')).toBe(true);
  });
});
