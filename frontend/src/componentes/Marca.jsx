import { useId } from 'react';

/** Logo de SIGMA: la Σ trazada como el recorrido de un ascensor, con la cabina en cian. */
export function Logo({ tamano = 36 }) {
  const id = useId().replace(/:/g, '');
  return (
    <svg className="marca__logo" width={tamano} height={tamano} viewBox="0 0 40 40" aria-hidden="true" style={{ flex: 'none', display: 'block' }}>
      <defs>
        <linearGradient id={`${id}f`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#9A6BFF" />
          <stop offset=".55" stopColor="#6B3FD4" />
          <stop offset="1" stopColor="#3A1A8F" />
        </linearGradient>
        <linearGradient id={`${id}b`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#fff" stopOpacity=".28" />
          <stop offset=".5" stopColor="#fff" stopOpacity="0" />
        </linearGradient>
      </defs>
      <rect width="40" height="40" rx="11" fill={`url(#${id}f)`} />
      <rect x=".5" y=".5" width="39" height="39" rx="10.5" fill={`url(#${id}b)`} stroke="#fff" strokeOpacity=".18" />
      <path d="M28 11.5H12.5l8 8.5-8 8.5H28" fill="none" stroke="#fff" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round" />
      <rect x="24.5" y="16.5" width="7" height="7" rx="2" fill="#38D3EE" />
    </svg>
  );
}

export function Marca({ subtitulo = 'Gestión de ascensores' }) {
  return (
    <div className="marca">
      <Logo />
      <div>
        <div className="marca__nombre">SIGMA</div>
        {subtitulo && <div className="marca__subtitulo">{subtitulo}</div>}
      </div>
    </div>
  );
}
