import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Lock, Save } from 'lucide-react';
import { roles } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { Casilla } from '../../componentes/Campos';
import { nombreRol } from '../../utilidades/formato';

// Deben coincidir con backend/config/sigma.php (permisos_exclusivos_administrador).
const PERMISOS_EXCLUSIVOS = new Set([
  'roles.crear',
  'roles.editar',
  'roles.permisos.editar',
  'permisos.ver',
  'usuarios.crear',
  'usuarios.editar',
  'registros_auditoria.ver',
  'configuracion.editar',
  'tecnicos.crear',
]);

const NOMBRES_GRUPO = {
  resumen: 'Resumen',
  roles: 'Roles',
  permisos: 'Permisos',
  usuarios: 'Usuarios',
  registros_auditoria: 'Auditoría',
  clientes: 'Clientes',
  edificios: 'Edificios',
  equipos: 'Equipos e historial',
  planes: 'Planes de mantenimiento',
  precios: 'Precios',
  asignaciones: 'Planes de los ascensores',
  mantenimientos: 'Mantenimientos programados',
  contratos: 'Contratos',
  configuracion: 'Configuración',
  tecnicos: 'Técnicos',
  ordenes: 'Órdenes de trabajo',
};

export default function DetalleRol() {
  const { id } = useParams();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [rol, setRol] = useState(null);
  const [catalogo, setCatalogo] = useState([]);
  const [seleccion, setSeleccion] = useState(new Set());
  const [error, setError] = useState('');
  const [guardando, setGuardando] = useState(false);

  useEffect(() => {
    Promise.all([roles.obtener(id), tienePermiso('permisos.ver') ? roles.permisos() : Promise.resolve({ datos: [] })])
      .then(([respuestaRol, respuestaPermisos]) => {
        setRol(respuestaRol.datos);
        setSeleccion(new Set(respuestaRol.datos.permisos));
        setCatalogo(
          respuestaPermisos.datos.length
            ? respuestaPermisos.datos
            : respuestaRol.datos.permisos.map((nombre) => ({ nombre, descripcion: nombre })),
        );
      })
      .catch((e) => setError(e.message));
  }, [id, tienePermiso]);

  const grupos = useMemo(() => {
    const resultado = {};
    catalogo.forEach((permiso) => {
      const grupo = permiso.nombre.split('.')[0];
      (resultado[grupo] ||= []).push(permiso);
    });
    return resultado;
  }, [catalogo]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!rol) return <Cargando />;

  const esAdministrador = rol.nombre === 'administrador';
  const editable = !esAdministrador && tienePermiso('roles.permisos.editar');

  function alternar(nombre, marcado) {
    setSeleccion((actual) => {
      const nueva = new Set(actual);
      if (marcado) nueva.add(nombre);
      else nueva.delete(nombre);
      return nueva;
    });
  }

  async function guardar() {
    setGuardando(true);
    try {
      const respuesta = await roles.sincronizarPermisos(id, [...seleccion]);
      setRol(respuesta.datos);
      notificar('Permisos actualizados.');
    } catch (e) {
      notificar(e.errores?.permisos?.[0] || e.message, 'error');
    } finally {
      setGuardando(false);
    }
  }

  return (
    <>
      <EncabezadoPagina
        titulo={nombreRol(rol.nombre)}
        migas={<Link to="/roles">Roles y permisos</Link>}
        descripcion={`${rol.descripcion || ''} · ${rol.usuarios_count ?? 0} usuario(s)`}
      >
        {editable && (
          <button type="button" className="boton" onClick={guardar} disabled={guardando}>
            <Save size={16} /> {guardando ? 'Guardando…' : 'Guardar permisos'}
          </button>
        )}
      </EncabezadoPagina>

      {esAdministrador && (
        <div style={{ marginBottom: 16 }}>
          <Alerta tipo="info">El rol administrador tiene todos los permisos y no se puede modificar, para evitar perder el acceso al sistema.</Alerta>
        </div>
      )}

      <Tarjeta titulo={`Permisos (${seleccion.size})`}>
        <div className="permisos">
          {Object.entries(grupos).map(([grupo, permisos]) => (
            <div key={grupo} className="permisos__grupo">
              <div className="permisos__titulo">{NOMBRES_GRUPO[grupo] || grupo}</div>
              <div className="permisos__lista">
                {permisos.map((permiso) => {
                  const exclusivo = PERMISOS_EXCLUSIVOS.has(permiso.nombre) && !esAdministrador;
                  return (
                    <Casilla
                      key={permiso.nombre}
                      etiqueta={
                        <span title={permiso.nombre}>
                          {permiso.descripcion}
                          {exclusivo && <Lock size={12} style={{ marginLeft: 4, verticalAlign: -1 }} aria-label="Exclusivo del administrador" />}
                        </span>
                      }
                      valor={seleccion.has(permiso.nombre)}
                      alCambiar={(marcado) => alternar(permiso.nombre, marcado)}
                      disabled={!editable || exclusivo}
                    />
                  );
                })}
              </div>
            </div>
          ))}
        </div>
        {!esAdministrador && (
          <p className="texto-pequeno texto-suave" style={{ marginBottom: 0 }}>
            <Lock size={12} style={{ verticalAlign: -1 }} /> Gestionar usuarios, roles y consultar la auditoría es exclusivo del administrador.
          </p>
        )}
      </Tarjeta>
    </>
  );
}
