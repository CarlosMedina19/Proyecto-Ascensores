import { useCallback, useEffect, useState } from 'react';
import { Check, Pencil, Plus, Power, Trash2, X } from 'lucide-react';
import { catalogos } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, Confirmacion, Insignia, Tarjeta } from '../../componentes/Basicos';
import { olvidarTiposAscensor } from '../../hooks/useTiposAscensor';

const SECCIONES = [
  {
    clave: 'especialidades',
    datos: 'especialidades',
    titulo: 'Especialidades de los técnicos',
    descripcion: 'Se eligen al registrar un técnico. Una especialidad en uso no se elimina: desactívela para que no se ofrezca más.',
    nuevo: 'Nueva especialidad, por ejemplo Puertas automáticas',
    uso: (n) => `${n} técnico${n === 1 ? '' : 's'}`,
  },
  {
    clave: 'tipos-ascensor',
    datos: 'tipos_ascensor',
    titulo: 'Tipos de ascensor',
    descripcion: 'Clasifican los ascensores (pasajeros, carga, camillero…). Un tipo en uso no se elimina: desactívelo.',
    nuevo: 'Nuevo tipo, por ejemplo Escalera eléctrica',
    uso: (n) => `${n} ascensor${n === 1 ? '' : 'es'}`,
  },
];

/** Agregar, renombrar, activar o eliminar los valores de los catálogos. */
export default function Catalogos() {
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');

  const cargar = useCallback(
    () =>
      catalogos
        .obtener()
        .then((r) => setDatos(r.datos))
        .catch((e) => setError(e.message)),
    [],
  );

  useEffect(() => {
    cargar();
  }, [cargar]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!datos) return <Cargando />;

  return (
    <div className="rejilla rejilla--2" style={{ marginTop: 18 }}>
      {SECCIONES.map((s) => (
        <ListaCatalogo key={s.clave} seccion={s} elementos={datos[s.datos]} alCambiar={cargar} />
      ))}
    </div>
  );
}

function ListaCatalogo({ seccion, elementos, alCambiar }) {
  const notificar = useNotificar();
  const [nuevo, setNuevo] = useState('');
  const [editando, setEditando] = useState(null); // { id, nombre }
  const [eliminar, setEliminar] = useState(null);
  const [ocupado, setOcupado] = useState(false);
  const [errorCampo, setErrorCampo] = useState('');

  async function ejecutar(accion, exito) {
    setOcupado(true);
    setErrorCampo('');
    try {
      const r = await accion();
      notificar(r?.mensaje || exito);
      if (seccion.clave === 'tipos-ascensor') olvidarTiposAscensor();
      await alCambiar();
      return true;
    } catch (e) {
      const detalle = Object.values(e.errores || {})[0]?.[0] || e.message;
      setErrorCampo(detalle);
      notificar(detalle, 'error');
      return false;
    } finally {
      setOcupado(false);
    }
  }

  async function agregar(evento) {
    evento.preventDefault();
    if (!nuevo.trim()) return;
    if (await ejecutar(() => catalogos.agregar(seccion.clave, nuevo.trim()), 'Agregado.')) setNuevo('');
  }

  async function renombrar(evento) {
    evento.preventDefault();
    if (await ejecutar(() => catalogos.actualizar(seccion.clave, editando.id, { nombre: editando.nombre.trim() }), 'Nombre actualizado.')) setEditando(null);
  }

  return (
    <Tarjeta titulo={seccion.titulo}>
      <p className="texto-pequeno texto-suave" style={{ marginTop: 0 }}>
        {seccion.descripcion}
      </p>
      <form className="catalogo__nuevo" onSubmit={agregar}>
        <input className="campo__control" value={nuevo} onChange={(e) => setNuevo(e.target.value)} placeholder={seccion.nuevo} aria-label={`Agregar a ${seccion.titulo}`} maxLength={100} />
        <button type="submit" className="boton" disabled={ocupado || !nuevo.trim()}>
          <Plus size={16} aria-hidden="true" /> Agregar
        </button>
      </form>
      {errorCampo && <span className="campo__error">{errorCampo}</span>}
      <ul className="catalogo">
        {elementos.map((e) => (
          <li key={e.id} className={`catalogo__fila${e.activo ? '' : ' catalogo__fila--inactiva'}`}>
            {editando?.id === e.id ? (
              <form className="catalogo__editar" onSubmit={renombrar}>
                <input className="campo__control" value={editando.nombre} onChange={(ev) => setEditando({ ...editando, nombre: ev.target.value })} aria-label="Nuevo nombre" autoFocus maxLength={100} />
                <button type="submit" className="boton boton--fantasma" disabled={ocupado} aria-label="Guardar nombre">
                  <Check size={16} />
                </button>
                <button type="button" className="boton boton--fantasma" onClick={() => setEditando(null)} aria-label="Cancelar">
                  <X size={16} />
                </button>
              </form>
            ) : (
              <>
                <span className="catalogo__nombre">
                  {e.nombre}
                  <small>{e.en_uso ? seccion.uso(e.en_uso) : 'Sin uso'}</small>
                </span>
                {!e.activo && <Insignia tipo="neutro">Inactivo</Insignia>}
                <span className="catalogo__acciones">
                  <button type="button" className="boton boton--fantasma" title="Cambiar nombre" aria-label={`Cambiar nombre de ${e.nombre}`} onClick={() => setEditando({ id: e.id, nombre: e.nombre })}>
                    <Pencil size={15} />
                  </button>
                  <button
                    type="button"
                    className="boton boton--fantasma"
                    title={e.activo ? 'Desactivar' : 'Activar'}
                    aria-label={`${e.activo ? 'Desactivar' : 'Activar'} ${e.nombre}`}
                    disabled={ocupado}
                    onClick={() => ejecutar(() => catalogos.actualizar(seccion.clave, e.id, { activo: !e.activo }), e.activo ? 'Desactivado.' : 'Activado.')}
                  >
                    <Power size={15} />
                  </button>
                  <button type="button" className="boton boton--fantasma" title="Eliminar" aria-label={`Eliminar ${e.nombre}`} disabled={ocupado || e.en_uso > 0} onClick={() => setEliminar(e)}>
                    <Trash2 size={15} />
                  </button>
                </span>
              </>
            )}
          </li>
        ))}
      </ul>
      <Confirmacion
        abierto={eliminar !== null}
        titulo={`Eliminar ${eliminar?.nombre ?? ''}`}
        mensaje="Se quitará del catálogo. Esta acción no se puede deshacer."
        textoConfirmar="Eliminar"
        peligro
        procesando={ocupado}
        alConfirmar={async () => {
          await ejecutar(() => catalogos.eliminar(seccion.clave, eliminar.id), 'Eliminado.');
          setEliminar(null);
        }}
        alCerrar={() => setEliminar(null)}
      />
    </Tarjeta>
  );
}
