import { useEffect, useState } from 'react';
import { configuracion } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoTexto, Casilla } from '../../componentes/Campos';
import { periodicidad } from '../../utilidades/formato';

const CANALES = [
  { valor: 'database', texto: 'En la aplicación (campana de notificaciones)' },
  { valor: 'mail', texto: 'Correo electrónico' },
];

/**
 * Parámetros del sistema que el administrador ajusta sin tocar el código:
 * canales y días de los avisos (RF-021), alerta de contratos (RF-020) e IVA.
 */
export default function Configuracion() {
  const notificar = useNotificar();
  const [cargado, setCargado] = useState(false);
  const [periodicidades, setPeriodicidades] = useState([]);
  const { datos, setDatos, cambiar, error, errores, mensajeError, enviando, enviar } = useFormulario({
    canales: [],
    dias: '',
    alerta: '',
    iva: '',
    objetivo: '',
    atrapamiento: '',
    certificacion: '',
  });

  useEffect(() => {
    Promise.all([configuracion.obtener(), configuracion.periodicidades()]).then(([{ datos: parametros }, { datos: lista }]) => {
      const valor = (clave) => parametros.find((p) => p.clave === clave)?.valor;
      setDatos({
        canales: valor('notificaciones.canales') || [],
        dias: (valor('notificaciones.dias_aviso') || []).join(', '),
        alerta: String(valor('contratos.dias_alerta_vencimiento') ?? ''),
        iva: String(valor('iva.porcentaje') ?? ''),
        objetivo: String(valor('emergencias.minutos_objetivo') ?? ''),
        atrapamiento: String(valor('emergencias.minutos_atrapamiento') ?? ''),
        certificacion: (valor('certificaciones.dias_alerta') || []).join(', '),
      });
      setPeriodicidades(lista);
      setCargado(true);
    });
  }, [setDatos]);

  function alternarCanal(canal, marcado) {
    setDatos((actual) => ({
      ...actual,
      canales: marcado ? [...actual.canales, canal] : actual.canales.filter((c) => c !== canal),
    }));
  }

  async function guardar(evento) {
    evento.preventDefault();
    const dias = datos.dias
      .split(/[,\s]+/)
      .filter(Boolean)
      .map(Number);
    const respuesta = await enviar(() =>
      configuracion.actualizar({
        notificaciones_canales: datos.canales,
        notificaciones_dias_aviso: dias,
        contratos_dias_alerta_vencimiento: Number(datos.alerta),
        iva_porcentaje: Number(datos.iva),
        emergencias_minutos_objetivo: Number(datos.objetivo),
        emergencias_minutos_atrapamiento: Number(datos.atrapamiento),
        certificaciones_dias_alerta: datos.certificacion
          .split(/[,\s]+/)
          .filter(Boolean)
          .map(Number),
      }),
    );
    if (respuesta) notificar(respuesta.mensaje);
  }

  if (!cargado) return <Cargando />;

  const errorDias = error('notificaciones_dias_aviso') || Object.entries(errores).find(([c]) => c.startsWith('notificaciones_dias_aviso.'))?.[1]?.[0];

  return (
    <>
      <EncabezadoPagina titulo="Configuración" descripcion={['Avisos', 'Contratos', 'Emergencias', 'Certificación anual', 'Impuestos']} />
      <form className="formulario" onSubmit={guardar} noValidate>
        <Alerta>{mensajeError}</Alerta>

        <div className="rejilla rejilla--3 configuracion">
          <Tarjeta titulo="Avisos de mantenimiento">
            <div className="formulario">
              <div className="campo">
                <span className="campo__etiqueta">Canales de envío</span>
                {CANALES.map((canal) => (
                  <Casilla key={canal.valor} etiqueta={canal.texto} valor={datos.canales.includes(canal.valor)} alCambiar={(m) => alternarCanal(canal.valor, m)} />
                ))}
                {error('notificaciones_canales') && <span className="campo__error">{error('notificaciones_canales')}</span>}
              </div>
              <CampoTexto etiqueta="Días de anticipación" ayuda="Separados por coma. 0 = el mismo día." valor={datos.dias} alCambiar={cambiar('dias')} error={errorDias} />
            </div>
          </Tarjeta>

          <Tarjeta titulo="Contratos e impuestos">
            <div className="formulario">
              <CampoTexto
                etiqueta="Alerta antes del vencimiento (días)"
                tipo="number"
                min="1"
                max="365"
                valor={datos.alerta}
                alCambiar={cambiar('alerta')}
                error={error('contratos_dias_alerta_vencimiento')}
              />
              <CampoTexto
                etiqueta="IVA sugerido (%)"
                tipo="number"
                min="0"
                max="100"
                step="0.01"
                valor={datos.iva}
                alCambiar={cambiar('iva')}
                error={error('iva_porcentaje')}
              />
            </div>
          </Tarjeta>

          <Tarjeta titulo="Emergencias">
            <div className="formulario">
              <CampoTexto
                etiqueta="Tiempo objetivo de llegada (min)"
                tipo="number"
                min="5"
                max="1440"
                valor={datos.objetivo}
                alCambiar={cambiar('objetivo')}
                error={error('emergencias_minutos_objetivo')}
              />
              <CampoTexto
                etiqueta="Con personas atrapadas (min)"
                tipo="number"
                min="5"
                max="1440"
                valor={datos.atrapamiento}
                alCambiar={cambiar('atrapamiento')}
                error={error('emergencias_minutos_atrapamiento')}
              />
            </div>
          </Tarjeta>

          <Tarjeta titulo="Certificación anual">
            <CampoTexto
              etiqueta="Alerta antes del vencimiento (días)"
              ayuda="Separados por coma. Por defecto: 60, 30, 15."
              valor={datos.certificacion}
              alCambiar={cambiar('certificacion')}
              error={error('certificaciones_dias_alerta') || Object.entries(errores).find(([c]) => c.startsWith('certificaciones_dias_alerta.'))?.[1]?.[0]}
            />
          </Tarjeta>

          <div className="configuracion__ancha">
            <Tarjeta titulo="Periodicidades disponibles">
              <p className="texto-pequeno texto-suave" style={{ marginTop: 0 }}>
                Cada plan define un precio por periodicidad. Una periodicidad inactiva no se puede asignar.
              </p>
              <div className="precios">
                {periodicidades.map((p) => (
                  <span key={p.id} className="precios__item">
                    {periodicidad(p.meses)} {!p.activo && <span className="texto-suave">(inactiva)</span>}
                  </span>
                ))}
              </div>
            </Tarjeta>
          </div>
        </div>

        <div className="formulario__acciones">
          <button type="submit" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : 'Guardar configuración'}
          </button>
        </div>
      </form>
    </>
  );
}
