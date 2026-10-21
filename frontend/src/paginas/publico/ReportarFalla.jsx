import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { CircleCheck, Siren } from 'lucide-react';
import { publico } from '../../api/servicios';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando } from '../../componentes/Basicos';
import { CampoArea, CampoTexto } from '../../componentes/Campos';
import { Marca } from '../../componentes/Marca';
import { TIPOS_EMERGENCIA } from '../../utilidades/servicio';

/**
 * Página que abre el QR pegado en la cabina: cualquier persona reporta una falla sin iniciar sesión.
 * Pensada para el teléfono: un solo paso, botones grandes y el número de seguimiento al final.
 */
export default function ReportarFalla() {
  const { codigo } = useParams();
  const [ascensor, setAscensor] = useState(null);
  const [noExiste, setNoExiste] = useState(false);
  const [recibido, setRecibido] = useState(null);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    tipo: '',
    descripcion: '',
    reportante_nombre: '',
    reportante_telefono: '',
  });

  useEffect(() => {
    publico
      .ascensor(codigo)
      .then((r) => setAscensor(r.datos))
      .catch(() => setNoExiste(true));
  }, [codigo]);

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() => publico.reportar(codigo, datos));
    if (respuesta) setRecibido(respuesta);
  }

  return (
    <main className="reporte-publico">
      <div className="reporte-publico__caja">
        <Marca subtitulo="Reporte de fallas" />
        {noExiste ? (
          <Alerta>No encontramos este ascensor. Revise que el código QR esté completo o comuníquese con la administración del edificio.</Alerta>
        ) : !ascensor ? (
          <Cargando />
        ) : recibido ? (
          <div className="reporte-publico__recibido" role="status">
            <CircleCheck size={40} aria-hidden="true" />
            <h1>Recibimos su reporte</h1>
            <p>{recibido.mensaje}</p>
            <p className="texto-suave">
              Número de seguimiento: <b>{recibido.datos.numero}</b>
            </p>
          </div>
        ) : (
          <form className="formulario" onSubmit={alEnviar} noValidate>
            <div>
              <h1 className="reporte-publico__titulo">Reportar una falla</h1>
              <p className="texto-suave" style={{ margin: 0 }}>
                Ascensor <b>{ascensor.codigo}</b>
                {ascensor.nombre ? ` · ${ascensor.nombre}` : ''} · {ascensor.edificio}
              </p>
            </div>
            <Alerta>{mensajeError}</Alerta>
            <fieldset className="reporte-publico__tipos">
              <legend>¿Qué pasa?</legend>
              {Object.entries(TIPOS_EMERGENCIA).map(([valor, texto]) => (
                <label key={valor} className={`reporte-publico__tipo${datos.tipo === valor ? ' activo' : ''}${valor === 'atrapamiento' ? ' urgente' : ''}`}>
                  <input type="radio" name="tipo" value={valor} checked={datos.tipo === valor} onChange={() => cambiar('tipo')(valor)} />
                  {valor === 'atrapamiento' && <Siren size={18} aria-hidden="true" />}
                  {texto}
                </label>
              ))}
              {error('tipo') && <span className="campo__error">Elija qué pasa con el ascensor.</span>}
            </fieldset>
            {datos.tipo === 'atrapamiento' && (
              <Alerta tipo="alerta">Si hay personas atrapadas, no intente abrir las puertas. Hable con ellas por el citófono y espere al técnico.</Alerta>
            )}
            <CampoTexto etiqueta="Su nombre" obligatorio autoComplete="name" valor={datos.reportante_nombre} alCambiar={cambiar('reportante_nombre')} error={error('reportante_nombre')} />
            <CampoTexto
              etiqueta="Teléfono de contacto"
              obligatorio
              tipo="tel"
              autoComplete="tel"
              valor={datos.reportante_telefono}
              alCambiar={cambiar('reportante_telefono')}
              error={error('reportante_telefono')}
              ayuda="Lo llamaremos si necesitamos más información."
            />
            <CampoArea etiqueta="Detalles (opcional)" rows={3} valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} placeholder="Piso, ruido, puertas…" />
            <button type="submit" className="boton boton--bloque boton--alto" disabled={enviando || !datos.tipo}>
              {enviando ? 'Enviando…' : 'Enviar reporte'}
            </button>
          </form>
        )}
      </div>
    </main>
  );
}
