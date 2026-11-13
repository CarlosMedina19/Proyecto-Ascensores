import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowRight,
  Bell,
  Building2,
  Check,
  ChevronDown,
  ClipboardCheck,
  FileText,
  Mail,
  Menu,
  MessageCircle,
  Moon,
  QrCode,
  Sun,
  Users,
  X,
} from 'lucide-react';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useTema } from '../../hooks/useTema';
import { Logo } from '../../componentes/Marca';
import { APP_MOVIL, AUDIENCIAS, BENEFICIOS, CONTACTO, CTA, FUNCIONES, HERO, INSIGNIAS_HERO, NAVEGACION, PASOS, PIE, PLANES, PREGUNTAS, PROBLEMAS, RECORRIDO, SEO } from './contenidoLanding';
import './landing.css';

const ICONOS = { clientes: Users, edificios: Building2, historial: ClipboardCheck, documentos: FileText, qr: QrCode, alertas: Bell };

/** Aparece suavemente al entrar en pantalla (sin animación si el usuario prefiere movimiento reducido). */
function useAparecer() {
  const raiz = useRef(null);
  useEffect(() => {
    const elementos = raiz.current?.querySelectorAll('.aparece') ?? [];
    if (!('IntersectionObserver' in window) || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
      elementos.forEach((e) => e.classList.add('visible'));
      return undefined;
    }
    const observador = new IntersectionObserver(
      (entradas) =>
        entradas.forEach((entrada) => {
          if (entrada.isIntersecting) {
            entrada.target.classList.add('visible');
            observador.unobserve(entrada.target);
          }
        }),
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    elementos.forEach((e) => observador.observe(e));
    return () => observador.disconnect();
  }, []);
  return raiz;
}

function Encabezado() {
  const { usuario } = useAutenticacion();
  const { tema, alternar } = useTema();
  const [abierto, setAbierto] = useState(false);
  const [desplazado, setDesplazado] = useState(false);

  useEffect(() => {
    const alDesplazar = () => setDesplazado(window.scrollY > 8);
    alDesplazar();
    window.addEventListener('scroll', alDesplazar, { passive: true });
    return () => window.removeEventListener('scroll', alDesplazar);
  }, []);

  return (
    <header className={`landing-nav${desplazado ? ' landing-nav--fija' : ''}`}>
      <div className="landing-contenedor landing-nav__fila">
        <a href="#inicio" className="landing-marca" aria-label="SIGMA, ir al inicio">
          <Logo tamano={34} />
          <span>SIGMA</span>
        </a>
        <nav className={`landing-nav__enlaces${abierto ? ' abierto' : ''}`} aria-label="Secciones">
          {NAVEGACION.map((n) => (
            <a key={n.id} href={`#${n.id}`} onClick={() => setAbierto(false)}>
              {n.texto}
            </a>
          ))}
        </nav>
        <div className="landing-nav__acciones">
          <button type="button" className="landing-icono" onClick={alternar} aria-label={tema === 'oscuro' ? 'Usar tema claro' : 'Usar tema oscuro'}>
            {tema === 'oscuro' ? <Sun size={18} /> : <Moon size={18} />}
          </button>
          <Link to={usuario ? '/inicio' : '/iniciar-sesion'} className="landing-boton landing-boton--primario landing-boton--pequeno">
            {usuario ? 'Ir al panel' : 'Iniciar sesión'}
          </Link>
          <button type="button" className="landing-icono landing-nav__menu" onClick={() => setAbierto((v) => !v)} aria-expanded={abierto} aria-label="Menú">
            {abierto ? <X size={20} /> : <Menu size={20} />}
          </button>
        </div>
      </div>
    </header>
  );
}

function Hero() {
  return (
    <section id="inicio" className="landing-hero">
      <div className="landing-contenedor landing-hero__rejilla">
        <div>
          <p className="landing-etiqueta">{HERO.etiqueta}</p>
          <h1 className="landing-hero__titulo">
            {HERO.titulo} <span>{HERO.tituloResaltado}</span>
          </h1>
          <p className="landing-hero__subtitulo">{HERO.subtitulo}</p>
          <div className="landing-hero__botones">
            <a href="#contacto" className="landing-boton landing-boton--primario">
              {HERO.botonPrincipal} <ArrowRight size={18} aria-hidden="true" />
            </a>
            <a href="#funciones" className="landing-boton landing-boton--secundario">
              {HERO.botonSecundario}
            </a>
          </div>
          <dl className="landing-hero__cifras">
            {HERO.cifras.map((c) => (
              <div key={c.texto}>
                <dt>{c.valor}</dt>
                <dd>{c.texto}</dd>
              </div>
            ))}
          </dl>
        </div>
        <div className="landing-hero__visual">
          <div className="landing-navegador">
            <div className="landing-navegador__barra">
              <i />
              <i />
              <i />
              <span>sigma.proyectoscampus.top</span>
            </div>
            <img src="/landing/panel.webp" alt="Panel de operación de SIGMA con indicadores y la agenda del día" width="2000" height="1250" fetchpriority="high" />
          </div>
          {INSIGNIAS_HERO.map((i, k) => (
            <div key={i.texto} className={`landing-insignia landing-insignia--${k + 1}`} aria-hidden="true">
              <span className="landing-insignia__punto">
                <Check size={14} />
              </span>
              <span>
                <b>{i.texto}</b>
                {i.detalle}
              </span>
            </div>
          ))}
          <img className="landing-hero__telefono" src="/landing/app-inicio.webp" alt="Inicio de la app de SIGMA para técnicos" width="390" height="844" loading="lazy" />
        </div>
      </div>
    </section>
  );
}

function Seccion({ id, etiqueta, titulo, texto, children, alterna }) {
  return (
    <section id={id} className={`landing-seccion${alterna ? ' landing-seccion--alterna' : ''}`}>
      <div className="landing-contenedor">
        <div className="landing-seccion__cabecera aparece">
          {etiqueta && <p className="landing-etiqueta">{etiqueta}</p>}
          <h2>{titulo}</h2>
          {texto && <p>{texto}</p>}
        </div>
        {children}
      </div>
    </section>
  );
}

/** Recorrido por el producto: pestañas que cambian la pantalla mostrada. */
function Recorrido() {
  const [activa, setActiva] = useState(RECORRIDO[0].id);
  const actual = RECORRIDO.find((r) => r.id === activa) ?? RECORRIDO[0];

  function alTeclear(evento) {
    const k = RECORRIDO.findIndex((r) => r.id === activa);
    if (evento.key === 'ArrowRight') setActiva(RECORRIDO[(k + 1) % RECORRIDO.length].id);
    if (evento.key === 'ArrowLeft') setActiva(RECORRIDO[(k - 1 + RECORRIDO.length) % RECORRIDO.length].id);
  }

  return (
    <div className="landing-recorrido aparece">
      <div className="landing-recorrido__pestanas" role="tablist" aria-label="Pantallas de SIGMA" onKeyDown={alTeclear}>
        {RECORRIDO.map((r) => (
          <button
            key={r.id}
            type="button"
            role="tab"
            id={`pestana-${r.id}`}
            aria-selected={r.id === activa}
            aria-controls="recorrido-panel"
            tabIndex={r.id === activa ? 0 : -1}
            className="landing-recorrido__pestana"
            onClick={() => setActiva(r.id)}
          >
            <b>{r.titulo}</b>
            <span>{r.texto}</span>
          </button>
        ))}
      </div>
      <div className="landing-navegador landing-recorrido__pantalla" role="tabpanel" id="recorrido-panel" aria-labelledby={`pestana-${actual.id}`}>
        <div className="landing-navegador__barra">
          <i />
          <i />
          <i />
          <span>sigma.proyectoscampus.top</span>
        </div>
        <img key={actual.id} src={actual.imagen} alt={`${actual.titulo} de SIGMA`} width="2000" height="1250" loading="lazy" />
      </div>
    </div>
  );
}

function Contacto() {
  const whatsapp = `https://wa.me/${CONTACTO.whatsapp}?text=${encodeURIComponent(CONTACTO.mensajeWhatsapp)}`;

  return (
    <div className="landing-contacto aparece">
      <a className="landing-tarjeta landing-canal" href={whatsapp} target="_blank" rel="noreferrer">
        <span className="landing-canal__icono">
          <MessageCircle size={24} aria-hidden="true" />
        </span>
        <span>
          <b>WhatsApp</b>
          Escríbanos y le respondemos hoy mismo.
        </span>
      </a>
      <a className="landing-tarjeta landing-canal" href={`mailto:${CONTACTO.correo}?subject=${encodeURIComponent('Demostración de SIGMA')}`}>
        <span className="landing-canal__icono">
          <Mail size={24} aria-hidden="true" />
        </span>
        <span>
          <b>Correo</b>
          {CONTACTO.correo}
        </span>
      </a>
    </div>
  );
}

/** Íconos de las redes sociales (trazos simples, mismo estilo de los demás íconos). */
const ICONOS_REDES = {
  Instagram: (
    <>
      <rect x="2" y="2" width="20" height="20" rx="5" ry="5" />
      <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
      <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />
    </>
  ),
  Facebook: <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />,
  LinkedIn: (
    <>
      <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
      <rect x="2" y="9" width="4" height="12" />
      <circle cx="4" cy="4" r="2" />
    </>
  ),
};

function IconoRed({ nombre }) {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {ICONOS_REDES[nombre]}
    </svg>
  );
}

/**
 * Página de inicio pública de SIGMA (comercial). El sistema de gestión queda detrás del inicio de sesión;
 * con sesión activa el botón dice «Ir al panel». Los textos están en contenidoLanding.js.
 */
export default function Landing() {
  const raiz = useAparecer();

  useEffect(() => {
    const anterior = document.title;
    document.title = SEO.titulo;
    const meta = document.querySelector('meta[name="description"]');
    const descripcionAnterior = meta?.getAttribute('content');
    meta?.setAttribute('content', SEO.descripcion);
    return () => {
      document.title = anterior;
      if (meta && descripcionAnterior) meta.setAttribute('content', descripcionAnterior);
    };
  }, []);

  return (
    <div className="landing" ref={raiz}>
      <a className="landing-saltar" href="#principal">
        Saltar al contenido
      </a>
      <Encabezado />
      <main id="principal">
        <Hero />

        <Seccion id="problema" etiqueta="Problema y solución" titulo="Lo que se pierde sin un sistema, SIGMA lo ordena" alterna>
          <div className="landing-rejilla landing-rejilla--3">
            {PROBLEMAS.map((p) => (
              <article key={p.problema} className="landing-tarjeta landing-problema aparece">
                <h3>{p.problema}</h3>
                <p className="landing-problema__antes">{p.detalle}</p>
                <p className="landing-problema__despues">
                  <Check size={16} aria-hidden="true" /> {p.solucion}
                </p>
              </article>
            ))}
          </div>
        </Seccion>

        <Seccion id="como-funciona" etiqueta="Cómo funciona" titulo="De la hoja de cálculo al control total en tres pasos">
          <ol className="landing-pasos">
            {PASOS.map((p, k) => (
              <li key={p.titulo} className="landing-tarjeta landing-paso aparece">
                <div className="landing-tarjeta__cabecera">
                  <span className="landing-paso__numero">{k + 1}</span>
                  <h3>{p.titulo}</h3>
                </div>
                <p>{p.texto}</p>
              </li>
            ))}
          </ol>
        </Seccion>

        <Seccion id="recorrido" etiqueta="Conózcalo por dentro" titulo="Así se ve SIGMA" alterna>
          <Recorrido />
        </Seccion>

        <Seccion id="funciones" etiqueta="Funcionalidades" titulo="Todo el mantenimiento de ascensores en un solo sistema">
          <div className="landing-rejilla landing-rejilla--3">
            {FUNCIONES.map((f) => {
              const Icono = ICONOS[f.icono] ?? Check;
              return (
                <article key={f.titulo} className="landing-tarjeta landing-funcion aparece">
                  <span className="landing-funcion__icono">
                    <Icono size={22} aria-hidden="true" />
                  </span>
                  <h3>{f.titulo}</h3>
                  <p>{f.texto}</p>
                </article>
              );
            })}
          </div>
        </Seccion>

        <Seccion id="app" etiqueta="Aplicación móvil" titulo={APP_MOVIL.titulo} texto={APP_MOVIL.texto} alterna>
          <div className="landing-app">
            <ul className="landing-lista aparece">
              {APP_MOVIL.puntos.map((p) => (
                <li key={p}>
                  <Check size={18} aria-hidden="true" /> {p}
                </li>
              ))}
              <li className="landing-tiendas">
                <a className="landing-tienda" href={APP_MOVIL.tiendas.googlePlay} aria-label="Disponible en Google Play (próximamente)">
                  <small>Próximamente en</small>Google Play
                </a>
                <a className="landing-tienda" href={APP_MOVIL.tiendas.appStore} aria-label="Disponible en App Store (próximamente)">
                  <small>Próximamente en</small>App Store
                </a>
                <a className="landing-tienda landing-tienda--directa" href={APP_MOVIL.descargaDirecta} download>
                  <small>Descarga directa</small>APK para Android
                </a>
              </li>
            </ul>
            <div className="landing-telefonos aparece">
              {APP_MOVIL.pantallas.map((p) => (
                <figure key={p.imagen}>
                  <img src={p.imagen} alt={p.texto} width="390" height="844" loading="lazy" />
                  <figcaption>{p.texto}</figcaption>
                </figure>
              ))}
            </div>
          </div>
        </Seccion>

        <Seccion id="beneficios" etiqueta="Beneficios" titulo="Menos papel, menos fallas, más control">
          <div className="landing-rejilla landing-rejilla--4">
            {BENEFICIOS.map((b, k) => (
              <article key={b.titulo} className="landing-tarjeta landing-beneficio aparece">
                <div className="landing-tarjeta__cabecera">
                  <span className="landing-beneficio__numero">0{k + 1}</span>
                  <h3>{b.titulo}</h3>
                </div>
                <p>{b.texto}</p>
              </article>
            ))}
          </div>
        </Seccion>

        <Seccion id="para-quien" etiqueta="¿Para quién es?" titulo="Pensado para quienes cuidan los ascensores" alterna>
          <div className="landing-rejilla landing-rejilla--2">
            {AUDIENCIAS.map((a) => (
              <article key={a.titulo} className="landing-tarjeta landing-audiencia aparece">
                <h3>{a.titulo}</h3>
                <p>{a.texto}</p>
              </article>
            ))}
          </div>
        </Seccion>

        <Seccion id="planes" etiqueta="Planes" titulo="Un plan, todo incluido" texto={PLANES.nota}>
          <div className="landing-rejilla landing-rejilla--3">
            {PLANES.opciones.map((p) => (
              <article key={p.periodo} className={`landing-tarjeta landing-plan aparece${p.destacado ? ' landing-plan--destacado' : ''}`}>
                {p.destacado && <span className="landing-plan__sello">{p.detalle}</span>}
                <h3>{p.periodo}</h3>
                <p className="landing-plan__precio">{p.precio}</p>
                {!p.destacado && <p className="landing-plan__detalle">{p.detalle}</p>}
                <ul className="landing-lista landing-lista--compacta">
                  {PLANES.incluye.map((i) => (
                    <li key={i}>
                      <Check size={16} aria-hidden="true" /> {i}
                    </li>
                  ))}
                </ul>
                <a href="#contacto" className={`landing-boton ${p.destacado ? 'landing-boton--primario' : 'landing-boton--secundario'}`}>
                  Pedir cotización
                </a>
              </article>
            ))}
          </div>
        </Seccion>

        <Seccion id="preguntas" etiqueta="Preguntas frecuentes" titulo="Lo que suelen preguntarnos" alterna>
          <div className="landing-preguntas aparece">
            {PREGUNTAS.map((p) => (
              <details key={p.pregunta} className="landing-tarjeta landing-pregunta">
                <summary>
                  {p.pregunta}
                  <ChevronDown size={18} aria-hidden="true" />
                </summary>
                <p>{p.respuesta}</p>
              </details>
            ))}
          </div>
        </Seccion>

        <section className="landing-cta">
          <div className="landing-contenedor landing-cta__caja aparece">
            <div>
              <h2>{CTA.titulo}</h2>
              <p>{CTA.texto}</p>
            </div>
            <a href="#contacto" className="landing-boton landing-boton--claro">
              {CTA.boton} <ArrowRight size={18} aria-hidden="true" />
            </a>
          </div>
        </section>

        <Seccion id="contacto" etiqueta="Contacto" titulo="Pida una demostración" texto="Escríbanos por WhatsApp o por correo: le mostramos SIGMA con sus propios datos y le ayudamos a cargar su información.">
          <Contacto />
        </Seccion>
      </main>

      <footer className="landing-pie">
        <div className="landing-contenedor landing-pie__rejilla">
          <div>
            <a href="#inicio" className="landing-marca">
              <Logo tamano={32} />
              <span>SIGMA</span>
            </a>
            <p className="landing-pie__nombre">Sistema Integral de Gestión y Mantenimiento de Ascensores.</p>
            <p className="landing-pie__resumen">{PIE.resumen}</p>
          </div>
          <nav aria-label="Enlaces del pie de página" className="landing-pie__enlaces">
            {NAVEGACION.map((n) => (
              <a key={n.id} href={`#${n.id}`}>
                {n.texto}
              </a>
            ))}
            <Link to="/iniciar-sesion">Iniciar sesión</Link>
          </nav>
          <div className="landing-pie__redes">
            <p className="landing-pie__titulo">Síganos</p>
            <nav aria-label="Redes sociales">
              {CONTACTO.redes.map((r) => (
                <a key={r.nombre} href={r.url} aria-label={r.nombre} title={r.nombre} target="_blank" rel="noreferrer">
                  <IconoRed nombre={r.nombre} />
                </a>
              ))}
            </nav>
          </div>
        </div>
        <p className="landing-contenedor landing-pie__derechos">© {new Date().getFullYear()} SIGMA. Todos los derechos reservados.</p>
      </footer>
    </div>
  );
}
