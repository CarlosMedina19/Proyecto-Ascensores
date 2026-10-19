import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bell, CalendarClock, FileWarning } from 'lucide-react';
import { notificaciones } from '../api/servicios';
import { fechaHora } from '../utilidades/formato';

const INTERVALO_MS = 60 * 1000;

/**
 * Campana del encabezado con los avisos en la aplicación (RF-021): mantenimientos
 * próximos o reprogramados y contratos por vencer.
 */
export default function CampanaNotificaciones() {
  const navegar = useNavigate();
  const [abierta, setAbierta] = useState(false);
  const [lista, setLista] = useState([]);
  const [noLeidas, setNoLeidas] = useState(0);
  const contenedor = useRef(null);

  const cargar = useCallback(() => {
    notificaciones
      .listar({ por_pagina: 10 })
      .then((r) => {
        if (!Array.isArray(r?.datos)) return;
        setLista(r.datos);
        setNoLeidas(r.no_leidas ?? r.datos.filter((n) => !n.leida).length);
      })
      .catch(() => {});
  }, []);

  useEffect(() => {
    cargar();
    const temporizador = setInterval(cargar, INTERVALO_MS);
    return () => clearInterval(temporizador);
  }, [cargar]);

  useEffect(() => {
    if (!abierta) return undefined;
    const alClic = (evento) => contenedor.current && !contenedor.current.contains(evento.target) && setAbierta(false);
    const alTecla = (evento) => evento.key === 'Escape' && setAbierta(false);
    document.addEventListener('mousedown', alClic);
    window.addEventListener('keydown', alTecla);
    return () => {
      document.removeEventListener('mousedown', alClic);
      window.removeEventListener('keydown', alTecla);
    };
  }, [abierta]);

  async function abrir(notificacion) {
    setAbierta(false);
    if (!notificacion.leida) {
      await notificaciones.marcarLeida(notificacion.id).catch(() => {});
      cargar();
    }
    if (notificacion.enlace) navegar(notificacion.enlace);
  }

  async function marcarTodas() {
    await notificaciones.marcarTodas().catch(() => {});
    cargar();
  }

  return (
    <div className="campana" ref={contenedor}>
      <button
        type="button"
        className="boton-icono"
        onClick={() => {
          setAbierta((valor) => !valor);
          if (!abierta) cargar();
        }}
        aria-label={noLeidas ? `Notificaciones: ${noLeidas} sin leer` : 'Notificaciones'}
        aria-expanded={abierta}
        title="Notificaciones"
      >
        <Bell size={18} />
        {noLeidas > 0 && <span className="campana__contador tabular">{noLeidas > 9 ? '9+' : noLeidas}</span>}
      </button>

      {abierta && (
        <div className="campana__panel" role="dialog" aria-label="Notificaciones">
          <div className="campana__cabecera">
            <b>Notificaciones</b>
            {noLeidas > 0 && (
              <button type="button" className="boton boton--fantasma boton--pequeno" onClick={marcarTodas}>
                Marcar todas como leídas
              </button>
            )}
          </div>
          {lista.length ? (
            <ul className="campana__lista">
              {lista.map((notificacion) => {
                const Icono = notificacion.tipo?.includes('Contrato') ? FileWarning : CalendarClock;
                return (
                  <li key={notificacion.id}>
                    <button type="button" className={`campana__item${notificacion.leida ? '' : ' campana__item--nueva'}`} onClick={() => abrir(notificacion)}>
                      <Icono size={16} aria-hidden="true" />
                      <span>
                        <b>{notificacion.titulo}</b>
                        <span className="campana__mensaje">{notificacion.mensaje}</span>
                        <span className="campana__fecha">{fechaHora(notificacion.created_at)}</span>
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          ) : (
            <p className="campana__vacio">No tiene notificaciones.</p>
          )}
        </div>
      )}
    </div>
  );
}
