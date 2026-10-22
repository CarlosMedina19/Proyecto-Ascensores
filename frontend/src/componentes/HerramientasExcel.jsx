import { useRef, useState } from 'react';
import { CheckCircle2, Download, FileSpreadsheet, RotateCcw, Upload } from 'lucide-react';
import { excel } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { useNotificar } from '../contexto/NotificacionesContexto';
import { Alerta, Modal } from './Basicos';

const TAMANO_MAXIMO = 10 * 1024 * 1024;
const EXTENSIONES = ['xlsx', 'csv'];

/**
 * Botones de Excel de un módulo: exportar los datos actuales, descargar la plantilla vacía e importar
 * (vista previa con errores y duplicados antes de guardar).
 */
export default function HerramientasExcel({ modulo, alImportar, filtros }) {
  const { tienePermiso } = useAutenticacion();
  const notificar = useNotificar();
  const [ocupado, setOcupado] = useState('');
  const [importando, setImportando] = useState(false);

  async function descargar(accion, clave) {
    setOcupado(clave);
    try {
      await accion();
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setOcupado('');
    }
  }

  return (
    <>
      <div className="herramientas-excel" role="group" aria-label="Excel">
        <button
          type="button"
          className="boton boton--secundario"
          onClick={() => descargar(() => excel.exportar(modulo, filtros), 'exportar')}
          disabled={!!ocupado}
          aria-label="Exportar a Excel"
          title="Exportar a Excel"
        >
          <FileSpreadsheet size={16} aria-hidden="true" /> {ocupado === 'exportar' ? 'Exportando…' : 'Exportar'}
        </button>
        {tienePermiso('datos.importar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setImportando(true)} aria-label="Importar desde Excel" title="Importar desde Excel">
            <Upload size={16} aria-hidden="true" /> Importar
          </button>
        )}
        <button
          type="button"
          className="boton boton--fantasma"
          onClick={() => descargar(excel.plantilla, 'plantilla')}
          disabled={!!ocupado}
          aria-label="Descargar plantilla vacía"
          title="Descargar plantilla vacía"
        >
          <Download size={16} aria-hidden="true" /> {ocupado === 'plantilla' ? 'Descargando…' : 'Plantilla'}
        </button>
      </div>
      {importando && (
        <ModalImportacion
          alCerrar={(importo) => {
            setImportando(false);
            if (importo) alImportar?.();
          }}
        />
      )}
    </>
  );
}

function ModalImportacion({ alCerrar }) {
  const notificar = useNotificar();
  const entrada = useRef(null);
  const [archivo, setArchivo] = useState(null);
  const [arrastrando, setArrastrando] = useState(false);
  const [vista, setVista] = useState(null);
  const [final, setFinal] = useState(null);
  const [error, setError] = useState('');
  const [trabajando, setTrabajando] = useState('');

  function elegir(nuevo) {
    setError('');
    if (!nuevo) return;
    const extension = nuevo.name.split('.').pop()?.toLowerCase();
    if (!EXTENSIONES.includes(extension)) {
      setError('Elija un archivo de Excel (.xlsx) o CSV.');
      return;
    }
    if (nuevo.size > TAMANO_MAXIMO) {
      setError('El archivo pesa más de 10 MB. Divídalo en varios archivos e impórtelos por separado.');
      return;
    }
    setArchivo(nuevo);
    revisar(nuevo);
  }

  async function revisar(seleccionado) {
    setTrabajando('revisar');
    setVista(null);
    try {
      const respuesta = await excel.previsualizar(seleccionado);
      setVista(respuesta.datos);
    } catch (e) {
      setError(Object.values(e.errores || {})[0]?.[0] || e.message);
    } finally {
      setTrabajando('');
    }
  }

  async function confirmar() {
    setTrabajando('confirmar');
    try {
      const respuesta = await excel.confirmar(vista.id);
      setFinal(respuesta.datos);
      notificar(respuesta.datos.mensaje);
    } catch (e) {
      setError(e.message);
    } finally {
      setTrabajando('');
    }
  }

  async function descargarErrores() {
    try {
      await excel.errores((final || vista).id);
    } catch (e) {
      notificar(e.message, 'error');
    }
  }

  function reiniciar() {
    setArchivo(null);
    setVista(null);
    setFinal(null);
    setError('');
    if (entrada.current) entrada.current.value = '';
  }

  const datos = final || vista;
  const conProblemas = datos ? datos.totales.errores + datos.totales.duplicados : 0;

  const pie = final ? (
    <>
      {conProblemas > 0 && (
        <button type="button" className="boton boton--secundario" onClick={descargarErrores}>
          <Download size={16} aria-hidden="true" /> Descargar errores
        </button>
      )}
      <button type="button" className="boton" onClick={() => alCerrar(true)}>
        Cerrar
      </button>
    </>
  ) : vista ? (
    <>
      <button type="button" className="boton boton--fantasma" onClick={reiniciar}>
        <RotateCcw size={16} aria-hidden="true" /> Corregir y volver a cargar
      </button>
      {conProblemas > 0 && (
        <button type="button" className="boton boton--secundario" onClick={descargarErrores}>
          <Download size={16} aria-hidden="true" /> Descargar errores
        </button>
      )}
      <button type="button" className="boton" onClick={confirmar} disabled={vista.totales.validas === 0 || trabajando === 'confirmar'}>
        {trabajando === 'confirmar' ? 'Importando…' : conProblemas > 0 ? 'Importar solo las filas válidas' : 'Importar'}
      </button>
    </>
  ) : (
    <button type="button" className="boton boton--secundario" onClick={() => alCerrar(false)}>
      Cancelar
    </button>
  );

  return (
    <Modal titulo={final ? 'Importación terminada' : 'Importar desde Excel'} abierto alCerrar={() => alCerrar(!!final)} pie={pie} ancho>
      {error && <Alerta>{error}</Alerta>}

      {!vista && !final && (
        <>
          <p className="texto-suave" style={{ marginTop: 0 }}>
            Use la plantilla de SIGMA (.xlsx) o un CSV con sus encabezados. Antes de guardar verá qué filas son válidas y cuáles tienen errores.
          </p>
          <label
            className={`zona-carga${arrastrando ? ' activa' : ''}`}
            onDragOver={(e) => {
              e.preventDefault();
              setArrastrando(true);
            }}
            onDragLeave={() => setArrastrando(false)}
            onDrop={(e) => {
              e.preventDefault();
              setArrastrando(false);
              elegir(e.dataTransfer.files?.[0]);
            }}
          >
            <input ref={entrada} type="file" accept=".xlsx,.csv" className="oculto-accesible" onChange={(e) => elegir(e.target.files?.[0])} />
            <Upload size={26} aria-hidden="true" />
            <b>{trabajando === 'revisar' ? `Revisando ${archivo?.name}…` : 'Arrastre el archivo aquí o haga clic para elegirlo'}</b>
            <span>Excel (.xlsx) o CSV · máximo 10 MB</span>
          </label>
        </>
      )}

      {datos && (
        <div className="importacion">
          {final ? (
            <div className="importacion__final" role="status">
              <CheckCircle2 size={22} aria-hidden="true" />
              <p>{final.mensaje}</p>
            </div>
          ) : (
            <p className="texto-suave" style={{ marginTop: 0 }}>
              <b>{vista.archivo}</b>: {vista.totales.validas} {vista.totales.validas === 1 ? 'fila válida' : 'filas válidas'}
              {vista.totales.errores > 0 && `, ${vista.totales.errores} con error`}
              {vista.totales.duplicados > 0 && `, ${vista.totales.duplicados} ${vista.totales.duplicados === 1 ? 'duplicada' : 'duplicadas'}`}. Todavía no se ha guardado nada.
            </p>
          )}
          {datos.avisos?.map((aviso) => (
            <Alerta key={aviso} tipo="info">
              {aviso}
            </Alerta>
          ))}

          <div className="tabla-contenedor importacion__tabla">
            <table className="tabla">
              <thead>
                <tr>
                  <th scope="col">Hoja</th>
                  <th scope="col" className="numero">Nuevas</th>
                  <th scope="col" className="numero">Se actualizan</th>
                  <th scope="col" className="numero">Ya existen</th>
                  <th scope="col" className="numero">Con error</th>
                  <th scope="col" className="numero">Duplicadas</th>
                </tr>
              </thead>
              <tbody>
                {datos.hojas.map((hoja) => (
                  <tr key={hoja.nombre}>
                    <td>
                      <b>{hoja.nombre}</b>
                    </td>
                    <td className="numero">{hoja.creados}</td>
                    <td className="numero">{hoja.actualizados}</td>
                    <td className="numero">{hoja.existentes}</td>
                    <td className={`numero${hoja.errores ? ' texto-peligro' : ''}`}>{hoja.errores}</td>
                    <td className={`numero${hoja.duplicados ? ' texto-alerta' : ''}`}>{hoja.duplicados}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!final && datos.derivados.puertas_creadas > 0 && (
            <p className="texto-pequeno texto-suave">Se crearán {datos.derivados.puertas_creadas} puertas de piso según las paradas de los ascensores nuevos.</p>
          )}

          {datos.errores.length > 0 && <ListaProblemas titulo="Errores encontrados" filas={datos.errores} />}
          {datos.duplicados.length > 0 && <ListaProblemas titulo="Duplicados detectados" filas={datos.duplicados} />}
        </div>
      )}
    </Modal>
  );
}

function ListaProblemas({ titulo, filas }) {
  return (
    <section className="importacion__problemas" aria-label={titulo}>
      <h3>
        {titulo} <span className="tabular">{filas.length}</span>
      </h3>
      <div className="tabla-contenedor">
        <table className="tabla tabla--compacta">
          <thead>
            <tr>
              <th scope="col">Hoja</th>
              <th scope="col" className="numero">Fila</th>
              <th scope="col">Columna</th>
              <th scope="col">Motivo</th>
            </tr>
          </thead>
          <tbody>
            {filas.slice(0, 200).map((fila, i) => (
              <tr key={`${fila.hoja}-${fila.fila}-${i}`}>
                <td>{fila.hoja}</td>
                <td className="numero">{fila.fila}</td>
                <td>{fila.columna || '—'}</td>
                <td className="importacion__motivo">{fila.motivo}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {filas.length > 200 && <p className="texto-pequeno texto-suave">Se muestran las primeras 200. Descargue los errores para ver todas.</p>}
    </section>
  );
}
