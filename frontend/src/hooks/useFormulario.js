import { useCallback, useState } from 'react';
import { ErrorApi } from '../api/cliente';

/**
 * Estado de un formulario con los errores de validación que devuelve la API.
 */
export function useFormulario(valoresIniciales) {
  const [datos, setDatos] = useState(valoresIniciales);
  const [errores, setErrores] = useState({});
  const [mensajeError, setMensajeError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const cambiar = useCallback(
    (campo) => (valor) => {
      setDatos((actual) => ({ ...actual, [campo]: valor }));
      setErrores((actual) => (actual[campo] ? { ...actual, [campo]: undefined } : actual));
    },
    [],
  );

  const error = useCallback((campo) => errores[campo]?.[0], [errores]);

  /** Ejecuta la acción y captura errores de validación (422) y de negocio. */
  const enviar = useCallback(async (accion) => {
    setEnviando(true);
    setErrores({});
    setMensajeError('');
    try {
      return await accion();
    } catch (e) {
      if (e instanceof ErrorApi) {
        setErrores(e.errores || {});
        setMensajeError(e.message);
      } else {
        setMensajeError('Ocurrió un error inesperado.');
      }
      return undefined;
    } finally {
      setEnviando(false);
    }
  }, []);

  return { datos, setDatos, cambiar, errores, error, mensajeError, enviando, enviar };
}

/** Convierte los campos vacíos a null para que la API los limpie. */
export function limpiarVacios(objeto) {
  return Object.fromEntries(Object.entries(objeto).map(([clave, valor]) => [clave, valor === '' ? null : valor]));
}
