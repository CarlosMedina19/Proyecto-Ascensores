import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import HerramientasExcel from '../componentes/HerramientasExcel';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

const VISTA = {
  id: '6f1c2a5e-0000-4000-8000-000000000001',
  archivo: 'carga.xlsx',
  hojas: [
    { nombre: 'Ascensores', total: 3, creados: 2, actualizados: 0, existentes: 0, errores: 1, duplicados: 0 },
    { nombre: 'Clientes', total: 2, creados: 1, actualizados: 0, existentes: 0, errores: 0, duplicados: 1 },
  ],
  totales: { validas: 3, errores: 1, duplicados: 1 },
  errores: [{ hoja: 'Ascensores', fila: 4, columna: 'Paradas', motivo: 'Falta Paradas. Es obligatorio.' }],
  duplicados: [{ hoja: 'Clientes', fila: 3, columna: 'Código', motivo: 'El código CL-1 ya aparece en la fila 2 de esta hoja; se omitió esta fila.' }],
  derivados: { puertas_creadas: 14, contratos_creados: 0, planes_asignados: 0, invitaciones: 0 },
  avisos: [],
};

function montar(permisos, alImportar = vi.fn()) {
  guardarToken('token');
  const llamadas = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url, opciones = {}) => {
      const ruta = new URL(url).pathname.replace('/api/v1', '');
      llamadas.push(`${opciones.method || 'GET'} ${ruta}`);
      if (ruta === '/perfil') return respuestaJson({ datos: { id: 1, nombre: 'Coordinación', rol: { nombre: 'coordinador', permisos } } });
      if (ruta === '/excel/importaciones') return respuestaJson({ datos: VISTA });
      if (ruta.endsWith('/confirmar')) {
        return respuestaJson({ datos: { ...VISTA, mensaje: 'Se importaron 2 ascensores, 14 puertas de piso y 1 cliente. 2 filas no se cargaron.' } });
      }
      return respuestaJson({ datos: [] });
    }),
  );
  render(
    <MemoryRouter>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <HerramientasExcel modulo="ascensores" alImportar={alImportar} />
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
  return { llamadas, alImportar };
}

describe('importar desde Excel', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('muestra la vista previa con errores y duplicados, e importa solo las filas válidas', async () => {
    const { llamadas, alImportar } = montar(['equipos.ver', 'datos.importar']);

    await userEvent.click(await screen.findByRole('button', { name: /importar desde excel/i }));
    const archivo = new File(['contenido'], 'carga.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    await userEvent.upload(screen.getByLabelText(/arrastre el archivo/i), archivo);

    expect(await screen.findByText(/Todavía no se ha guardado nada/)).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Errores encontrados' })).toHaveTextContent('Falta Paradas. Es obligatorio.');
    expect(screen.getByRole('region', { name: 'Duplicados detectados' })).toHaveTextContent('ya aparece en la fila 2');
    expect(screen.getByText(/Se crearán 14 puertas de piso/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Corregir y volver a cargar' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Descargar errores' })).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Importar solo las filas válidas' }));
    expect((await screen.findAllByText('Se importaron 2 ascensores, 14 puertas de piso y 1 cliente. 2 filas no se cargaron.')).length).toBeGreaterThan(0);
    expect(llamadas).toContain(`POST /excel/importaciones/${VISTA.id}/confirmar`);

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar' }));
    expect(alImportar).toHaveBeenCalled();
  });

  it('rechaza archivos que no son de Excel ni CSV', async () => {
    montar(['equipos.ver', 'datos.importar']);
    await userEvent.click(await screen.findByRole('button', { name: /importar desde excel/i }));
    await userEvent.upload(screen.getByLabelText(/arrastre el archivo/i), new File(['x'], 'foto.png', { type: 'image/png' }), { applyAccept: false });
    expect(await screen.findByText('Elija un archivo de Excel (.xlsx) o CSV.')).toBeInTheDocument();
  });

  it('sin permiso de importación solo ofrece exportar y la plantilla', async () => {
    montar(['equipos.ver']);
    expect(await screen.findByRole('button', { name: /exportar a excel/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /descargar plantilla vacía/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /importar desde excel/i })).not.toBeInTheDocument();
  });
});
