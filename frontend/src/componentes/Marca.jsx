import { useId } from 'react';

/** Logo de SIGMA: la Σ trazada como el recorrido de un ascensor, con la cabina SIGMA en el centro. */
export function Logo({ tamano = 40 }) {
  const id = useId().replace(/:/g, '');
  const metal = `url(#${id}m)`;
  const neon = `url(#${id}n)`;
  const brillo = `url(#${id}g)`;
  const recorrido = 'M96 12L24 36V52L96 76V92L24 112';
  // Versión reducida (32 px o menos): solo la Σ y la cabina, sin rieles, brillos ni letras, para que se lea a 24 px.
  if (tamano <= 32) {
    return (
      <svg className="marca__logo" width={tamano} height={tamano} viewBox="0 0 120 120" aria-hidden="true" style={{ flex: 'none', display: 'block' }}>
        <rect width="120" height="120" rx="28" fill="#150B2D" />
        <path d={recorrido} fill="none" stroke="#AA94E0" strokeWidth="15" strokeLinejoin="bevel" />
        <rect x="40" y="30" width="40" height="78" rx="6" fill="#E6DFF6" stroke="#150B2D" strokeWidth="6" />
        <path d="M60 40V98" stroke="#5A30C5" strokeWidth="6" />
      </svg>
    );
  }
  return (
    <svg className="marca__logo" width={tamano} height={tamano} viewBox="0 0 120 120" aria-hidden="true" style={{ flex: 'none', display: 'block' }}>
      <defs>
        <linearGradient id={`${id}m`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#5B4A82" />
          <stop offset=".42" stopColor="#241640" />
          <stop offset=".7" stopColor="#3A2A5E" />
          <stop offset="1" stopColor="#1B1030" />
        </linearGradient>
        <linearGradient id={`${id}n`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#F2EFFA" />
          <stop offset=".35" stopColor="#AA94E0" />
          <stop offset="1" stopColor="#5A30C5" />
        </linearGradient>
        <radialGradient id={`${id}l`} cx=".5" cy=".5" r=".6">
          <stop offset="0" stopColor="#FFFFFF" />
          <stop offset=".55" stopColor="#E6DFF6" />
          <stop offset="1" stopColor="#9A7FE0" />
        </radialGradient>
        <filter id={`${id}g`} x="-40%" y="-40%" width="180%" height="180%">
          <feGaussianBlur stdDeviation="2.4" />
        </filter>
      </defs>
      <rect width="120" height="120" rx="28" fill="#150B2D" />
      <rect x="1" y="1" width="118" height="118" rx="27" fill="none" stroke={neon} strokeOpacity=".55" strokeWidth="2" />
      <g fill={metal} stroke="#AA94E0" strokeWidth="1.2">
        <rect x="103" y="22" width="4.5" height="50" />
        <rect x="110" y="32" width="4.5" height="40" />
      </g>
      <path d={recorrido} fill="none" stroke="#8161D1" strokeWidth="16" strokeLinejoin="bevel" filter={brillo} opacity=".8" />
      <path d={recorrido} fill="none" stroke={neon} strokeWidth="13" strokeLinejoin="bevel" />
      <path d={recorrido} fill="none" stroke={metal} strokeWidth="9.6" strokeLinejoin="bevel" />
      <rect x="40" y="30" width="40" height="78" rx="4" fill="#9A7FE0" filter={brillo} opacity=".6" />
      <rect x="42" y="32" width="36" height="74" rx="3" fill={metal} stroke={neon} strokeWidth="1.8" />
      <rect x="45.5" y="35" width="29" height="10" rx="2" fill="#0E0820" stroke="#8161D1" strokeWidth=".8" />
      <path d="M55 37.3L58 42.4H52Z" fill="#E6DFF6" />
      <path d="M62 37.6H68L65 42.7Z" fill="#9A7FE0" />
      <rect x="45.5" y="48" width="29" height="55" fill={`url(#${id}l)`} />
      <text x="60" y="49" textAnchor="middle" fill="#2F1967" style={{ font: '400 8.4px var(--brand)' }}>
        {['S', 'I', 'G', 'M', 'A'].map((letra, i) => (
          <tspan key={letra} x="60" dy={i === 0 ? 9.2 : 9.6}>
            {letra}
          </tspan>
        ))}
      </text>
      <rect x="45.5" y="48" width="8" height="55" fill={metal} stroke="#8161D1" strokeWidth=".8" />
      <rect x="66.5" y="48" width="8" height="55" fill={metal} stroke="#8161D1" strokeWidth=".8" />
      <path d="M53.5 48V103M66.5 48V103" stroke="#F2EFFA" strokeWidth="1.1" />
      <path d="M53.5 48V103M66.5 48V103" stroke="#CDC0ED" strokeWidth="3" filter={brillo} opacity=".8" />
      <rect x="38" y="105.5" width="44" height="2.4" rx="1.2" fill="#AA94E0" />
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
