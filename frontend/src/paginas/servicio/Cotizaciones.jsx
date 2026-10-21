import { useEffect, useState } from 'react';
import { Plus, Search, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { equipos, servicio } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useFormulario } from '../../hooks/useFormulario';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import { dinero, fecha, fechaIso } from '../../utilidades/formato';
import { ESTADOS_COTIZACION } from '../../utilidades/servicio';

/** Cotizaciones de correctivos: el cliente las aprueba o rechaza desde su portal. */
export default function Cotizaciones() {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const [nueva, setNueva] = useState(false);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina } = useListado(servicio.cotizaciones, { estado: esCliente ? 'enviada' : '' });

  return (
    <>
      <EncabezadoPagina
        titulo={esCliente ? 'Mis cotizaciones' : 'Cotizaciones'}
        descripcion={
          esCliente
            ? 'Revise las cotizaciones de reparaciones de sus ascensores y apruébelas o recházelas antes de su vigencia.'
            : 'Propuestas de mantenimiento correctivo enviadas a los clientes. El cliente responde desde su portal.'
        }
      >
        {tienePermiso('cotizaciones.crear') && (
          <button type="button" className="boton" onClick={() => setNueva(true)}>
            <Plus size={16} aria-hidden="true" /> Crear cotización
          </button>
        )}
      </EncabezadoPagina>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por número, trabajo o ascensor"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar cotizaciones"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="">Todas</option>
            {Object.entries(ESTADOS_COTIZACION).map(([valor, e]) => (
              <option key={valor} value={valor}>
                {e.texto}
              </option>
            ))}
          </select>
        </div>
        {error && (
          <div style={{ padding: 16 }}>
            <Alerta>{error}</Alerta>
          </div>
        )}
        {cargando && !filas.length ? (
          <Cargando />
        ) : (
          <Tabla
            filas={filas}
            alSeleccionar={(c) => navegar(`/cotizaciones/${c.id}`)}
            vacio={
              <EstadoVacio
                titulo="No hay cotizaciones para mostrar"
                descripcion={tienePermiso('cotizaciones.crear') ? 'Cree una cotización desde aquí o desde un hallazgo del checklist.' : 'Cuando le enviemos una propuesta, aparecerá aquí para que la apruebe.'}
              />
            }
            columnas={[
              {
                clave: 'numero',
                titulo: 'Cotización',
                render: (c) => (
                  <>
                    <strong>{c.numero}</strong>
                    <div className="tabla__secundario">{c.titulo}</div>
                  </>
                ),
              },
              {
                clave: 'equipo',
                titulo: 'Ascensor',
                render: (c) => (
                  <>
                    {c.equipo?.codigo}
                    <div className="tabla__secundario">{esCliente ? c.equipo?.edificio?.nombre : c.cliente?.nombre}</div>
                  </>
                ),
              },
              { clave: 'emision', titulo: 'Emitida', render: (c) => fecha(c.fecha_emision) },
              { clave: 'vigencia', titulo: 'Vigente hasta', render: (c) => fecha(c.vigente_hasta) },
              { clave: 'total', titulo: 'Total', clase: 'numero', render: (c) => <strong>{dinero(c.total)}</strong> },
              { clave: 'estado', titulo: 'Estado', render: (c) => <Insignia tipo={ESTADOS_COTIZACION[c.estado]?.tipo}>{ESTADOS_COTIZACION[c.estado]?.texto}</Insignia> },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      {nueva && (
        <ModalCotizacion
          alCerrar={(cotizacion) => {
            setNueva(false);
            if (cotizacion) navegar(`/cotizaciones/${cotizacion.id}`);
          }}
        />
      )}
    </>
  );
}

const RENGLON = { descripcion: '', cantidad: '1', valor_unitario: '' };
const numero = (texto) => Number(String(texto).replace(/\./g, '').replace(',', '.')) || 0;

export function ModalCotizacion({ equipo, alCerrar }) {
  const notificar = useNotificar();
  const [opciones, setOpciones] = useState([]);
  const { datos, setDatos, cambiar, error, errores, mensajeError, enviando, enviar } = useFormulario({
    equipo_id: equipo ? String(equipo.id) : '',
    titulo: '',
    descripcion: '',
    iva_porcentaje: '19',
    vigente_hasta: fechaIso(new Date(Date.now() + 30 * 86400000)),
    items: [{ ...RENGLON }],
  });

  useEffect(() => {
    if (equipo) return;
    equipos
      .listar({ por_pagina: 100 })
      .then((r) => setOpciones(r.datos))
      .catch(() => {});
  }, [equipo]);

  const cambiarRenglon = (i, campo) => (valor) => setDatos((d) => ({ ...d, items: d.items.map((r, j) => (j === i ? { ...r, [campo]: valor } : r)) }));
  const subtotal = datos.items.reduce((suma, r) => suma + numero(r.cantidad) * numero(r.valor_unitario), 0);
  const iva = Math.round((subtotal * numero(datos.iva_porcentaje)) / 100);

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() =>
      servicio.crearCotizacion({
        ...datos,
        equipo_id: datos.equipo_id ? Number(datos.equipo_id) : null,
        iva_porcentaje: numero(datos.iva_porcentaje),
        items: datos.items.map((r) => ({ descripcion: r.descripcion, cantidad: numero(r.cantidad), valor_unitario: numero(r.valor_unitario) })),
      }),
    );
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(respuesta.datos);
    }
  }

  return (
    <Modal
      titulo="Crear cotización"
      ancho
      abierto
      alCerrar={() => alCerrar(null)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(null)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-cotizacion" className="boton" disabled={enviando}>
            {enviando ? 'Enviando…' : 'Enviar al cliente'}
          </button>
        </>
      }
    >
      <form id="formulario-cotizacion" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        {!equipo && (
          <CampoSelector
            etiqueta="Ascensor"
            obligatorio
            valor={datos.equipo_id}
            alCambiar={cambiar('equipo_id')}
            error={error('equipo_id')}
            opciones={opciones.map((e) => ({ valor: String(e.id), texto: `${e.codigo} · ${e.edificio?.nombre || e.ubicacion || ''}` }))}
          />
        )}
        <CampoTexto etiqueta="Trabajo cotizado" obligatorio valor={datos.titulo} alCambiar={cambiar('titulo')} error={error('titulo')} placeholder="Cambio de amortiguadores del foso" />
        <CampoArea etiqueta="Detalle" rows={2} valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} placeholder="Alcance, repuestos, garantía y condiciones" />

        <fieldset className="renglones">
          <legend>Renglones</legend>
          {datos.items.map((r, i) => (
            <div key={i} className="renglones__fila">
              <CampoTexto etiqueta="Descripción" obligatorio valor={r.descripcion} alCambiar={cambiarRenglon(i, 'descripcion')} error={errores[`items.${i}.descripcion`]?.[0]} />
              <CampoTexto etiqueta="Cantidad" obligatorio inputMode="decimal" valor={r.cantidad} alCambiar={cambiarRenglon(i, 'cantidad')} error={errores[`items.${i}.cantidad`]?.[0]} />
              <CampoTexto etiqueta="Valor unitario" obligatorio inputMode="numeric" valor={r.valor_unitario} alCambiar={cambiarRenglon(i, 'valor_unitario')} error={errores[`items.${i}.valor_unitario`]?.[0]} />
              <button
                type="button"
                className="boton-icono"
                aria-label={`Quitar el renglón ${i + 1}`}
                disabled={datos.items.length === 1}
                onClick={() => setDatos((d) => ({ ...d, items: d.items.filter((_, j) => j !== i) }))}
              >
                <Trash2 size={16} />
              </button>
            </div>
          ))}
          {error('items') && <span className="campo__error">{error('items')}</span>}
          <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setDatos((d) => ({ ...d, items: [...d.items, { ...RENGLON }] }))}>
            <Plus size={14} aria-hidden="true" /> Agregar renglón
          </button>
        </fieldset>

        <div className="formulario__fila">
          <CampoTexto etiqueta="IVA (%)" inputMode="decimal" valor={datos.iva_porcentaje} alCambiar={cambiar('iva_porcentaje')} error={error('iva_porcentaje')} />
          <CampoTexto etiqueta="Vigente hasta" tipo="date" min={fechaIso()} valor={datos.vigente_hasta} alCambiar={cambiar('vigente_hasta')} error={error('vigente_hasta')} />
        </div>
        <dl className="totales">
          <div>
            <dt>Subtotal</dt>
            <dd>{dinero(subtotal)}</dd>
          </div>
          <div>
            <dt>IVA</dt>
            <dd>{dinero(iva)}</dd>
          </div>
          <div className="totales__total">
            <dt>Total</dt>
            <dd>{dinero(subtotal + iva)}</dd>
          </div>
        </dl>
      </form>
    </Modal>
  );
}
