import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ImagePlus, ShieldAlert, Trash2, Wrench } from 'lucide-react';
import { planilla as servicioPlanilla } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, Insignia, Tarjeta } from '../../componentes/Basicos';
import { ImagenProtegida, validarFotos } from '../../componentes/FotosEquipo';

const SECCIONES = {
  cuarto_maquinas: 'Cuarto de máquinas',
  cabina: 'Cabina',
  contrapeso: 'Contrapeso',
  puertas: 'Puertas',
  hueco: 'Hueco y recorrido',
  foso: 'Foso',
};
const RESULTADOS = [
  { valor: 'INS', texto: 'INS' },
  { valor: 'EST', texto: 'EST' },
  { valor: 'NA', texto: 'N.A.' },
];
const ESTADOS = [
  { valor: 'B', texto: 'B', titulo: 'Bueno' },
  { valor: 'R', texto: 'R', titulo: 'Regular' },
  { valor: 'M', texto: 'M', titulo: 'Malo' },
];
const MAXIMO_FOTOS = 20;

function peorEstado(estados) {
  if (estados.includes('M')) return 'M';
  if (estados.includes('R')) return 'R';
  return estados.length ? 'B' : null;
}

/**
 * RF-030 a RF-038: planilla digital del preventivo. El técnico asignado la diligencia con la orden
 * en proceso; los demás la consultan.
 */
export default function PlanillaOrden({ orden, alCambiar, notificar }) {
  const { tienePermiso } = useAutenticacion();
  const editable = Boolean(orden.permisos?.ejecutar) && orden.estado === 'en_proceso';
  const puedeCrearCorrectivo = (orden.permisos?.ejecutar || tienePermiso('ordenes.crear')) && orden.estado !== 'cancelada';

  const [catalogo, setCatalogo] = useState(null);
  const [guardados, setGuardados] = useState({});
  const [cambios, setCambios] = useState({});
  const [recomendaciones, setRecomendaciones] = useState('');
  const [recomendacionesGuardadas, setRecomendacionesGuardadas] = useState('');
  const [fotos, setFotos] = useState([]);
  const [seccion, setSeccion] = useState('cuarto_maquinas');
  const [error, setError] = useState('');
  const [errores, setErrores] = useState({});
  const [guardando, setGuardando] = useState(false);
  const [creando, setCreando] = useState(null);

  const aplicar = useCallback((datos) => {
    const mapa = {};
    (datos?.resultados || []).forEach((r) => (mapa[r.item_id] = r));
    setGuardados(mapa);
    setRecomendaciones(datos?.recomendaciones ?? '');
    setRecomendacionesGuardadas(datos?.recomendaciones ?? '');
    setFotos(datos?.fotos || []);
  }, []);

  const cargar = useCallback(() => {
    Promise.all([servicioPlanilla.catalogo(), servicioPlanilla.obtener(orden.id)])
      .then(([c, p]) => {
        setCatalogo(c.datos);
        aplicar(p.datos);
      })
      .catch((e) => setError(e.message));
  }, [orden.id, aplicar]);

  useEffect(cargar, [cargar]);

  // Valor visible de cada ítem: el cambio sin guardar o lo guardado.
  const valor = useCallback((itemId) => ({ ...guardados[itemId], ...cambios[itemId] }), [guardados, cambios]);

  const resumen = useMemo(() => {
    const porSeccion = {};
    (catalogo || []).forEach((item) => {
      const v = valor(item.id);
      const s = (porSeccion[item.seccion] ||= { total: 0, hechos: 0, estados: [] });
      s.total += 1;
      if (v.resultado && v.estado) {
        s.hechos += 1;
        s.estados.push(v.estado);
      }
    });
    const todos = Object.values(porSeccion);
    return {
      porSeccion,
      total: todos.reduce((a, s) => a + s.total, 0),
      hechos: todos.reduce((a, s) => a + s.hechos, 0),
      conteo: ['B', 'R', 'M'].reduce((a, e) => ({ ...a, [e]: todos.flatMap((s) => s.estados).filter((x) => x === e).length }), {}),
    };
  }, [catalogo, valor]);

  function cambiar(itemId, campo, nuevo) {
    setCambios((actual) => ({ ...actual, [itemId]: { ...actual[itemId], [campo]: nuevo } }));
    setErrores((actual) => ({ ...actual, [itemId]: undefined }));
  }

  const pendientes = Object.keys(cambios).length + (recomendaciones !== recomendacionesGuardadas ? 1 : 0);

  async function guardar() {
    // RF-037: cada ítem cambiado necesita resultado y estado; R o M exigen observación.
    const nuevosErrores = {};
    const resultados = Object.keys(cambios).map((id) => {
      const v = valor(id);
      if (!v.resultado || !v.estado) nuevosErrores[id] = 'Elija el resultado y el estado.';
      else if (v.estado !== 'B' && !(v.observacion || '').trim()) nuevosErrores[id] = 'Un ítem en estado Regular o Malo exige una observación.';
      return { item_id: Number(id), resultado: v.resultado, estado: v.estado, observacion: v.observacion || null };
    });
    if (Object.keys(nuevosErrores).length) {
      setErrores(nuevosErrores);
      setError('Revise los ítems marcados antes de guardar.');
      const primero = catalogo.find((i) => nuevosErrores[i.id]);
      if (primero) setSeccion(primero.seccion);
      return;
    }

    setGuardando(true);
    setError('');
    try {
      const respuesta = await servicioPlanilla.guardar(orden.id, { resultados, recomendaciones: recomendaciones || null });
      aplicar(respuesta.datos);
      setCambios({});
      notificar('Planilla guardada.');
      alCambiar();
    } catch (e) {
      setError(e.message);
    } finally {
      setGuardando(false);
    }
  }

  async function crearCorrectivo(resultado) {
    setCreando(resultado.id);
    try {
      const respuesta = await servicioPlanilla.crearCorrectivo(orden.id, resultado.id);
      notificar(respuesta.mensaje || 'Correctivo creado.');
      cargar();
      alCambiar();
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setCreando(null);
    }
  }

  async function subirFoto(evento) {
    const archivo = evento.target.files?.[0];
    evento.target.value = '';
    if (!archivo) return;
    const problema = validarFotos([archivo], MAXIMO_FOTOS - fotos.length);
    if (problema) {
      notificar(problema, 'error');
      return;
    }
    try {
      await servicioPlanilla.subirFoto(orden.id, archivo);
      notificar('Foto agregada.');
      cargar();
    } catch (e) {
      notificar(Object.values(e.errores || {})[0]?.[0] || e.message, 'error');
    }
  }

  async function eliminarFoto(foto) {
    try {
      await servicioPlanilla.eliminarFoto(orden.id, foto.id);
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    }
  }

  if (error && !catalogo) return <Alerta>{error}</Alerta>;
  if (!catalogo) return <Cargando texto="Cargando planilla…" />;

  const items = catalogo.filter((i) => i.seccion === seccion);
  const progreso = resumen.total ? Math.round((resumen.hechos / resumen.total) * 100) : 0;

  return (
    <div className="rejilla">
      <Tarjeta sinRelleno>
        <div className="tarjeta__cuerpo planilla__cabecera">
          <div>
            <h2 className="tarjeta__titulo" style={{ marginBottom: 4 }}>
              Planilla de mantenimiento
            </h2>
            <p className="texto-pequeno texto-suave" style={{ margin: 0 }}>
              {editable
                ? 'Registre el resultado (INS, EST o N.A.) y el estado (B, R o M) de cada ítem. R o M exigen observación.'
                : orden.lleva_planilla && orden.estado !== 'en_proceso' && !resumen.hechos
                  ? 'La planilla se diligencia cuando el técnico inicia el trabajo.'
                  : 'Resultado registrado por el técnico.'}
            </p>
          </div>
          <div className="planilla__avance">
            <span className="tabular">
              <b>{resumen.hechos}</b> de {resumen.total} ítems
            </span>
            <span className="planilla__conteo">
              <i className="estado-b">B {resumen.conteo.B}</i> <i className="estado-r">R {resumen.conteo.R}</i> <i className="estado-m">M {resumen.conteo.M}</i>
            </span>
            <span className="tecnicos__barra" aria-hidden="true">
              <i style={{ width: `${progreso}%` }} />
            </span>
          </div>
        </div>

        <div className="planilla__secciones" role="tablist" aria-label="Secciones del ascensor">
          {Object.entries(SECCIONES).map(([clave, nombre]) => {
            const s = resumen.porSeccion[clave] || { total: 0, hechos: 0, estados: [] };
            const peor = peorEstado(s.estados);
            return (
              <button key={clave} type="button" role="tab" aria-selected={seccion === clave} className={`planilla__seccion${seccion === clave ? ' activa' : ''}`} onClick={() => setSeccion(clave)}>
                {peor && <i className={`punto punto--${{ B: 'exito', R: 'alerta', M: 'peligro' }[peor]}`} aria-hidden="true" />}
                {nombre}
                <small className="tabular">
                  {s.hechos}/{s.total}
                </small>
              </button>
            );
          })}
        </div>

        {error && (
          <div style={{ padding: '12px 20px 0' }}>
            <Alerta>{error}</Alerta>
          </div>
        )}

        <ul className="planilla__items">
          {items.map((item) => {
            const v = valor(item.id);
            const guardado = guardados[item.id];
            const pideObservacion = v.estado === 'R' || v.estado === 'M';
            return (
              <li key={item.id} className={`planilla__item${errores[item.id] ? ' con-error' : ''}`}>
                <div className="planilla__nombre">
                  <span className="codigo">{item.codigo}</span>
                  <b>{item.nombre}</b>
                  {item.critico && (
                    <span className="planilla__critico" title="Ítem de seguridad: en estado Malo avisa de inmediato al coordinador">
                      <ShieldAlert size={13} /> Seguridad
                    </span>
                  )}
                </div>

                {editable ? (
                  <div className="planilla__controles">
                    <div className="segmento segmento--pequeno" role="radiogroup" aria-label={`Resultado de ${item.nombre}`}>
                      {RESULTADOS.map((r) => (
                        <button key={r.valor} type="button" role="radio" aria-checked={v.resultado === r.valor} aria-pressed={v.resultado === r.valor} onClick={() => cambiar(item.id, 'resultado', r.valor)} title="Significado de INS y EST por definir (RF-037)">
                          {r.texto}
                        </button>
                      ))}
                    </div>
                    <div className="estados-brm" role="radiogroup" aria-label={`Estado de ${item.nombre}`}>
                      {ESTADOS.map((e) => (
                        <button key={e.valor} type="button" role="radio" aria-checked={v.estado === e.valor} className={`estado-brm estado-brm--${e.valor}${v.estado === e.valor ? ' activo' : ''}`} onClick={() => cambiar(item.id, 'estado', e.valor)} title={e.titulo}>
                          {e.texto}
                        </button>
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className="planilla__controles">
                    {v.resultado ? <span className="codigo">{v.resultado === 'NA' ? 'N.A.' : v.resultado}</span> : <span className="texto-suave">Sin registrar</span>}
                    {v.estado && <span className={`estado-brm estado-brm--${v.estado} activo`}>{v.estado}</span>}
                  </div>
                )}

                {editable && (pideObservacion || v.observacion) && (
                  <input
                    className="campo__control planilla__observacion"
                    placeholder={pideObservacion ? 'Observación (obligatoria)' : 'Observación'}
                    value={v.observacion || ''}
                    onChange={(e) => cambiar(item.id, 'observacion', e.target.value)}
                    aria-label={`Observación de ${item.nombre}`}
                    aria-invalid={Boolean(errores[item.id])}
                  />
                )}
                {!editable && v.observacion && <p className="planilla__texto">{v.observacion}</p>}
                {errores[item.id] && <span className="campo__error">{errores[item.id]}</span>}

                {/* RF-038: correctivo desde un ítem guardado en R o M. */}
                {guardado && (guardado.estado === 'R' || guardado.estado === 'M') && !cambios[item.id] && (
                  <div className="planilla__correctivo">
                    {guardado.correctivo_abierto ? (
                      <Link to={`/ordenes/${guardado.correctivo_abierto.id}`}>
                        <Wrench size={13} /> Correctivo {guardado.correctivo_abierto.numero}
                      </Link>
                    ) : (
                      puedeCrearCorrectivo && (
                        <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => crearCorrectivo(guardado)} disabled={creando === guardado.id}>
                          <Wrench size={14} /> {creando === guardado.id ? 'Creando…' : 'Crear orden correctiva'}
                        </button>
                      )
                    )}
                  </div>
                )}
              </li>
            );
          })}
        </ul>

        <div className="tarjeta__cuerpo" style={{ borderTop: '1px solid var(--color-borde)' }}>
          <label className="campo__etiqueta" htmlFor="recomendaciones">
            Recomendaciones
          </label>
          {editable ? (
            <textarea id="recomendaciones" className="campo__control" rows={3} value={recomendaciones} onChange={(e) => setRecomendaciones(e.target.value)} />
          ) : (
            <p className="planilla__texto" style={{ margin: '4px 0 0' }}>
              {recomendaciones || <span className="texto-suave">Sin recomendaciones.</span>}
            </p>
          )}
          {editable && (
            <div className="planilla__guardar">
              <span className="texto-pequeno texto-suave">{pendientes ? `${pendientes} cambio(s) sin guardar` : 'Todo guardado'}</span>
              <button type="button" className="boton" onClick={guardar} disabled={!pendientes || guardando}>
                {guardando ? 'Guardando…' : 'Guardar planilla'}
              </button>
            </div>
          )}
        </div>
      </Tarjeta>

      <Tarjeta
        titulo={`Fotos (${fotos.length} de ${MAXIMO_FOTOS})`}
        acciones={
          editable &&
          fotos.length < MAXIMO_FOTOS && (
            <label className="boton boton--secundario boton--pequeno">
              <ImagePlus size={15} /> Agregar foto
              <input type="file" accept="image/jpeg,image/png,image/webp" hidden onChange={subirFoto} />
            </label>
          )
        }
      >
        {fotos.length ? (
          <div className="fotos">
            {fotos.map((foto, indice) => (
              <figure key={foto.id} className="fotos__item">
                <ImagenProtegida ruta={foto.ruta_archivo} alt={`Foto ${indice + 1} de la planilla`} />
                {editable && (
                  <button type="button" className="fotos__quitar" onClick={() => eliminarFoto(foto)} aria-label={`Eliminar foto ${indice + 1}`}>
                    <Trash2 size={13} />
                  </button>
                )}
              </figure>
            ))}
          </div>
        ) : (
          <p className="texto-suave texto-pequeno" style={{ margin: 0 }}>
            Sin fotos. {editable && 'Las fotos se incluyen en el informe PDF.'}
          </p>
        )}
      </Tarjeta>
      {!editable && orden.estado === 'en_proceso' && !orden.permisos?.ejecutar && (
        <Insignia tipo="info">Solo el técnico asignado diligencia la planilla.</Insignia>
      )}
    </div>
  );
}
