import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProveedorAutenticacion } from '../contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from '../contexto/NotificacionesContexto';
import { guardarToken } from '../api/cliente';
import BuscadorGlobal from '../componentes/BuscadorGlobal';
import { validarFotos } from '../componentes/FotosEquipo';
import Calendario from '../paginas/mantenimientos/Calendario';

function respuestaJson(cuerpo, estado = 200) {
  return Promise.resolve({ ok: estado < 400, status: estado, json: () => Promise.resolve(cuerpo) });
}

function simularApi(rutas) {
  vi.stubGlobal(
    'fetch',
    vi.fn((url) => {
      const direccion = new URL(url);
      const respuesta = rutas[direccion.pathname.replace('/api/v1', '')];
      return respuestaJson(typeof respuesta === 'function' ? respuesta(direccion) : (respuesta ?? { datos: [] }));
    }),
  );
}

function montar(elemento, ruta = '/') {
  return render(
    <MemoryRouter initialEntries={[ruta]}>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <Routes>
            <Route path="*" element={elemento} />
            <Route path="/equipos/:id" element={<div>detalle del ascensor</div>} />
          </Routes>
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </MemoryRouter>,
  );
}

const archivo = (nombre, tipo, tamano = 1000) => ({ name: nombre, type: tipo, size: tamano });

describe('fotos del ascensor', () => {
  it('acepta hasta 3 imágenes JPG, PNG o WEBP de máximo 5 MB', () => {
    expect(validarFotos([archivo('a.jpg', 'image/jpeg'), archivo('b.png', 'image/png')], 3)).toBe('');
    expect(validarFotos([archivo('a.jpg', 'image/jpeg'), archivo('b.jpg', 'image/jpeg')], 1)).toMatch(/máximo 1 foto/);
    expect(validarFotos([archivo('manual.pdf', 'application/pdf')], 3)).toMatch(/no es una imagen/);
    expect(validarFotos([archivo('grande.jpg', 'image/jpeg', 6 * 1024 * 1024)], 3)).toMatch(/más de 5 MB/);
  });
});

describe('buscador global', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('Ctrl+K lo enfoca y Enter abre el resultado elegido', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Admin', rol: { nombre: 'administrador', permisos: [] } } },
      '/buscar': (url) => ({
        datos: {
          ascensores: url.searchParams.get('q') === 'asc' ? [{ id: 27, titulo: 'ASC-001', detalle: 'Torre A', enlace: '/equipos/27' }] : [],
          clientes: [],
        },
      }),
    });
    montar(<BuscadorGlobal />);

    await userEvent.keyboard('{Control>}k{/Control}');
    const entrada = screen.getByRole('combobox', { name: 'Buscar en SIGMA' });
    expect(entrada).toHaveFocus();

    await userEvent.type(entrada, 'asc');
    const opcion = await screen.findByRole('option', { name: /ASC-001/ });
    expect(within(opcion).getByText('Torre A')).toBeInTheDocument();

    await userEvent.keyboard('{Enter}');
    expect(await screen.findByText('detalle del ascensor')).toBeInTheDocument();
  });
});

describe('calendario por horas', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    guardarToken(null);
  });

  it('abre la semana indicada en la dirección y ubica la visita a su hora', async () => {
    guardarToken('token');
    simularApi({
      '/perfil': { datos: { id: 1, nombre: 'Coordinador', rol: { nombre: 'coordinador', permisos: ['mantenimientos.ver'] } } },
      '/festivos': { datos: [{ fecha: '2026-11-02', nombre: 'Todos los Santos' }] },
      '/mantenimientos': {
        datos: [
          {
            id: 4,
            equipo_id: 30,
            equipo: { id: 30, codigo: 'ASC-004', edificio: { id: 2, nombre: 'Torre B' } },
            plan: { id: 3, nombre: 'Plan Premium' },
            fecha_programada: '2026-11-03',
            fecha_teorica: '2026-11-01',
            hora: '10:30',
            precio: '950000.00',
            estado: 'programado',
          },
        ],
      },
    });
    montar(<Calendario />, '/calendario?fecha=2026-11-03');

    const visita = await screen.findByRole('button', { name: /ASC-004 · Torre B/ });
    // 10:30 es 4,5 horas después de las 6:00 → 270 px con 60 px por hora.
    expect(visita).toHaveStyle({ top: '270px' });
    expect(screen.getByText('Festivo')).toBeInTheDocument();
    expect(screen.getByText('Domingo 8 sin programación')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Mes' }));
    expect(await screen.findByText('Todos los Santos')).toBeInTheDocument();
  });
});
