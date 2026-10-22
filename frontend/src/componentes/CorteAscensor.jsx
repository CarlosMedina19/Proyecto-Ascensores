/**
 * Esquema en corte de un ascensor (cuarto de máquinas, hueco, cabina, contrapeso,
 * puertas de piso y foso). Cada zona se colorea con un estado:
 * 'B' (bueno), 'R' (regular), 'M' (malo) o 'P' (pendiente / sin dato).
 *
 * En la fase 1 se usa con el estado general del equipo; en la fase 4 recibirá
 * el resultado del checklist por zona.
 */
const COLORES = {
  B: 'var(--color-exito)',
  R: 'var(--color-alerta)',
  M: 'var(--color-peligro)',
  P: 'var(--color-texto-tenue)',
  A: 'var(--color-primario)',
};

export default function CorteAscensor({
  zonas = {}, // { maquina, cabina, contrapeso, puertasCabina, hueco, foso }
  puertasPiso = [], // estado por piso, de abajo hacia arriba
  pisos = 7,
  compacto = false,
  etiqueta = 'Esquema en corte del ascensor',
}) {
  const totalPisos = Math.max(2, Math.min(pisos, 24));
  const alto = 290 / totalPisos;
  const cabinaY = 360 - Math.min(4, totalPisos) * alto + 4;
  const color = (zona) => COLORES[zonas[zona] || 'P'];
  const relleno = (zona, opacidad) => ({ fill: color(zona), fillOpacity: opacidad, stroke: color(zona), strokeWidth: 1.4 });
  const texto = { fill: 'var(--color-texto)', font: '500 10.5px var(--mono)' };

  return (
    <svg viewBox="0 0 250 440" role="img" aria-label={etiqueta} style={{ width: '100%', height: 'auto', display: 'block' }}>
      <rect x="52" y="70" width="160" height="290" rx="3" style={{ fill: 'var(--color-superficie)', stroke: 'var(--color-borde-fuerte)' }} />
      <rect x="52" y="70" width="160" height="290" rx="3" style={relleno('hueco', 0.09)} />
      {Array.from({ length: totalPisos }, (_, piso) => {
        // Con muchos pisos las puertas se juntan y solo se rotula uno de cada `paso`.
        const separacion = Math.min(12, alto * 0.3);
        const paso = Math.ceil(totalPisos / 12);
        const rotulado = piso % paso === 0;
        const y = 360 - (piso + 1) * alto + separacion / 2;
        return (
          <g key={piso}>
            <rect x="44" y={y} width="6" height={Math.max(alto - separacion, 2)} rx="1.5" style={{ fill: COLORES[puertasPiso[piso] || 'P'] }} />
            {!compacto && rotulado && (
              <text x="18" y={y + alto / 2 - 2} style={{ fill: 'var(--color-texto-suave)', font: '400 10px var(--mono)' }}>
                P{piso + 1}
              </text>
            )}
          </g>
        );
      })}
      <line x1="118" y1="60" x2="118" y2={cabinaY} style={{ stroke: 'var(--color-texto-tenue)' }} strokeWidth="1.2" />
      <line x1="190" y1="60" x2="190" y2="120" style={{ stroke: 'var(--color-texto-tenue)' }} strokeWidth="1.2" />
      <rect x="176" y="120" width="28" height="140" rx="2" style={relleno('contrapeso', 0.3)} />
      <rect x="70" y={cabinaY} width="96" height={alto * 1.15} rx="3" style={relleno('cabina', 0.26)} />
      <rect x="70" y={cabinaY + alto * 1.15 - 5} width="96" height="5" rx="1" style={relleno('puertasCabina', 0.95)} />
      <rect x="20" y="10" width="210" height="50" rx="5" style={relleno('maquina', 0.22)} />
      <circle cx="118" cy="40" r="10" style={{ fill: 'none', stroke: 'var(--color-texto)', strokeOpacity: 0.4 }} />
      <circle cx="118" cy="40" r="3" style={{ fill: 'var(--color-texto)', fillOpacity: 0.4 }} />
      <rect x="20" y="370" width="210" height="58" rx="5" style={relleno('foso', 0.28)} />
      <rect x="84" y="404" width="12" height="20" rx="2" style={{ fill: 'var(--color-texto)', fillOpacity: 0.22 }} />
      <rect x="140" y="404" width="12" height="20" rx="2" style={{ fill: 'var(--color-texto)', fillOpacity: 0.22 }} />
      {!compacto && (
        <>
          <text x="28" y="30" style={texto}>Máquina</text>
          <text x="78" y={cabinaY + 18} style={texto}>Cabina</text>
          <text x="178" y="276" style={{ ...texto, fontSize: 9.5 }}>Contra</text>
          <text x="28" y="392" style={texto}>Foso</text>
          <text x="140" y="84" style={{ ...texto, fontSize: 9.5 }}>Hueco</text>
        </>
      )}
    </svg>
  );
}
