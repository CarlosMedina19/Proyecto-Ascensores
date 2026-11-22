import { useEffect, useState } from 'react';
import { RotateCw } from 'lucide-react';
import { autenticacion } from '../api/servicios';
import { CampoTexto } from './Campos';

/** Campo de 6 dígitos para el código de verificación (el celular lo propone desde el SMS o el correo). */
export function CampoCodigo({ valor, alCambiar, error, ayuda, etiqueta = 'Código de verificación', autoFocus = true }) {
  return (
    <CampoTexto
      etiqueta={etiqueta}
      obligatorio
      valor={valor}
      alCambiar={(texto) => alCambiar(texto.replace(/\D/g, '').slice(0, 6))}
      error={error}
      ayuda={ayuda}
      inputMode="numeric"
      autoComplete="one-time-code"
      maxLength={6}
      placeholder="000000"
      autoFocus={autoFocus}
      style={{ fontSize: 22, letterSpacing: '0.4em', textAlign: 'center', fontVariantNumeric: 'tabular-nums' }}
    />
  );
}

/**
 * Botón «Reenviar código» con cuenta regresiva de 60 segundos (el servidor exige la misma espera).
 * Devuelve la respuesta del reenvío (con codigo_demo en el modo de demostración de SMS).
 */
export function ReenviarCodigo({ desafio, proposito = 'inicio_sesion', alReenviar, alError }) {
  const [segundos, setSegundos] = useState(60);
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    if (segundos <= 0) return undefined;
    const temporizador = setTimeout(() => setSegundos((s) => s - 1), 1000);
    return () => clearTimeout(temporizador);
  }, [segundos]);

  async function reenviar() {
    setEnviando(true);
    try {
      const respuesta = await autenticacion.reenviarCodigo(desafio, proposito);
      setSegundos(60);
      alReenviar?.(respuesta);
    } catch (e) {
      alError?.(e);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <button type="button" className="boton boton--fantasma boton--pequeno" onClick={reenviar} disabled={segundos > 0 || enviando}>
      <RotateCw size={14} aria-hidden="true" /> {segundos > 0 ? `Reenviar código en ${segundos} s` : enviando ? 'Enviando…' : 'Reenviar código'}
    </button>
  );
}
