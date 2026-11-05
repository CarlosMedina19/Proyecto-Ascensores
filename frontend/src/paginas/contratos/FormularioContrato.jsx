import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Plus, X } from 'lucide-react';
import { clientes, contratos, equipos, planes } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoBuscador, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import { ESTADOS_EQUIPO, dinero, periodicidad } from '../../utilidades/formato';

/** Periodos de la vigencia (un periodo empezado cuenta completo), igual que el servidor. */
function periodosEntre(inicio, fin, meses) {
  if (!inicio || !fin || !meses || fin <= inicio) return null;
  const [a1, m1, d1] = inicio.split('-').map(Number);
  const fechaFin = new Date(`${fin}T00:00:00`);
  fechaFin.setDate(fechaFin.getDate() + 1);
  const totalMeses = (fechaFin.getFullYear() - a1) * 12 + (fechaFin.getMonth() + 1 - m1) + (fechaFin.getDate() - d1) / 30;
  return Math.max(1, Math.ceil(Math.round(totalMeses * 100) / 100 / meses));
}

/**
 * RF-019: contrato con cliente, ascensores incluidos, vigencia, valor, servicios y condiciones.
 * El plan de alquiler del software se ajusta aquí: periodicidad, precio pactado y servicios incluidos.
 */
export default function FormularioContrato() {
  const { id } = useParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [listaClientes, setListaClientes] = useState(null);
  const [listaPlanes, setListaPlanes] = useState([]);
  const [equiposCliente, setEquiposCliente] = useState(null);
  const [cargado, setCargado] = useState(!editando);
  const [servicioNuevo, setServicioNuevo] = useState('');
  // Mientras no se escriba a mano, el valor total sigue al precio × periodos.
  const [valorManual, setValorManual] = useState(editando);
  const { datos, setDatos, cambiar, error, errores, mensajeError, enviando, enviar } = useFormulario({
    cliente_id: '',
    plan_id: '',
    periodicidad_id: '',
    precio_periodo: '',
    servicios_pactados: [],
    fecha_inicio: '',
    fecha_fin: '',
    valor: '',
    servicios: '',
    condiciones: '',
    equipos: [],
  });

  useEffect(() => {
    clientes
      .listar({ por_pagina: 100, estado: 1 })
      .then((r) => setListaClientes(r.datos))
      .catch(() => setListaClientes([]));
    planes
      .listar({ por_pagina: 100, activo: 1 })
      .then((r) => setListaPlanes(r.datos))
      .catch(() => setListaPlanes([]));
  }, []);

  useEffect(() => {
    if (!editando) return;
    contratos.obtener(id).then(({ datos: contrato }) => {
      setDatos({
        cliente_id: String(contrato.cliente_id),
        plan_id: contrato.plan_id ? String(contrato.plan_id) : '',
        periodicidad_id: contrato.periodicidad_id ? String(contrato.periodicidad_id) : '',
        precio_periodo: contrato.precio_periodo != null ? String(Number(contrato.precio_periodo)) : '',
        servicios_pactados: contrato.servicios_pactados ?? [],
        fecha_inicio: contrato.fecha_inicio,
        fecha_fin: contrato.fecha_fin,
        valor: String(Number(contrato.valor)),
        servicios: contrato.servicios ?? '',
        condiciones: contrato.condiciones ?? '',
        equipos: contrato.equipos.map((e) => e.id),
      });
      setCargado(true);
    });
  }, [editando, id, setDatos]);

  // Solo se ofrecen los ascensores del cliente seleccionado.
  useEffect(() => {
    if (!datos.cliente_id) {
      setEquiposCliente(null);
      return;
    }
    setEquiposCliente(undefined);
    equipos
      .listar({ cliente_id: datos.cliente_id, por_pagina: 100 })
      .then((r) => setEquiposCliente(r.datos))
      .catch(() => setEquiposCliente([]));
  }, [datos.cliente_id]);

  const plan = listaPlanes.find((p) => String(p.id) === String(datos.plan_id));
  const precios = (plan?.precios || []).slice().sort((a, b) => a.periodicidad.meses - b.periodicidad.meses);
  const precioElegido = precios.find((p) => String(p.periodicidad_id ?? p.periodicidad.id) === String(datos.periodicidad_id));
  const precioCatalogo = precioElegido ? Number(precioElegido.precio) : null;
  const periodos = periodosEntre(datos.fecha_inicio, datos.fecha_fin, precioElegido?.periodicidad.meses);
  const valorCalculado = periodos && datos.precio_periodo !== '' ? Number(datos.precio_periodo) * periodos : null;

  // El valor total se recalcula mientras el usuario no lo haya escrito.
  useEffect(() => {
    if (!valorManual && valorCalculado !== null) setDatos((d) => ({ ...d, valor: String(valorCalculado) }));
  }, [valorCalculado, valorManual, setDatos]);

  const serviciosPlan = useMemo(() => plan?.servicios_incluidos ?? [], [plan]);

  function elegirPlan(valor) {
    const elegido = listaPlanes.find((p) => String(p.id) === String(valor));
    setDatos((d) => {
      if (!elegido) return { ...d, plan_id: '', periodicidad_id: '', precio_periodo: '', servicios_pactados: [] };
      const primero = (elegido.precios || []).slice().sort((a, b) => a.periodicidad.meses - b.periodicidad.meses)[0];
      return {
        ...d,
        plan_id: String(elegido.id),
        periodicidad_id: primero ? String(primero.periodicidad_id ?? primero.periodicidad.id) : '',
        precio_periodo: primero ? String(Number(primero.precio)) : '',
        servicios_pactados: [...(elegido.servicios_incluidos ?? [])],
      };
    });
  }

  function elegirPeriodicidad(valor) {
    const precio = precios.find((p) => String(p.periodicidad_id ?? p.periodicidad.id) === String(valor));
    setDatos((d) => ({ ...d, periodicidad_id: valor, precio_periodo: precio ? String(Number(precio.precio)) : d.precio_periodo }));
  }

  function alternarServicio(servicio, marcado) {
    setDatos((d) => ({ ...d, servicios_pactados: marcado ? [...d.servicios_pactados, servicio] : d.servicios_pactados.filter((s) => s !== servicio) }));
  }

  function agregarServicio() {
    const texto = servicioNuevo.trim();
    if (!texto || datos.servicios_pactados.includes(texto)) return;
    setDatos((d) => ({ ...d, servicios_pactados: [...d.servicios_pactados, texto] }));
    setServicioNuevo('');
  }

  function alternarEquipo(equipoId, marcado) {
    setDatos((actual) => ({
      ...actual,
      equipos: marcado ? [...actual.equipos, equipoId] : actual.equipos.filter((e) => e !== equipoId),
    }));
  }

  async function guardar(evento) {
    evento.preventDefault();
    const cuerpo = {
      plan_id: datos.plan_id ? Number(datos.plan_id) : null,
      periodicidad_id: datos.plan_id && datos.periodicidad_id ? Number(datos.periodicidad_id) : null,
      precio_periodo: datos.plan_id && datos.precio_periodo !== '' ? Number(datos.precio_periodo) : null,
      servicios_pactados: datos.plan_id ? datos.servicios_pactados : null,
      fecha_inicio: datos.fecha_inicio,
      fecha_fin: datos.fecha_fin,
      valor: datos.valor === '' ? null : Number(datos.valor),
      servicios: datos.servicios || null,
      condiciones: datos.condiciones || null,
      equipos: datos.equipos,
    };
    if (!editando) cuerpo.cliente_id = datos.cliente_id ? Number(datos.cliente_id) : null;

    const respuesta = await enviar(() => (editando ? contratos.actualizar(id, cuerpo) : contratos.crear(cuerpo)));
    if (respuesta) {
      notificar(editando ? 'Contrato actualizado.' : `Contrato ${respuesta.datos.numero} creado.`);
      navegar(`/contratos/${respuesta.datos.id}`, { replace: true });
    }
  }

  if (!cargado || listaClientes === null) return <Cargando />;

  const ajustado = precioCatalogo !== null && datos.precio_periodo !== '' && Number(datos.precio_periodo) !== precioCatalogo;
  const serviciosPropios = datos.servicios_pactados.filter((s) => !serviciosPlan.includes(s));

  return (
    <>
      <EncabezadoPagina titulo={editando ? 'Editar contrato' : 'Nuevo contrato'} migas={<Link to="/contratos">Contratos</Link>} />
      <form className="formulario" onSubmit={guardar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <Tarjeta titulo="Datos del contrato">
          <div className="formulario">
            <CampoBuscador
              etiqueta="Cliente"
              obligatorio
              valor={datos.cliente_id}
              alCambiar={(valor) => setDatos((actual) => ({ ...actual, cliente_id: valor, equipos: [] }))}
              error={error('cliente_id')}
              opciones={listaClientes.map((c) => ({ valor: String(c.id), texto: c.nombre, detalle: c.nit || c.numero_documento }))}
              placeholder="Busque el cliente por nombre o NIT"
              disabled={editando}
              ayuda={editando ? 'El cliente de un contrato no se cambia.' : undefined}
            />
            <div className="formulario__fila">
              <CampoTexto etiqueta="Fecha inicial" tipo="date" obligatorio valor={datos.fecha_inicio} alCambiar={cambiar('fecha_inicio')} error={error('fecha_inicio')} />
              <CampoTexto etiqueta="Fecha final" tipo="date" obligatorio valor={datos.fecha_fin} alCambiar={cambiar('fecha_fin')} error={error('fecha_fin')} />
            </div>
          </div>
        </Tarjeta>

        <Tarjeta titulo="Plan de alquiler del software">
          <div className="formulario">
            <p className="texto-pequeno texto-suave" style={{ margin: 0 }}>
              Elija el plan y ajústelo para este cliente: el precio por periodo, la periodicidad y los servicios incluidos. La vigencia es la del contrato.
            </p>
            <div className="formulario__fila formulario__fila--3">
              <CampoSelector
                etiqueta="Plan"
                valor={datos.plan_id}
                alCambiar={elegirPlan}
                error={error('plan_id')}
                vacio="Sin plan (valor libre)"
                opciones={listaPlanes.map((p) => ({ valor: String(p.id), texto: p.nombre }))}
              />
              <CampoSelector
                etiqueta="Periodicidad"
                obligatorio={Boolean(plan)}
                valor={datos.periodicidad_id}
                alCambiar={elegirPeriodicidad}
                error={error('periodicidad_id')}
                disabled={!plan}
                opciones={precios.map((p) => ({ valor: String(p.periodicidad_id ?? p.periodicidad.id), texto: `${periodicidad(p.periodicidad.meses)} · ${dinero(p.precio)}` }))}
              />
              <CampoTexto
                etiqueta="Precio pactado por periodo"
                tipo="number"
                min="0"
                step="1000"
                valor={datos.precio_periodo}
                alCambiar={cambiar('precio_periodo')}
                error={error('precio_periodo')}
                disabled={!plan}
                ayuda={precioCatalogo !== null ? (ajustado ? `Ajustado. Catálogo: ${dinero(precioCatalogo)}` : 'Precio del catálogo; puede ajustarlo.') : undefined}
              />
            </div>

            {plan && (
              <div className="campo">
                <span className="campo__etiqueta">Servicios incluidos ({datos.servicios_pactados.length})</span>
                <div className="lista-seleccion">
                  {serviciosPlan.map((s) => (
                    <Casilla key={s} etiqueta={s} valor={datos.servicios_pactados.includes(s)} alCambiar={(m) => alternarServicio(s, m)} />
                  ))}
                  {serviciosPropios.map((s) => (
                    <span key={s} className="servicio-propio">
                      {s}
                      <button type="button" onClick={() => alternarServicio(s, false)} aria-label={`Quitar ${s}`}>
                        <X size={13} />
                      </button>
                    </span>
                  ))}
                </div>
                <div className="catalogo__nuevo" style={{ marginTop: 8 }}>
                  <input
                    className="campo__control"
                    value={servicioNuevo}
                    onChange={(e) => setServicioNuevo(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), agregarServicio())}
                    placeholder="Agregar un servicio pactado solo para este cliente"
                    aria-label="Nuevo servicio pactado"
                    maxLength={200}
                  />
                  <button type="button" className="boton boton--secundario" onClick={agregarServicio} disabled={!servicioNuevo.trim()}>
                    <Plus size={16} aria-hidden="true" /> Agregar
                  </button>
                </div>
                {Object.entries(errores).find(([c]) => c.startsWith('servicios_pactados'))?.[1]?.[0] && (
                  <span className="campo__error">{Object.entries(errores).find(([c]) => c.startsWith('servicios_pactados'))[1][0]}</span>
                )}
              </div>
            )}

            <div className="formulario__fila">
              <CampoTexto
                etiqueta="Valor total del contrato"
                tipo="number"
                min="0"
                step="1000"
                obligatorio={!plan}
                valor={datos.valor}
                alCambiar={(v) => {
                  setValorManual(true);
                  cambiar('valor')(v);
                }}
                error={error('valor')}
                ayuda={
                  periodos
                    ? `${periodos} ${periodos === 1 ? 'periodo' : 'periodos'} × ${dinero(datos.precio_periodo || 0)} = ${dinero(valorCalculado ?? 0)}${valorManual && valorCalculado !== null && Number(datos.valor) !== valorCalculado ? ' (valor escrito a mano)' : ''}`
                    : undefined
                }
              />
              {valorManual && valorCalculado !== null && Number(datos.valor) !== valorCalculado && (
                <div className="campo" style={{ justifyContent: 'flex-end' }}>
                  <button type="button" className="boton boton--fantasma" onClick={() => setValorManual(false)}>
                    Usar el valor calculado
                  </button>
                </div>
              )}
            </div>
            <CampoArea etiqueta="Alcance de los servicios" valor={datos.servicios} alCambiar={cambiar('servicios')} error={error('servicios')} rows={2} />
            <CampoArea etiqueta="Condiciones" valor={datos.condiciones} alCambiar={cambiar('condiciones')} error={error('condiciones')} rows={3} />
          </div>
        </Tarjeta>

        <Tarjeta titulo={`Ascensores incluidos (${datos.equipos.length})`}>
          {error('equipos') && <Alerta>{error('equipos')}</Alerta>}
          {equiposCliente === null ? (
            <p className="texto-suave" style={{ margin: 0 }}>
              Seleccione el cliente para ver sus ascensores.
            </p>
          ) : equiposCliente === undefined ? (
            <Cargando />
          ) : equiposCliente.length ? (
            <div className="lista-seleccion">
              {equiposCliente.map((equipo) => (
                <Casilla
                  key={equipo.id}
                  valor={datos.equipos.includes(equipo.id)}
                  alCambiar={(marcado) => alternarEquipo(equipo.id, marcado)}
                  etiqueta={
                    <span>
                      <strong>{equipo.codigo}</strong> · {equipo.edificio?.nombre}
                      {equipo.estado !== 'activo' && (
                        <>
                          {' '}
                          <Insignia tipo={ESTADOS_EQUIPO[equipo.estado]?.tipo}>{ESTADOS_EQUIPO[equipo.estado]?.texto}</Insignia>
                        </>
                      )}
                    </span>
                  }
                />
              ))}
            </div>
          ) : (
            <EstadoVacio titulo="El cliente no tiene ascensores registrados" />
          )}
        </Tarjeta>

        <div className="formulario__acciones">
          <Link to={editando ? `/contratos/${id}` : '/contratos'} className="boton boton--secundario">
            Cancelar
          </Link>
          <button type="submit" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : 'Guardar contrato'}
          </button>
        </div>
      </form>
    </>
  );
}
