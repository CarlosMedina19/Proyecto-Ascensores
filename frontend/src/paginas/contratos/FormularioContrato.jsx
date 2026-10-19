import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { clientes, contratos, equipos } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import { ESTADOS_EQUIPO } from '../../utilidades/formato';

/**
 * RF-019: contrato con cliente, ascensores incluidos, vigencia, valor, servicios y condiciones.
 */
export default function FormularioContrato() {
  const { id } = useParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [listaClientes, setListaClientes] = useState(null);
  const [equiposCliente, setEquiposCliente] = useState(null);
  const [cargado, setCargado] = useState(!editando);
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    cliente_id: '',
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
  }, []);

  useEffect(() => {
    if (!editando) return;
    contratos.obtener(id).then(({ datos: contrato }) => {
      setDatos({
        cliente_id: String(contrato.cliente_id),
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

  function alternarEquipo(equipoId, marcado) {
    setDatos((actual) => ({
      ...actual,
      equipos: marcado ? [...actual.equipos, equipoId] : actual.equipos.filter((e) => e !== equipoId),
    }));
  }

  async function guardar(evento) {
    evento.preventDefault();
    const cuerpo = {
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

  return (
    <>
      <EncabezadoPagina titulo={editando ? 'Editar contrato' : 'Nuevo contrato'} migas={<Link to="/contratos">Contratos</Link>} />
      <form className="formulario" onSubmit={guardar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <Tarjeta titulo="Datos del contrato">
          <div className="formulario">
            <CampoSelector
              etiqueta="Cliente"
              obligatorio
              valor={datos.cliente_id}
              alCambiar={(valor) => setDatos((actual) => ({ ...actual, cliente_id: valor, equipos: [] }))}
              error={error('cliente_id')}
              opciones={listaClientes.map((c) => ({ valor: String(c.id), texto: c.nombre }))}
              disabled={editando}
              ayuda={editando ? 'El cliente de un contrato no se cambia.' : undefined}
            />
            <div className="formulario__fila formulario__fila--3">
              <CampoTexto etiqueta="Fecha inicial" tipo="date" obligatorio valor={datos.fecha_inicio} alCambiar={cambiar('fecha_inicio')} error={error('fecha_inicio')} />
              <CampoTexto etiqueta="Fecha final" tipo="date" obligatorio valor={datos.fecha_fin} alCambiar={cambiar('fecha_fin')} error={error('fecha_fin')} />
              <CampoTexto etiqueta="Valor del contrato" tipo="number" min="0" step="1000" obligatorio valor={datos.valor} alCambiar={cambiar('valor')} error={error('valor')} />
            </div>
            <CampoArea etiqueta="Servicios" valor={datos.servicios} alCambiar={cambiar('servicios')} error={error('servicios')} rows={2} />
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
