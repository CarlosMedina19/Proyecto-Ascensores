import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowUpDown, Briefcase, Building2, ClipboardCheck, FileText, Search, X } from 'lucide-react';
import { busqueda } from '../api/servicios';

const GRUPOS = [
  { clave: 'ascensores', titulo: 'Ascensores', icono: ArrowUpDown },
  { clave: 'ordenes', titulo: 'Órdenes de trabajo', icono: ClipboardCheck },
  { clave: 'edificios', titulo: 'Edificios', icono: Building2 },
  { clave: 'clientes', titulo: 'Clientes', icono: Briefcase },
  { clave: 'contratos', titulo: 'Contratos', icono: FileText },
];

const esMac = typeof navigator !== 'undefined' && /mac/i.test(navigator.platform || '');

/**
 * Buscador del encabezado. Ctrl+K (⌘K en Mac) lo enfoca; flechas y Enter para elegir.
 * Busca ascensores, órdenes, edificios, clientes y contratos según los permisos del usuario.
 */
export default function BuscadorGlobal() {
  const navegar = useNavigate();
  const entrada = useRef(null);
  const contenedor = useRef(null);
  const [texto, setTexto] = useState('');
  const [resultados, setResultados] = useState(null);
  const [cargando, setCargando] = useState(false);
  const [abierto, setAbierto] = useState(false);
  const [activo, setActivo] = useState(0);

  // Atajo de teclado global.
  useEffect(() => {
    const alTecla = (evento) => {
      if ((evento.ctrlKey || evento.metaKey) && evento.key.toLowerCase() === 'k') {
        evento.preventDefault();
        entrada.current?.focus();
        entrada.current?.select();
        setAbierto(true);
      }
    };
    window.addEventListener('keydown', alTecla);
    return () => window.removeEventListener('keydown', alTecla);
  }, []);

  useEffect(() => {
    const alClic = (evento) => contenedor.current && !contenedor.current.contains(evento.target) && setAbierto(false);
    document.addEventListener('mousedown', alClic);
    return () => document.removeEventListener('mousedown', alClic);
  }, []);

  // Espera a que el usuario deje de escribir.
  useEffect(() => {
    const consulta = texto.trim();
    if (consulta.length < 2) {
      setResultados(null);
      return undefined;
    }
    let vigente = true;
    setCargando(true);
    const temporizador = setTimeout(() => {
      busqueda
        .buscar(consulta)
        .then((r) => vigente && setResultados(r.datos || {}))
        .catch(() => vigente && setResultados({}))
        .finally(() => vigente && setCargando(false));
    }, 250);
    return () => {
      vigente = false;
      clearTimeout(temporizador);
    };
  }, [texto]);

  const opciones = useMemo(
    () => GRUPOS.flatMap((grupo) => (resultados?.[grupo.clave] || []).map((r) => ({ ...r, grupo }))),
    [resultados],
  );

  useEffect(() => setActivo(0), [opciones]);

  function elegir(opcion) {
    if (!opcion) return;
    navegar(opcion.enlace);
    setTexto('');
    setAbierto(false);
    entrada.current?.blur();
  }

  function alTeclear(evento) {
    if (evento.key === 'ArrowDown') {
      evento.preventDefault();
      setActivo((i) => Math.min(i + 1, opciones.length - 1));
    } else if (evento.key === 'ArrowUp') {
      evento.preventDefault();
      setActivo((i) => Math.max(i - 1, 0));
    } else if (evento.key === 'Enter') {
      evento.preventDefault();
      elegir(opciones[activo]);
    } else if (evento.key === 'Escape') {
      setAbierto(false);
      entrada.current?.blur();
    }
  }

  const mostrarPanel = abierto && texto.trim().length >= 2;

  return (
    // En el celular el buscador ocupa todo el encabezado mientras está abierto (ver .buscador--abierto).
    <div className={`buscador${abierto ? ' buscador--abierto' : ''}`} ref={contenedor}>
      <Search size={16} className="buscador__icono" aria-hidden="true" />
      <input
        ref={entrada}
        className="buscador__entrada"
        type="search"
        placeholder="Buscar ascensor, orden o cliente"
        value={texto}
        onChange={(e) => {
          setTexto(e.target.value);
          setAbierto(true);
        }}
        onFocus={() => setAbierto(true)}
        onKeyDown={alTeclear}
        role="combobox"
        aria-expanded={mostrarPanel}
        aria-controls="buscador-resultados"
        aria-activedescendant={mostrarPanel && opciones[activo] ? `buscador-opcion-${activo}` : undefined}
        aria-label="Buscar en SIGMA"
      />
      <button
        type="button"
        className="buscador__cerrar"
        aria-label="Cerrar la búsqueda"
        onClick={() => {
          setTexto('');
          setAbierto(false);
          entrada.current?.blur();
        }}
      >
        <X size={16} />
      </button>
      <kbd className="buscador__atajo" aria-hidden="true">
        {esMac ? '⌘ K' : 'Ctrl K'}
      </kbd>

      {mostrarPanel && (
        <div className="buscador__panel" id="buscador-resultados" role="listbox" aria-label="Resultados de la búsqueda">
          {cargando && !resultados && <p className="buscador__vacio">Buscando…</p>}
          {resultados && !opciones.length && <p className="buscador__vacio">Sin resultados para «{texto.trim()}».</p>}
          {GRUPOS.map((grupo) => {
            const lista = opciones.filter((o) => o.grupo.clave === grupo.clave);
            if (!lista.length) return null;
            return (
              <div key={grupo.clave} role="group" aria-label={grupo.titulo}>
                <div className="buscador__grupo">{grupo.titulo}</div>
                {lista.map((opcion) => {
                  const indice = opciones.indexOf(opcion);
                  return (
                    <div
                      key={`${grupo.clave}-${opcion.id}`}
                      id={`buscador-opcion-${indice}`}
                      role="option"
                      aria-selected={indice === activo}
                      className={`buscador__opcion${indice === activo ? ' activa' : ''}`}
                      onMouseEnter={() => setActivo(indice)}
                      onMouseDown={(e) => {
                        e.preventDefault();
                        elegir(opcion);
                      }}
                    >
                      <grupo.icono size={16} aria-hidden="true" />
                      <span>
                        <b>{opcion.titulo}</b>
                        {opcion.detalle && <small>{opcion.detalle}</small>}
                      </span>
                    </div>
                  );
                })}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
