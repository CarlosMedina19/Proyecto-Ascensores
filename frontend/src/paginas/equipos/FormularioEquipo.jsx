import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { edificios, equipos, fotosEquipo } from '../../api/servicios';
import { limpiarVacios, useFormulario } from '../../hooks/useFormulario';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import { ESTADOS_EQUIPO } from '../../utilidades/formato';
import { GaleriaFotos, SelectorFotos } from '../../componentes/FotosEquipo';

const EQUIPO_VACIO = {
  edificio_id: '',
  codigo: '',
  marca: '',
  modelo: '',
  numero_serie: '',
  ubicacion: '',
  estado: 'activo',
  fecha_instalacion: '',
  observaciones: '',
};

const FICHA_VACIA = {
  capacidad_kg: '',
  velocidad_mpm: '',
  paradas: '',
  tipo_traccion: '',
  motor: '',
  controlador: '',
  tipo_puerta: '',
  puertas_cabina: '',
  puertas_piso: '',
  operador_puertas: '',
  especificaciones_tecnicas: '',
};

const NUMERICOS = ['capacidad_kg', 'velocidad_mpm', 'paradas', 'puertas_cabina', 'puertas_piso'];

const ACCIONAMIENTOS = ['Eléctrico de tracción', 'Eléctrico sin cuarto de máquinas (MRL)', 'Hidráulico'];

export default function FormularioEquipo() {
  const { id } = useParams();
  const [parametros] = useSearchParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [cargando, setCargando] = useState(true);
  const [opcionesEdificios, setOpcionesEdificios] = useState([]);
  const [fotosNuevas, setFotosNuevas] = useState([]);
  const [fotosGuardadas, setFotosGuardadas] = useState([]);
  const { datos, setDatos, cambiar, errores, error, mensajeError, enviando, enviar } = useFormulario({
    ...EQUIPO_VACIO,
    edificio_id: parametros.get('edificio_id') || '',
    ascensor: FICHA_VACIA,
  });

  useEffect(() => {
    const solicitudes = [edificios.listar({ por_pagina: 100 }).then((r) => setOpcionesEdificios(r.datos))];
    if (editando) {
      solicitudes.push(
        equipos.obtener(id).then(({ datos: equipo }) => {
          setFotosGuardadas(equipo.fotos || []);
          setDatos({
            ...Object.fromEntries(Object.keys(EQUIPO_VACIO).map((clave) => [clave, equipo[clave] ?? EQUIPO_VACIO[clave]])),
            ascensor: Object.fromEntries(Object.keys(FICHA_VACIA).map((clave) => [clave, equipo.ascensor?.[clave] ?? ''])),
          });
        }),
      );
    }
    Promise.allSettled(solicitudes).finally(() => setCargando(false));
  }, [editando, id, setDatos]);

  const cambiarFicha = (campo) => (valor) => setDatos((actual) => ({ ...actual, ascensor: { ...actual.ascensor, [campo]: valor } }));
  const errorFicha = (campo) => errores[`ascensor.${campo}`]?.[0];

  async function alEnviar(evento) {
    evento.preventDefault();
    const ficha = limpiarVacios(datos.ascensor);
    NUMERICOS.forEach((campo) => {
      if (ficha[campo] !== null) ficha[campo] = Number(ficha[campo]);
    });
    const cuerpo = {
      ...limpiarVacios({ ...datos, ascensor: undefined }),
      edificio_id: datos.edificio_id ? Number(datos.edificio_id) : null,
      ascensor: ficha,
    };
    const respuesta = await enviar(() => (editando ? equipos.actualizar(id, cuerpo) : equipos.crear(cuerpo)));
    if (respuesta) {
      notificar(editando ? 'Ascensor actualizado.' : 'Ascensor registrado.');
      // Las fotos se suben cuando el ascensor ya existe.
      if (!editando && fotosNuevas.length) {
        try {
          await fotosEquipo.subir(respuesta.datos.id, fotosNuevas);
        } catch (e) {
          const detalle = Object.values(e.errores || {})[0]?.[0] || e.message;
          notificar(`El ascensor se registró, pero las fotos no se subieron: ${detalle}`, 'error');
        }
      }
      navegar(`/equipos/${respuesta.datos.id}`);
    }
  }

  if (cargando) return <Cargando />;

  return (
    <>
      <EncabezadoPagina titulo={editando ? 'Editar ascensor' : 'Nuevo ascensor'} migas={<Link to="/equipos">Ascensores</Link>} />
      <Tarjeta>
        <form className="formulario" onSubmit={alEnviar} noValidate>
          <Alerta>{mensajeError}</Alerta>

          <div className="formulario__seccion">Datos generales del equipo</div>
          <div className="formulario__fila">
            <CampoSelector
              etiqueta="Edificio"
              obligatorio
              valor={String(datos.edificio_id)}
              alCambiar={cambiar('edificio_id')}
              error={error('edificio_id')}
              opciones={opcionesEdificios
                .filter((e) => e.activo || String(e.id) === String(datos.edificio_id))
                .map((e) => ({ valor: String(e.id), texto: `${e.nombre} — ${e.cliente?.nombre ?? ''}` }))}
            />
            <CampoTexto etiqueta="Código" obligatorio valor={datos.codigo} alCambiar={cambiar('codigo')} error={error('codigo')} ayuda="Código único del equipo. Ej. ASC-001" />
          </div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Marca" valor={datos.marca} alCambiar={cambiar('marca')} error={error('marca')} />
            <CampoTexto etiqueta="Modelo" valor={datos.modelo} alCambiar={cambiar('modelo')} error={error('modelo')} />
            <CampoTexto etiqueta="Número de serie" valor={datos.numero_serie} alCambiar={cambiar('numero_serie')} error={error('numero_serie')} />
          </div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Ubicación" valor={datos.ubicacion} alCambiar={cambiar('ubicacion')} error={error('ubicacion')} placeholder="Ej. Torre A — Ascensor 1" />
            <CampoSelector
              etiqueta="Estado"
              vacio={null}
              valor={datos.estado}
              alCambiar={cambiar('estado')}
              error={error('estado')}
              opciones={Object.entries(ESTADOS_EQUIPO).map(([valor, { texto }]) => ({ valor, texto }))}
            />
            <CampoTexto
              etiqueta="Fecha de instalación"
              tipo="date"
              valor={datos.fecha_instalacion}
              alCambiar={cambiar('fecha_instalacion')}
              error={error('fecha_instalacion')}
            />
          </div>

          <div className="formulario__seccion">Ficha técnica del ascensor</div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Capacidad (kg)" obligatorio tipo="number" min="1" valor={datos.ascensor.capacidad_kg} alCambiar={cambiarFicha('capacidad_kg')} error={errorFicha('capacidad_kg')} />
            <CampoTexto etiqueta="Velocidad (m/min)" obligatorio tipo="number" min="0.1" step="0.1" valor={datos.ascensor.velocidad_mpm} alCambiar={cambiarFicha('velocidad_mpm')} error={errorFicha('velocidad_mpm')} />
            <CampoTexto etiqueta="Número de paradas" obligatorio tipo="number" min="1" valor={datos.ascensor.paradas} alCambiar={cambiarFicha('paradas')} error={errorFicha('paradas')} />
          </div>
          <div className="formulario__fila formulario__fila--3">
            <CampoSelector
              etiqueta="Accionamiento"
              valor={datos.ascensor.tipo_traccion}
              alCambiar={cambiarFicha('tipo_traccion')}
              error={errorFicha('tipo_traccion')}
              opciones={[...new Set([...ACCIONAMIENTOS, datos.ascensor.tipo_traccion].filter(Boolean))].map((a) => ({ valor: a, texto: a }))}
            />
            <CampoTexto etiqueta="Motor" valor={datos.ascensor.motor} alCambiar={cambiarFicha('motor')} error={errorFicha('motor')} />
            <CampoTexto etiqueta="Controlador" valor={datos.ascensor.controlador} alCambiar={cambiarFicha('controlador')} error={errorFicha('controlador')} />
          </div>

          <div className="formulario__seccion">Puertas de cabina y de piso</div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Tipo de puerta" valor={datos.ascensor.tipo_puerta} alCambiar={cambiarFicha('tipo_puerta')} error={errorFicha('tipo_puerta')} placeholder="Ej. Automática apertura central" />
            <CampoTexto etiqueta="Puertas de cabina" tipo="number" min="0" valor={datos.ascensor.puertas_cabina} alCambiar={cambiarFicha('puertas_cabina')} error={errorFicha('puertas_cabina')} />
            <CampoTexto etiqueta="Puertas de piso" tipo="number" min="0" valor={datos.ascensor.puertas_piso} alCambiar={cambiarFicha('puertas_piso')} error={errorFicha('puertas_piso')} />
          </div>
          <CampoTexto etiqueta="Operador de puertas" valor={datos.ascensor.operador_puertas} alCambiar={cambiarFicha('operador_puertas')} error={errorFicha('operador_puertas')} />
          <CampoArea
            etiqueta="Especificaciones técnicas adicionales"
            valor={datos.ascensor.especificaciones_tecnicas}
            alCambiar={cambiarFicha('especificaciones_tecnicas')}
            error={errorFicha('especificaciones_tecnicas')}
          />
          <CampoArea etiqueta="Observaciones" valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} />

          <div className="formulario__seccion">Fotos del ascensor</div>
          {editando ? (
            <GaleriaFotos
              equipoId={id}
              fotos={fotosGuardadas}
              editable
              notificar={notificar}
              alCambiar={() => equipos.obtener(id).then(({ datos: equipo }) => setFotosGuardadas(equipo.fotos || []))}
            />
          ) : (
            <SelectorFotos archivos={fotosNuevas} alCambiar={setFotosNuevas} />
          )}

          <div className="formulario__acciones">
            <button type="button" className="boton boton--secundario" onClick={() => navegar(-1)}>
              Cancelar
            </button>
            <button type="submit" className="boton" disabled={enviando}>
              {enviando ? 'Guardando…' : 'Guardar ascensor'}
            </button>
          </div>
        </form>
      </Tarjeta>
    </>
  );
}
