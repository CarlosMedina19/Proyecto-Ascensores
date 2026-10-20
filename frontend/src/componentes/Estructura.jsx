import { useCallback, useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import {
  Briefcase,
  Building2,
  CalendarDays,
  ChevronDown,
  CircleUser,
  ClipboardCheck,
  ClipboardList,
  FileText,
  HardHat,
  LayoutDashboard,
  LogOut,
  Menu,
  Moon,
  PanelLeftClose,
  PanelLeftOpen,
  ScrollText,
  Settings,
  ShieldCheck,
  Sun,
  Users,
} from 'lucide-react';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { useInactividad } from '../hooks/useInactividad';
import { useTema } from '../hooks/useTema';
import { autenticacion } from '../api/servicios';
import { TIPOS_EDIFICIO, iniciales, nombreRol } from '../utilidades/formato';
import { Modal } from './Basicos';
import CampanaNotificaciones from './CampanaNotificaciones';
import BuscadorGlobal from './BuscadorGlobal';
import { Marca } from './Marca';
import { RamaTipo } from './ArbolLateral';

/** Opciones del menú lateral y el permiso que requiere cada una (RF-002). */
function opcionesMenu(esCliente, esTecnico) {
  return [
    { grupo: 'Operación' },
    { ruta: '/inicio', texto: 'Panel', icono: LayoutDashboard },
    { ruta: '/ordenes', texto: esTecnico ? 'Mis órdenes' : esCliente ? 'Mis órdenes de servicio' : 'Órdenes de trabajo', icono: ClipboardCheck, permiso: 'ordenes.ver' },
    // Edificios y ascensores se llegan desde Clientes: por tipo en el submenú o desplegando cada cliente.
    {
      ruta: '/clientes',
      texto: esCliente ? 'Mi empresa' : 'Clientes',
      icono: Briefcase,
      permiso: 'clientes.ver',
      hijos: Object.entries(TIPOS_EDIFICIO).map(([tipo, t]) => ({ tipo, ruta: t.ruta, texto: t.plural, icono: Building2, permiso: 'edificios.ver' })),
      // Mantienen el menú abierto y resaltado al navegar por edificios y ascensores.
      relacionadas: ['/edificios', '/equipos'],
    },
    { grupo: 'Mantenimiento' },
    { ruta: '/calendario', texto: 'Calendario', icono: CalendarDays, permiso: 'mantenimientos.ver' },
    { ruta: '/tecnicos', texto: 'Técnicos', icono: HardHat, permiso: 'tecnicos.ver' },
    { ruta: '/contratos', texto: esCliente ? 'Mis contratos' : 'Contratos', icono: FileText, permiso: 'contratos.ver' },
    { ruta: '/planes', texto: 'Planes y precios', icono: ClipboardList, permiso: 'planes.ver' },
    { grupo: 'Administración' },
    { ruta: '/usuarios', texto: 'Usuarios', icono: Users, permiso: 'usuarios.ver' },
    { ruta: '/roles', texto: 'Roles y permisos', icono: ShieldCheck, permiso: 'roles.ver' },
    { ruta: '/auditoria', texto: 'Auditoría', icono: ScrollText, permiso: 'registros_auditoria.ver' },
    { ruta: '/configuracion', texto: 'Configuración', icono: Settings, permiso: 'configuracion.editar' },
    { grupo: 'Cuenta' },
    { ruta: '/perfil', texto: 'Mi perfil', icono: CircleUser },
  ];
}

export default function Estructura() {
  const { usuario, tienePermiso, esCliente, esTecnico, cerrarSesion, minutosInactividad } = useAutenticacion();
  const { tema, alternar } = useTema();
  const [menuAbierto, setMenuAbierto] = useState(false);
  // Menú contraído a solo íconos (pantallas anchas); se recuerda entre visitas.
  const [contraido, setContraido] = useState(() => {
    try {
      return localStorage.getItem('sigma.menu-contraido') === '1';
    } catch {
      return false;
    }
  });
  const alternarContraido = () =>
    setContraido((actual) => {
      try {
        localStorage.setItem('sigma.menu-contraido', actual ? '0' : '1');
      } catch {
        // Sin almacenamiento local la preferencia dura solo esta visita.
      }
      return !actual;
    });
  const ubicacion = useLocation();
  const navegar = useNavigate();

  useEffect(() => setMenuAbierto(false), [ubicacion.pathname]);

  // Mantiene vivo el token mientras el usuario trabaja (el servidor cuenta la inactividad por solicitudes).
  const alPing = useCallback(() => {
    autenticacion.perfil().catch(() => {});
  }, []);

  const alExpirar = useCallback(async () => {
    await cerrarSesion('Su sesión se cerró por inactividad.');
    navegar('/iniciar-sesion', { replace: true });
  }, [cerrarSesion, navegar]);

  const { segundosRestantes, mantenerSesion } = useInactividad({ minutos: minutosInactividad, alPing, alExpirar });

  // Oculta los grupos que quedan vacíos por falta de permisos.
  const visibles = opcionesMenu(esCliente, esTecnico).filter((o) => o.grupo || !o.permiso || tienePermiso(o.permiso));
  const menu = visibles.filter((o, i) => !o.grupo || (visibles[i + 1] && !visibles[i + 1].grupo));
  const ruta = ubicacion.pathname;
  const hijoActual = menu.flatMap((o) => o.hijos || []).find((h) => ruta.startsWith(h.ruta));
  const actual =
    (hijoActual && ruta.endsWith('/ascensores') && { texto: `Ascensores · ${hijoActual.texto}` }) ||
    hijoActual ||
    (ruta.startsWith('/edificios') && { texto: 'Edificios' }) ||
    (ruta.startsWith('/equipos') && { texto: 'Ascensores' }) ||
    menu.find((o) => o.ruta && ruta.startsWith(o.ruta));
  const dentroDe = (o) => ruta.startsWith(o.ruta) || (o.relacionadas || []).some((r) => ruta.startsWith(r));
  const [desplegados, setDesplegados] = useState({});
  const estaDesplegado = (o) => desplegados[o.ruta] ?? dentroDe(o);

  async function salir() {
    await cerrarSesion();
    navegar('/iniciar-sesion', { replace: true });
  }

  return (
    <div className={`estructura${contraido ? ' estructura--contraida' : ''}`}>
      <aside className={`lateral${menuAbierto ? ' abierto' : ''}`} aria-label="Menú principal">
        <Marca />
        <nav className="lateral__navegacion">
          {menu.map((opcion) =>
            opcion.grupo ? (
              <div key={opcion.grupo} className="lateral__grupo">
                {opcion.grupo}
              </div>
            ) : opcion.hijos ? (
              <div key={opcion.ruta} className="lateral__rama">
                <div className="lateral__fila">
                  <NavLink
                    to={opcion.ruta}
                    end
                    className={({ isActive }) => `lateral__enlace${isActive || (dentroDe(opcion) && !hijoActual) ? ' activo' : ''}`}
                    title={contraido ? opcion.texto : undefined}
                  >
                    <opcion.icono size={18} aria-hidden="true" />
                    <span className="lateral__texto">{opcion.texto}</span>
                  </NavLink>
                  {opcion.hijos.some((h) => tienePermiso(h.permiso)) && (
                    <button
                      type="button"
                      className={`lateral__desplegar${estaDesplegado(opcion) ? ' abierto' : ''}`}
                      aria-expanded={estaDesplegado(opcion)}
                      aria-label={`${estaDesplegado(opcion) ? 'Contraer' : 'Desplegar'} ${opcion.texto}`}
                      onClick={() => setDesplegados((d) => ({ ...d, [opcion.ruta]: !estaDesplegado(opcion) }))}
                    >
                      <ChevronDown size={16} aria-hidden="true" />
                    </button>
                  )}
                </div>
                {estaDesplegado(opcion) && (
                  <div className="lateral__hijos">
                    {opcion.hijos
                      .filter((h) => tienePermiso(h.permiso))
                      .map((hijo) => (
                        <RamaTipo key={hijo.ruta} ruta={hijo.ruta} texto={hijo.texto} />
                      ))}
                  </div>
                )}
              </div>
            ) : (
              <NavLink
                key={opcion.ruta}
                to={opcion.ruta}
                className={({ isActive }) => `lateral__enlace${isActive ? ' activo' : ''}`}
                title={contraido ? opcion.texto : undefined}
              >
                <opcion.icono size={18} aria-hidden="true" />
                <span className="lateral__texto">{opcion.texto}</span>
              </NavLink>
            ),
          )}
        </nav>
        <div className="lateral__pie">
          <button type="button" className="lateral__contraer" onClick={alternarContraido} aria-expanded={!contraido} aria-label={contraido ? 'Expandir menú' : 'Contraer menú'}>
            {contraido ? <PanelLeftOpen size={16} aria-hidden="true" /> : <PanelLeftClose size={16} aria-hidden="true" />}
            <span>{contraido ? 'Expandir menú' : 'Contraer menú'}</span>
          </button>
          <div className="lateral__version">
            <span className="en-vivo" aria-hidden="true" />
            Conectado · Fase 4
          </div>
          <div className="lateral__usuario">
            <div className="avatar" aria-hidden="true">
              {iniciales(usuario?.nombre)}
            </div>
            <div style={{ minWidth: 0, flex: 1 }}>
              <b>{usuario?.nombre}</b>
              <small>{usuario?.cliente ? usuario.cliente.nombre : nombreRol(usuario?.rol?.nombre)}</small>
            </div>
            <button type="button" className="boton-icono" onClick={salir} title="Cerrar sesión" aria-label="Cerrar sesión" style={{ border: 0, background: 'none' }}>
              <LogOut size={18} />
            </button>
          </div>
        </div>
      </aside>

      <div className="principal">
        <header className="encabezado">
          <button type="button" className="boton-icono encabezado__menu" onClick={() => setMenuAbierto((v) => !v)} aria-label="Abrir menú">
            <Menu size={18} />
          </button>
          <div className="migas-encabezado">
            SIGMA <span className="texto-suave">/</span> <b>{actual?.texto || 'Inicio'}</b>
          </div>
          <span style={{ flex: 1 }} />
          <BuscadorGlobal />
          <CampanaNotificaciones />
          <button
            type="button"
            className="boton-icono"
            onClick={alternar}
            aria-label={tema === 'oscuro' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            title={tema === 'oscuro' ? 'Modo claro' : 'Modo oscuro'}
          >
            {tema === 'oscuro' ? <Sun size={18} /> : <Moon size={18} />}
          </button>
        </header>

        <main className="contenido" key={ubicacion.pathname}>
          <Outlet />
        </main>
      </div>

      <Modal
        abierto={segundosRestantes !== null}
        titulo="¿Sigue ahí?"
        pie={
          <>
            <button type="button" className="boton boton--secundario" onClick={alExpirar}>
              Cerrar sesión
            </button>
            <button type="button" className="boton" onClick={mantenerSesion}>
              Mantener sesión activa
            </button>
          </>
        }
      >
        Por seguridad, su sesión se cerrará en <strong className="tabular">{segundosRestantes}</strong> segundos por inactividad.
      </Modal>
    </div>
  );
}
