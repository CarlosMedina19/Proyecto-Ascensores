import { useEffect, useState } from 'react';
import { Moon, Sun } from 'lucide-react';
import { Marca } from '../../componentes/Marca';
import { useTema } from '../../hooks/useTema';

const PISOS = 12;
const VENTANAS_ENCENDIDAS = new Set([3, 8, 14, 19, 27, 31, 38, 44, 50, 57, 61, 66]);
const dos = (n) => String(n).padStart(2, '0');

/** Indicador de piso y cabina que recorren el edificio (decorativo; se detiene con movimiento reducido). */
function useRecorrido() {
  const [estado, setEstado] = useState({ piso: 7, sube: true });

  useEffect(() => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return undefined;
    const reloj = setInterval(() => {
      setEstado(({ piso, sube }) => {
        let direccion = sube;
        if (piso >= PISOS) direccion = false;
        else if (piso <= 1) direccion = true;
        else if (Math.random() < 0.2) direccion = !direccion;
        return { piso: piso + (direccion ? 1 : -1), sube: direccion };
      });
    }, 1500);
    return () => clearInterval(reloj);
  }, []);

  return estado;
}

function Edificio({ piso }) {
  const ventanas = [];
  let n = 0;
  for (let fila = 0; fila < PISOS; fila += 1) {
    for (const x of [56, 82, 108, 182, 208, 234]) {
      n += 1;
      const encendida = VENTANAS_ENCENDIDAS.has(n);
      ventanas.push(<rect key={n} x={x} y={88 + fila * 30} width="14" height="10" rx="1.5" fill={encendida ? '#B5D65A' : '#FFFFFF'} fillOpacity={encendida ? 0.85 : 0.1} />);
    }
  }
  return (
    <svg className="acceso__edificio" viewBox="0 0 300 470" aria-hidden="true">
      <defs>
        <linearGradient id="accesoFachada" x1="0" y1="0" x2="1" y2="0">
          <stop offset="0" stopColor="#8161D1" />
          <stop offset="0.5" stopColor="#4E29AA" />
          <stop offset="1" stopColor="#22124A" />
        </linearGradient>
        <linearGradient id="accesoHueco" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#B5D65A" stopOpacity="0" />
          <stop offset="1" stopColor="#B5D65A" stopOpacity="0.2" />
        </linearGradient>
        <clipPath id="accesoRecorte">
          <rect x="132" y="80" width="36" height="370" />
        </clipPath>
      </defs>
      <circle cx="266" cy="30" r="12" fill="#F2EFFA" fillOpacity="0.9" />
      <path d="M150 8V40" stroke="#AA94E0" strokeWidth="2" />
      <circle className="acceso__baliza" cx="150" cy="8" r="3.5" fill="#FF8A80" />
      <path d="M40 452V78H70V40H230V78H260V452Z" fill="url(#accesoFachada)" />
      <path d="M70 40H230" stroke="#FFFFFF" strokeOpacity="0.25" />
      {ventanas}
      <rect x="132" y="80" width="36" height="372" fill="#150B2D" fillOpacity="0.78" />
      <rect x="132" y="80" width="36" height="372" fill="url(#accesoHueco)" />
      <path d="M136 80V452M164 80V452" stroke="#AA94E0" strokeOpacity="0.35" />
      <rect x="116" y="50" width="68" height="22" rx="6" fill="#150B2D" stroke="#3E2188" />
      <text x="150" y="65.5" textAnchor="middle" fill="#B5D65A" style={{ font: "600 11px 'JetBrains Mono', monospace", letterSpacing: '.08em' }}>
        SIGMA
      </text>
      <g clipPath="url(#accesoRecorte)">
        <g className="acceso__cabina" style={{ transform: `translateY(${-(piso - 1) * 30}px)` }}>
          <path d="M150 20V421" stroke="#B5D65A" strokeOpacity="0.55" />
          <rect x="133" y="417" width="34" height="32" rx="5" fill="#B5D65A" fillOpacity="0.22" />
          <rect x="137" y="421" width="26" height="25" rx="3" fill="#B5D65A" />
          <path d="M150 421V446" stroke="#3B4F0B" strokeWidth="1.4" />
        </g>
      </g>
      <rect x="16" y="452" width="268" height="3" rx="1.5" fill="#AA94E0" fillOpacity="0.45" />
    </svg>
  );
}

/** Diseño compartido de las pantallas de acceso (ingreso, recuperación y restablecimiento). */
export default function DisenoAcceso({ children }) {
  const { piso, sube } = useRecorrido();
  const { tema, alternar } = useTema();

  return (
    <div className="acceso">
      <section className="acceso__presentacion">
        <Marca subtitulo="Sistema Integral de Gestión y Mantenimiento de Ascensores" />
        <div className="acceso__escena" aria-hidden="true">
          <div className="acceso__indicador">
            <small>Piso</small>
            <b>{dos(piso)}</b>
            <span className={`acceso__flecha${sube ? '' : ' baja'}`}>
              <svg width="22" height="16" viewBox="0 0 22 16">
                <path d="M11 1l10 14H1z" fill="currentColor" />
              </svg>
            </span>
          </div>
          <Edificio piso={piso} />
          <div className="acceso__aviso acceso__aviso--1">
            <span className="caja" style={{ background: 'rgba(154,127,224,.16)', color: '#CDC0ED' }}>
              OT
            </span>
            <div>
              <b>Preventivo en proceso</b>
              Checklist por secciones del ascensor
            </div>
          </div>
          <div className="acceso__aviso acceso__aviso--2">
            <span className="caja" style={{ background: 'rgba(242,174,61,.16)', color: '#F2AE3D' }}>
              R
            </span>
            <div>
              <b>Puerta de piso en Regular</b>
              Observación y correctivo derivado
            </div>
          </div>
        </div>
        <div>
          <h1>
            Cada ascensor, <em>cada piso</em>, bajo control.
          </h1>
          <p>Programación de mantenimientos, checklist digital en campo, informes firmados en PDF y trazabilidad de cada orden.</p>
        </div>
        <div className="acceso__funciones">
          <div>
            <b>Agenda</b>sin cruces de técnicos
          </div>
          <div>
            <b>Checklist</b>B · R · M por sección
          </div>
          <div>
            <b>Informes</b>PDF con firmas
          </div>
        </div>
      </section>
      <section className="acceso__formulario">
        <div className="acceso__superior">
          <button
            type="button"
            className="boton-icono"
            onClick={alternar}
            aria-label={tema === 'oscuro' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            title={tema === 'oscuro' ? 'Modo claro' : 'Modo oscuro'}
          >
            {tema === 'oscuro' ? <Sun size={18} /> : <Moon size={18} />}
          </button>
        </div>
        <div className="acceso__caja">{children}</div>
        <footer className="acceso__pie">SENA · Proyecto formativo SIGMA</footer>
      </section>
    </div>
  );
}
