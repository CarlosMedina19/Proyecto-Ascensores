import { Marca } from '../../componentes/Marca';
import CorteAscensor from '../../componentes/CorteAscensor';

/** Diseño compartido de las pantallas de acceso. */
export default function DisenoAcceso({ children }) {
  return (
    <div className="acceso">
      <section className="acceso__presentacion">
        <Marca subtitulo="Sistema Integral de Gestión y Mantenimiento de Ascensores" />
        <div
          className="acceso__corte"
          aria-hidden="true"
          style={{
            '--color-superficie': '#16111f',
            '--color-texto': '#f2eff7',
            '--color-borde-fuerte': '#382f48',
            '--color-texto-tenue': '#6e6680',
            '--color-exito': '#3fd68a',
            '--color-alerta': '#f2ae3d',
          }}
        >
          <CorteAscensor
            compacto
            zonas={{ maquina: 'B', cabina: 'B', contrapeso: 'B', puertasCabina: 'B', hueco: 'B', foso: 'B' }}
            puertasPiso={['B', 'B', 'B', 'R', 'B', 'B', 'B']}
          />
        </div>
        <h1>
          Cada ascensor, <em>cada piso</em>, bajo control.
        </h1>
        <p>Ficha técnica, historial y trazabilidad de cada equipo, con acceso por roles para su equipo y portal para la administración del edificio.</p>
        <div className="acceso__chips">
          <span>Clientes</span>
          <span>Edificios</span>
          <span>Ascensores</span>
          <span>Auditoría</span>
        </div>
      </section>
      <section className="acceso__formulario">
        <div className="acceso__caja">{children}</div>
      </section>
    </div>
  );
}
