// Campos de formulario con etiqueta, ayuda y mensaje de error de la API.
import { useId, useState } from 'react';
import { Eye, EyeOff } from 'lucide-react';

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
