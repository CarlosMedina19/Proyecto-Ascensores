// Campos de formulario con etiqueta, ayuda y mensaje de error de la API.
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { ChevronDown, Eye, EyeOff, X } from 'lucide-react';

function Envoltura({ id, etiqueta, obligatorio, ayuda, error, children }) {
  return (
    <div className="campo">
      {etiqueta && (
        <label className="campo__etiqueta" htmlFor={id}>
          {etiqueta} {obligatorio && <span className="campo__obligatorio">*</span>}
        </label>
      )}
      {children}
      {error ? (
        <span className="campo__error" id={`${id}-error`}>
          {error}
        </span>
      ) : (
        ayuda && <span className="campo__ayuda">{ayuda}</span>
      )}
    </div>
  );
}

function atributosComunes(id, error, obligatorio) {
  return {
    id,
    className: 'campo__control',
    'aria-invalid': error ? 'true' : undefined,
    'aria-describedby': error ? `${id}-error` : undefined,
    required: obligatorio,
  };
}

export function CampoTexto({ etiqueta, obligatorio, ayuda, error, valor, alCambiar, tipo = 'text', ...resto }) {
  const id = useId();
  return (
    <Envoltura id={id} etiqueta={etiqueta} obligatorio={obligatorio} ayuda={ayuda} error={error}>
      <input
        {...atributosComunes(id, error, obligatorio)}
        type={tipo}
        value={valor ?? ''}
        onChange={(e) => alCambiar(e.target.value)}
        {...resto}
      />
    </Envoltura>
  );
}

export function CampoArea({ etiqueta, obligatorio, ayuda, error, valor, alCambiar, ...resto }) {
  const id = useId();
  return (
    <Envoltura id={id} etiqueta={etiqueta} obligatorio={obligatorio} ayuda={ayuda} error={error}>
      <textarea
        {...atributosComunes(id, error, obligatorio)}
        value={valor ?? ''}
        onChange={(e) => alCambiar(e.target.value)}
        {...resto}
      />
    </Envoltura>
  );
}

/** opciones: [{ valor, texto }] */
export function CampoSelector({ etiqueta, obligatorio, ayuda, error, valor, alCambiar, opciones, vacio = 'Seleccione…', ...resto }) {
  const id = useId();
  return (
    <Envoltura id={id} etiqueta={etiqueta} obligatorio={obligatorio} ayuda={ayuda} error={error}>
      <select
        {...atributosComunes(id, error, obligatorio)}
        value={valor ?? ''}
        onChange={(e) => alCambiar(e.target.value)}
        {...resto}
      >
        {vacio !== null && <option value="">{vacio}</option>}
        {opciones.map((opcion) => (
          <option key={opcion.valor} value={opcion.valor}>
            {opcion.texto}
          </option>
        ))}
      </select>
    </Envoltura>
  );
}

/** Texto sin tildes ni mayúsculas para comparar: "Ibagué" y "ibague" coinciden. */
export const normalizarTexto = (texto) =>
  String(texto ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .trim();

const MAXIMO_RESULTADOS = 60;

/**
 * Selector con búsqueda: se escribe parte del nombre (sin importar tildes ni el orden de las palabras)
 * y se elige de la lista con el mouse o con las flechas y Enter. opciones: [{ valor, texto, detalle }].
 */
export function CampoBuscador({ etiqueta, obligatorio, ayuda, error, valor, alCambiar, opciones, placeholder = 'Escriba para buscar…', sinResultados = 'No hay coincidencias.', disabled, ...resto }) {
  const id = useId();
  const lista = `${id}-lista`;
  const seleccionada = opciones.find((o) => String(o.valor) === String(valor ?? ''));
  const [texto, setTexto] = useState(seleccionada?.texto ?? '');
  const [abierto, setAbierto] = useState(false);
  const [activa, setActiva] = useState(0);
  const contenedor = useRef(null);

  // Si el valor cambia desde fuera (o llegan las opciones), se muestra su nombre.
  useEffect(() => {
    if (!abierto) setTexto(seleccionada?.texto ?? '');
  }, [seleccionada?.texto, abierto]);

  const filtradas = useMemo(() => {
    const palabras = normalizarTexto(abierto && texto !== seleccionada?.texto ? texto : '').split(/\s+/).filter(Boolean);
    const coinciden = palabras.length
      ? opciones.filter((o) => {
          const base = normalizarTexto(`${o.texto} ${o.detalle ?? ''}`);
          return palabras.every((p) => base.includes(p));
        })
      : opciones;
    // Primero las que empiezan por lo escrito.
    const inicio = palabras[0];
    return (inicio ? [...coinciden].sort((a, b) => normalizarTexto(b.texto).startsWith(inicio) - normalizarTexto(a.texto).startsWith(inicio)) : coinciden).slice(0, MAXIMO_RESULTADOS);
  }, [opciones, texto, abierto, seleccionada?.texto]);

  function elegir(opcion) {
    alCambiar(opcion ? String(opcion.valor) : '');
    setTexto(opcion?.texto ?? '');
    setAbierto(false);
  }

  function alSalir(evento) {
    if (contenedor.current?.contains(evento.relatedTarget)) return;
    setAbierto(false);
    // Lo escrito que no corresponde a una opción se descarta (o elige la única coincidencia).
    if (texto !== (seleccionada?.texto ?? '')) {
      const exacta = opciones.find((o) => normalizarTexto(o.texto) === normalizarTexto(texto));
      if (exacta) elegir(exacta);
      else if (!texto.trim()) elegir(null);
      else setTexto(seleccionada?.texto ?? '');
    }
  }

  function alTeclear(evento) {
    if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
      evento.preventDefault();
      setAbierto(true);
      setActiva((a) => Math.max(0, Math.min(filtradas.length - 1, a + (evento.key === 'ArrowDown' ? 1 : -1))));
    } else if (evento.key === 'Enter' && abierto) {
      evento.preventDefault();
      if (filtradas[activa]) elegir(filtradas[activa]);
    } else if (evento.key === 'Escape' && abierto) {
      evento.stopPropagation();
      setAbierto(false);
      setTexto(seleccionada?.texto ?? '');
    }
  }

  return (
    <Envoltura id={id} etiqueta={etiqueta} obligatorio={obligatorio} ayuda={ayuda} error={error}>
      <div className="combo" ref={contenedor} onBlur={alSalir}>
        <input
          {...atributosComunes(id, error, obligatorio)}
          role="combobox"
          aria-expanded={abierto}
          aria-controls={lista}
          aria-autocomplete="list"
          aria-activedescendant={abierto && filtradas[activa] ? `${lista}-${activa}` : undefined}
          autoComplete="off"
          placeholder={placeholder}
          value={texto}
          disabled={disabled}
          onChange={(e) => {
            setTexto(e.target.value);
            setAbierto(true);
            setActiva(0);
          }}
          onFocus={(e) => e.target.select()}
          onClick={() => setAbierto(true)}
          onKeyDown={alTeclear}
          {...resto}
        />
        {seleccionada && !disabled && !obligatorio ? (
          <button type="button" className="combo__boton" onClick={() => elegir(null)} aria-label={`Quitar ${etiqueta ?? 'selección'}`}>
            <X size={14} aria-hidden="true" />
          </button>
        ) : (
          <ChevronDown size={16} className="combo__flecha" aria-hidden="true" />
        )}
        {abierto && !disabled && (
          <ul className="combo__lista" id={lista} role="listbox">
            {filtradas.length ? (
              filtradas.map((o, k) => (
                <li
                  key={o.valor}
                  id={`${lista}-${k}`}
                  role="option"
                  aria-selected={k === activa}
                  className={`combo__opcion${String(o.valor) === String(valor ?? '') ? ' combo__opcion--elegida' : ''}`}
                  onMouseDown={(e) => e.preventDefault()}
                  onMouseEnter={() => setActiva(k)}
                  onClick={() => elegir(o)}
                >
                  {o.texto}
                  {o.detalle && <small>{o.detalle}</small>}
                </li>
              ))
            ) : (
              <li className="combo__vacio">{sinResultados}</li>
            )}
          </ul>
        )}
      </div>
    </Envoltura>
  );
}

export function CampoContrasena({ etiqueta = 'Contraseña', obligatorio, ayuda, error, valor, alCambiar, ...resto }) {
  const id = useId();
  const [visible, setVisible] = useState(false);
  return (
    <Envoltura id={id} etiqueta={etiqueta} obligatorio={obligatorio} ayuda={ayuda} error={error}>
      <div className="contrasena">
        <input
          {...atributosComunes(id, error, obligatorio)}
          type={visible ? 'text' : 'password'}
          value={valor ?? ''}
          onChange={(e) => alCambiar(e.target.value)}
          {...resto}
        />
        <button
          type="button"
          className="boton boton--fantasma contrasena__ver"
          onClick={() => setVisible((v) => !v)}
          aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
        >
          {visible ? <EyeOff size={16} /> : <Eye size={16} />}
        </button>
      </div>
    </Envoltura>
  );
}

export function Casilla({ etiqueta, valor, alCambiar, ...resto }) {
  return (
    <label className="casilla">
      <input type="checkbox" checked={Boolean(valor)} onChange={(e) => alCambiar(e.target.checked)} {...resto} />
      {etiqueta}
    </label>
  );
}
