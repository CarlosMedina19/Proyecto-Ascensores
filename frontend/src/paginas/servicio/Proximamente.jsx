import { BrainCircuit, RadioTower } from 'lucide-react';
import { EncabezadoPagina, Insignia, Tarjeta } from '../../componentes/Basicos';

const MODULOS = [
  {
    icono: RadioTower,
    titulo: 'Monitoreo en línea (IoT)',
    texto:
      'Un sensor en el cuarto de máquinas o en el controlador envía a SIGMA los viajes, las paradas, las fallas y los atrapamientos en el momento en que ocurren. La emergencia se abre sola, sin esperar la llamada.',
    puntos: ['Alarma automática de atrapamiento', 'Conteo de viajes y horas de uso', 'Estado de puertas por piso', 'Historial de fallas del controlador'],
  },
  {
    icono: BrainCircuit,
    titulo: 'Mantenimiento predictivo con IA',
    texto:
      'Con el historial de checklist, fallas y uso, SIGMA estimará qué componentes tienen más probabilidad de fallar y sugerirá adelantar su revisión antes de que el ascensor se detenga.',
    puntos: ['Riesgo de falla por componente', 'Sugerencias para la próxima visita', 'Repuestos a tener en bodega', 'Comparación entre ascensores similares'],
  },
];

/** Funciones en preparación: se muestran para que el cliente conozca la hoja de ruta. */
export default function Proximamente() {
  return (
    <>
      <EncabezadoPagina titulo="Próximamente" descripcion="Funciones en las que estamos trabajando. Si le interesa participar en la prueba piloto, comuníquese con su coordinador." />
      <div className="rejilla rejilla--2">
        {MODULOS.map(({ icono: Icono, titulo, texto, puntos }) => (
          <Tarjeta key={titulo}>
            <div className="proximamente">
              <span className="indicador-servicio__icono" aria-hidden="true">
                <Icono size={18} />
              </span>
              <div>
                <h3 style={{ margin: '0 0 6px' }}>
                  {titulo} <Insignia tipo="info">En preparación</Insignia>
                </h3>
                <p className="texto-suave" style={{ margin: '0 0 12px' }}>
                  {texto}
                </p>
                <ul className="proximamente__lista">
                  {puntos.map((p) => (
                    <li key={p}>{p}</li>
                  ))}
                </ul>
              </div>
            </div>
          </Tarjeta>
        ))}
      </div>
    </>
  );
}
