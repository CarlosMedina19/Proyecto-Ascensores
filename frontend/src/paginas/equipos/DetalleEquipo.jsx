import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { equipos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Confirmacion, Datos, EncabezadoPagina, EstadoVacio, Insignia, Modal, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector } from '../../componentes/Campos';
import { ESTADOS_EQUIPO, EVENTOS_HISTORIAL, fecha, fechaHora, numero, velocidad } from '../../utilidades/formato';
import CorteAscensor from '../../componentes/CorteAscensor';
import LineaTiempo from '../../componentes/LineaTiempo';
import PlanDelEquipo from './PlanDelEquipo';
import OrdenesDelEquipo from './OrdenesDelEquipo';
import { GaleriaFotos } from '../../componentes/FotosEquipo';

// Sin planilla, el esquema refleja el estado general del equipo; con planilla, el resultado de cada zona.
const ESTADO_A_ZONA = { activo: 'B', mantenimiento: 'R', inactivo: 'P' };

function ModalEvento({ equipoId, abierto, alCerrar, alGuardar }) {
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ evento: '', descripcion: '' });

  async function guardar() {
    const respuesta = await enviar(() => equipos.agregarHistorial(equipoId, datos));
    if (respuesta) {
      setDatos({ evento: '', descripcion: '' });
      alGuardar();
    }
  }

  return (
    <Modal
      abierto={abierto}
      titulo="Registrar evento en el historial"
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={guardar} disabled={enviando}>
            {enviando ? 'Guardando…' : 'Registrar'}
          </button>
        </>
      }
    >
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <CampoSelector etiqueta="Tipo de evento" obligatorio valor={datos.evento} alCambiar={cambiar('evento')} error={error('evento')} opciones={EVENTOS_HISTORIAL} />
        <CampoArea etiqueta="Descripción" obligatorio valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} />
        <span className="campo__ayuda">El historial no se puede modificar ni eliminar; para corregir, registre un nuevo evento.</span>
      </div>
    </Modal>
  );
}

export default function DetalleEquipo() {
  const { id } = useParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [equipo, setEquipo] = useState(null);
  const [historial, setHistorial] = useState(null);
  const [error, setError] = useState('');
  const [modalEvento, setModalEvento] = useState(false);
  const [confirmarBaja, setConfirmarBaja] = useState(false);
  const [procesando, setProcesando] = useState(false);

  const puedeVerHistorial = tienePermiso('equipos.historial.ver');

  const cargar = useCallback(() => {
    equipos
      .obtener(id)
      .then((r) => setEquipo(r.datos))
      .catch((e) => setError(e.message));
    if (puedeVerHistorial) {
      equipos
        .historial(id)
        .then((r) => setHistorial(r.datos))
        .catch(() => setHistorial([]));
    }
  }, [id, puedeVerHistorial]);

  useEffect(cargar, [cargar]);

  async function cambiarEstado(estado) {
    try {
      await equipos.actualizar(id, { estado });
      notificar('Estado actualizado.');
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    }
  }

  async function darDeBaja() {
    setProcesando(true);
    try {
      await equipos.eliminar(id);
      notificar('Ascensor dado de baja.');
      navegar('/equipos', { replace: true });
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setProcesando(false);
      setConfirmarBaja(false);
    }
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!equipo) return <Cargando />;

  const ficha = equipo.ascensor || {};
  const estado = ESTADOS_EQUIPO[equipo.estado];
  const zona = ESTADO_A_ZONA[equipo.estado] || 'P';
  // Fase 4: con una planilla completa, cada zona toma el estado más grave de su sección.
  const revision = equipo.ultima_revision;
  const seccion = (clave) => revision?.secciones?.[clave] || 'P';
  const zonas = revision
    ? {
        maquina: seccion('cuarto_maquinas'),
        cabina: seccion('cabina'),
        contrapeso: seccion('contrapeso'),
        puertasCabina: seccion('puertas'),
        hueco: seccion('hueco'),
        foso: seccion('foso'),
      }
    : { maquina: zona, cabina: zona, contrapeso: zona, puertasCabina: zona, hueco: zona, foso: zona };
  const totalPuertasPiso = ficha.puertas_piso ?? ficha.paradas ?? 0;

  return (
    <>
      <EncabezadoPagina
        titulo={`Ascensor ${equipo.codigo}`}
        migas={<Link to="/equipos">Ascensores</Link>}
        descripcion={
          <>
            <Link to={`/edificios/${equipo.edificio_id}`}>{equipo.edificio?.nombre}</Link>
            {equipo.edificio?.cliente && ` · ${equipo.edificio.cliente.nombre}`} · <Insignia tipo={estado?.tipo}>{estado?.texto}</Insignia>
          </>
        }
      >
        {tienePermiso('equipos.editar') && (
          <select
            className="campo__control"
            style={{ width: 'auto' }}
            value={equipo.estado}
            onChange={(e) => cambiarEstado(e.target.value)}
            aria-label="Cambiar estado"
          >
            {Object.entries(ESTADOS_EQUIPO).map(([valor, { texto }]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
          </select>
        )}
        {tienePermiso('equipos.eliminar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setConfirmarBaja(true)}>
            <Trash2 size={16} /> Dar de baja
          </button>
        )}
        {tienePermiso('equipos.editar') && (
          <Link to={`/equipos/${id}/editar`} className="boton">
            <Pencil size={16} /> Editar
          </Link>
        )}
      </EncabezadoPagina>

      <div className="rejilla rejilla--2" style={{ alignItems: 'start' }}>
        <div className="rejilla">
          <Tarjeta titulo="Datos del ascensor">
            <Datos
              elementos={[
                ['Nombre', equipo.nombre],
                ['Marca', equipo.marca],
                ['Modelo', equipo.modelo],
                ['Técnico asignado', equipo.tecnico?.nombre],
                ['Número de serie', equipo.numero_serie],
                ['Ubicación', equipo.ubicacion],
                ['Fecha de instalación', fecha(equipo.fecha_instalacion)],
                ['Registrado', fechaHora(equipo.created_at)],
              ]}
            />
          </Tarjeta>
          <Tarjeta titulo="Fotos">
            <GaleriaFotos
              equipoId={equipo.id}
              fotos={equipo.fotos || []}
              editable={tienePermiso('equipos.editar') || tienePermiso('equipos.crear')}
              notificar={notificar}
              alCambiar={cargar}
            />
          </Tarjeta>
          <Tarjeta titulo="Ficha técnica">
            <Datos
              elementos={[
                ['Capacidad', [numero(ficha.capacidad_kg, ' kg'), ficha.capacidad_personas ? `${ficha.capacidad_personas} personas` : null].filter(Boolean).join(' · ')],
                ['Velocidad', velocidad(ficha.velocidad_mpm)],
                ['Paradas', ficha.paradas],
                ['Accionamiento', ficha.tipo_traccion],
                ['Motor', ficha.motor],
                ['Controlador', ficha.controlador],
                ['Tipo de puerta', ficha.tipo_puerta],
                ['Operador de puertas', ficha.operador_puertas],
                ['Puertas de cabina', ficha.puertas_cabina],
                ['Puertas de piso', ficha.puertas_piso],
              ]}
            />
            {totalPuertasPiso > 0 && (
              <>
                <p className="etiqueta-superior" style={{ marginTop: 16 }}>
                  Puertas de piso del ascensor
                </p>
                <div className="puertas">
                  {equipo.puertas_piso?.length
                    ? equipo.puertas_piso.map((p) => (
                        <span key={p.id} className={`estado-${p.estado}`} title={`Piso ${p.piso} · ${p.tipo === 'manual' ? 'manual' : 'automática'} · ${{ B: 'Bueno', R: 'Regular', M: 'Malo', P: 'Pendiente' }[p.estado]}${p.observacion ? ` · ${p.observacion}` : ''}`}>
                          P{p.piso}
                        </span>
                      ))
                    : Array.from({ length: Math.min(totalPuertasPiso, 60) }, (_, i) => <span key={i}>P{i + 1}</span>)}
                </div>
              </>
            )}
            {ficha.especificaciones_tecnicas && (
              <>
                <div className="separador" />
                <div className="texto-pequeno texto-suave">Especificaciones adicionales</div>
                <p style={{ margin: '4px 0 0', whiteSpace: 'pre-line' }}>{ficha.especificaciones_tecnicas}</p>
              </>
            )}
          </Tarjeta>
        </div>

        <div className="rejilla">
        <PlanDelEquipo equipo={equipo} alCambiar={cargar} />
        {tienePermiso('ordenes.ver') && <OrdenesDelEquipo equipoId={equipo.id} />}
        <Tarjeta titulo="Esquema del ascensor" acciones={<Insignia tipo={estado?.tipo}>{estado?.texto}</Insignia>}>
          <div style={{ maxWidth: 260, margin: '0 auto' }}>
            <CorteAscensor
              pisos={ficha.paradas || 7}
              zonas={zonas}
              puertasPiso={Array(ficha.paradas || 7).fill(zonas.puertasCabina)}
              etiqueta={`Esquema del ascensor ${equipo.codigo} con ${ficha.paradas || 0} paradas`}
            />
          </div>
          {revision ? (
            <p className="texto-pequeno texto-suave" style={{ marginBottom: 0 }}>
              Estado de cada zona según la última planilla: <Link to={`/ordenes/${revision.orden_id}`}>{revision.orden_numero}</Link> del{' '}
              {fechaHora(revision.fecha)}. Verde: bueno; ámbar: regular; rojo: malo.
            </p>
          ) : (
            <p className="texto-pequeno texto-suave" style={{ marginBottom: 0 }}>
              Sin planilla de mantenimiento todavía: el esquema muestra el estado general del ascensor. Al finalizar el primer preventivo,
              cada zona toma el color de su resultado.
            </p>
          )}
        </Tarjeta>
        {puedeVerHistorial && (
          <Tarjeta
            titulo="Historial del ascensor"
            acciones={
              tienePermiso('equipos.historial.crear') && (
                <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModalEvento(true)}>
                  <Plus size={15} /> Registrar evento
                </button>
              )
            }
          >
            {historial === null ? (
              <Cargando />
            ) : historial.length ? (
              <LineaTiempo eventos={historial} />
            ) : (
              <EstadoVacio titulo="Sin eventos registrados" />
            )}
          </Tarjeta>
        )}
        </div>
      </div>

      <ModalEvento
        equipoId={id}
        abierto={modalEvento}
        alCerrar={() => setModalEvento(false)}
        alGuardar={() => {
          setModalEvento(false);
          notificar('Evento registrado en el historial.');
          cargar();
        }}
      />
      <Confirmacion
        abierto={confirmarBaja}
        titulo="Dar de baja el ascensor"
        mensaje="El ascensor dejará de aparecer en los listados. Su historial se conserva."
        textoConfirmar="Dar de baja"
        peligro
        procesando={procesando}
        alConfirmar={darDeBaja}
        alCerrar={() => setConfirmarBaja(false)}
      />
    </>
  );
}
