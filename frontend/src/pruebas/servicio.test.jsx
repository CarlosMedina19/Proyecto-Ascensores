import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import ReportarFalla from '../paginas/publico/ReportarFalla';
import DetalleCotizacion from '../paginas/servicio/DetalleCotizacion';
import Certificaciones from '../paginas/servicio/Certificaciones';
import { duracion, plazoCertificacion } from '../utilidades/servicio';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

/** Simula la API: cada ruta "MÉTODO /ruta" responde lo indicado; el resto, lista vacía. */
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

afterEach(() => {
  vi.unstubAllGlobals();
  guardarToken(null);
});

describe('reporte de fallas por QR', () => {
  it('pide los datos de contacto y muestra el número de seguimiento sin iniciar sesión', async () => {
    const llamadas = simularApi({
      'GET /publico/ascensores/abc123': { datos: { codigo: 'ASC-002', nombre: 'Torre A', edificio: 'Mirador del Parque' } },
      'POST /publico/ascensores/abc123/reportes': [
        { datos: { numero: 'EM-2026-00004', minutos_objetivo: 30 }, mensaje: 'Recibimos su reporte EM-2026-00004. Un técnico va en camino.' },
        201,
      ],
    });
    montar(<ReportarFalla />, '/reportar/abc123', '/reportar/:codigo');
    const usuario = userEvent.setup();

    expect(await screen.findByText(/ASC-002/)).toBeInTheDocument();
    const enviarBoton = screen.getByRole('button', { name: 'Enviar reporte' });
    expect(enviarBoton).toBeDisabled();

    await usuario.click(screen.getByLabelText('Personas atrapadas'));
    expect(screen.getByText(/no intente abrir las puertas/)).toBeInTheDocument();
    await usuario.type(screen.getByLabelText(/Su nombre/), 'Portería');
    await usuario.type(screen.getByLabelText(/Teléfono de contacto/), '6015551234');
    await usuario.click(enviarBoton);

    expect(await screen.findByText('Recibimos su reporte')).toBeInTheDocument();
    expect(screen.getByText('EM-2026-00004')).toBeInTheDocument();
    const envio = llamadas.find((l) => l.clave === 'POST /publico/ascensores/abc123/reportes');
    expect(envio.cuerpo).toMatchObject({ tipo: 'atrapamiento', reportante_nombre: 'Portería' });
  });

  it('avisa cuando el código del QR no existe', async () => {
    simularApi({ 'GET /publico/ascensores/falso': [{ mensaje: 'No encontrado' }, 404] });
    montar(<ReportarFalla />, '/reportar/falso', '/reportar/:codigo');
    expect(await screen.findByText(/No encontramos este ascensor/)).toBeInTheDocument();
  });
});

describe('cotizaciones en el portal del cliente', () => {
  const COTIZACION = {
    id: 7,
    numero: 'CT-2026-00001',
    titulo: 'Cambio de amortiguadores',
    estado: 'enviada',
    subtotal: 2000000,
    iva: 380000,
    total: 2380000,
    fecha_emision: '2026-10-20',
    vigente_hasta: '2026-11-19',
    equipo: { id: 3, codigo: 'ASC-002' },
    cliente: { id: 1, nombre: 'Conjunto Mirador' },
    items: [{ descripcion: 'Amortiguador hidráulico', cantidad: 2, valor_unitario: 1000000, valor_total: 2000000 }],
  };

  it('el cliente aprueba la cotización y ve el nuevo estado', async () => {
    guardarToken('token');
    const llamadas = simularApi({
      'GET /perfil': { datos: { id: 9, nombre: 'Laura Gómez', rol: { nombre: 'cliente', permisos: ['cotizaciones.ver', 'cotizaciones.responder'] } } },
      'GET /cotizaciones/7': { datos: COTIZACION },
      'POST /cotizaciones/7/respuesta': { datos: { ...COTIZACION, estado: 'aprobada' }, mensaje: 'Cotización CT-2026-00001 aprobada.' },
    });
    montar(<DetalleCotizacion />, '/cotizaciones/7', '/cotizaciones/:id');
    const usuario = userEvent.setup();

    await usuario.click(await screen.findByRole('button', { name: 'Aprobar cotización' }));
    await usuario.click(screen.getByRole('button', { name: /Aprobar por/ }));

    expect(await screen.findByText('Aprobada')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Aprobar cotización' })).not.toBeInTheDocument();
    expect(llamadas.find((l) => l.clave === 'POST /cotizaciones/7/respuesta').cuerpo).toEqual({ decision: 'aprobada', motivo: null });
  });

  it('sin permiso de responder no se muestran los botones', async () => {
    guardarToken('token');
    simularApi({
      'GET /perfil': { datos: { id: 2, nombre: 'Coordinación', rol: { nombre: 'coordinador', permisos: ['cotizaciones.ver'] } } },
      'GET /cotizaciones/7': { datos: COTIZACION },
    });
    montar(<DetalleCotizacion />, '/cotizaciones/7', '/cotizaciones/:id');
    expect(await screen.findByText('Esperando respuesta')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Aprobar cotización' })).not.toBeInTheDocument();
  });
});

describe('certificación anual', () => {
  it('ordena primero las vencidas y filtra por estado', async () => {
    guardarToken('token');
    simularApi({
      'GET /perfil': { datos: { id: 1, nombre: 'Coordinación', rol: { nombre: 'coordinador', permisos: ['certificaciones.ver'] } } },
      'GET /certificaciones': {
        datos: [
          { equipo: { id: 1, codigo: 'ASC-001' }, estado: 'vigente', dias_para_vencer: 200, certificacion: { organismo: 'Andinas', resultado: 'conforme', fecha_vencimiento: '2027-05-01' } },
          { equipo: { id: 2, codigo: 'ASC-002' }, estado: 'vencida', dias_para_vencer: -3, certificacion: { organismo: 'Andinas', resultado: 'conforme', fecha_vencimiento: '2026-10-18' } },
          { equipo: { id: 3, codigo: 'ASC-003' }, estado: 'sin_certificar', dias_para_vencer: null, certificacion: null },
        ],
        resumen: { vigente: 1, vencida: 1, sin_certificar: 1 },
      },
    });
    montar(<Certificaciones />, '/certificaciones', '/certificaciones');
    const usuario = userEvent.setup();

    const filas = await screen.findAllByRole('row');
    expect(filas[1]).toHaveTextContent('ASC-002');
    expect(filas[1]).toHaveTextContent('Venció hace 3 días');

    await usuario.click(screen.getByRole('button', { name: /Sin certificar/ }));
    expect(screen.getAllByRole('row')).toHaveLength(2);
    expect(screen.getByText('ASC-003')).toBeInTheDocument();
  });
});

describe('formatos del servicio', () => {
  it('muestra duraciones y plazos en palabras', () => {
    expect(duracion(25)).toBe('25 min');
    expect(duracion(65)).toBe('1 h 05 min');
    expect(plazoCertificacion(1)).toBe('Vence en 1 día');
    expect(plazoCertificacion(-2)).toBe('Venció hace 2 días');
    expect(plazoCertificacion(null)).toBe('Sin certificado registrado');
  });
});
