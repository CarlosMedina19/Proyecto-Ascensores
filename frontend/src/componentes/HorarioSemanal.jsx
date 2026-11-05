// Días y horas de trabajo de un técnico: { 1: { inicio: '07:00', fin: '17:00' }, … } (1 = lunes).
export const DIAS_SEMANA = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

const JORNADA = { inicio: '07:00', fin: '17:00' };

const PLANTILLAS = [
  { texto: 'Lunes a viernes', dias: [1, 2, 3, 4, 5] },
  { texto: 'Lunes a sábado', dias: [1, 2, 3, 4, 5, 6] },
  { texto: 'Sin horario', dias: [] },
];

/** Calendario semanal: se marca cada día que trabaja y se ajusta su hora de entrada y salida. */
export default function HorarioSemanal({ valor = {}, alCambiar, errores = {} }) {
  const horario = valor || {};
  const primera = Object.values(horario)[0] ?? JORNADA;

  function cambiarDia(dia, jornada) {
    const nuevo = { ...horario };
    if (jornada) nuevo[dia] = jornada;
    else delete nuevo[dia];
    alCambiar(nuevo);
  }

  return (
    <div className="horario">
      <div className="horario__plantillas">
        <span className="texto-pequeno texto-suave">Rápido:</span>
        {PLANTILLAS.map((p) => (
          <button
            key={p.texto}
            type="button"
            className="boton boton--secundario boton--pequeno"
            onClick={() => alCambiar(Object.fromEntries(p.dias.map((d) => [d, { ...primera }])))}
          >
            {p.texto}
          </button>
        ))}
      </div>
      <ul className="horario__dias">
        {DIAS_SEMANA.map((nombre, k) => {
          const dia = k + 1;
          const jornada = horario[dia];
          const errorDia = errores[`horario.${dia}.fin`]?.[0] || errores[`horario.${dia}.inicio`]?.[0];
          return (
            <li key={dia} className={`horario__dia${jornada ? ' horario__dia--activo' : ''}`}>
              <label className="horario__nombre">
                <input type="checkbox" checked={Boolean(jornada)} onChange={(e) => cambiarDia(dia, e.target.checked ? { ...primera } : null)} />
                <span>{nombre}</span>
              </label>
              {jornada ? (
                <span className="horario__horas">
                  <input
                    type="time"
                    className="campo__control"
                    value={jornada.inicio}
                    onChange={(e) => cambiarDia(dia, { ...jornada, inicio: e.target.value })}
                    aria-label={`Entrada del ${nombre.toLowerCase()}`}
                  />
                  <span aria-hidden="true">a</span>
                  <input
                    type="time"
                    className="campo__control"
                    value={jornada.fin}
                    onChange={(e) => cambiarDia(dia, { ...jornada, fin: e.target.value })}
                    aria-label={`Salida del ${nombre.toLowerCase()}`}
                    aria-invalid={errorDia ? 'true' : undefined}
                  />
                </span>
              ) : (
                <span className="horario__descanso">Descanso</span>
              )}
              {errorDia && <span className="campo__error">{errorDia}</span>}
            </li>
          );
        })}
      </ul>
    </div>
  );
}
