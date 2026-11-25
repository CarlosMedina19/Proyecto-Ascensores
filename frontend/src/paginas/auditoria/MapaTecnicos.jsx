import { lazy, Suspense, useCallback, useState } from 'react';
import MapaGoogle from './MapaGoogle';

const MapaLeaflet = lazy(() => import('./MapaLeaflet'));

/**
 * Mapa de los técnicos en mantenimiento, siempre en modo oscuro.
 * Con VITE_GOOGLE_MAPS_KEY usa Google Maps; sin clave, o si Google Maps no carga (clave inválida o sin
 * conexión), usa el mapa de respaldo de OpenStreetMap oscurecido.
 */
export default function MapaTecnicos(props) {
  const clave = import.meta.env.VITE_GOOGLE_MAPS_KEY;
  const [respaldo, setRespaldo] = useState(!clave);
  const alFallar = useCallback(() => setRespaldo(true), []);

  if (!respaldo) return <MapaGoogle clave={clave} {...props} alFallar={alFallar} />;
  return (
    <Suspense fallback={<div className="mapa-auditoria mapa-auditoria--oscuro mapa-auditoria--cargando">Cargando mapa…</div>}>
      <MapaLeaflet {...props} />
    </Suspense>
  );
}
