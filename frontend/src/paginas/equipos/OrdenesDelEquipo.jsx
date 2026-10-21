import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { ordenes } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Cargando, EstadoVacio, Insignia, Tarjeta } from '../../componentes/Basicos';
import { ESTADOS_ORDEN, SERVICIOS_ORDEN, fecha } from '../../utilidades/formato';

/** Fase 3: últimas órdenes de trabajo del ascensor. */
export default function OrdenesDelEquipo({ equipoId }) {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const [lista, setLista] = useState(null);

  useEffect(() => {
    ordenes
      .listar({ equipo_id: equipoId, por_pagina: 5 })
      .then((r) => setLista(r.datos))
      .catch(() => setLista([]));
  }, [equipoId]);

  return (
    <Tarjeta
      titulo="Órdenes de trabajo"
      acciones={
        tienePermiso('ordenes.crear') && (
          <Link to={`/ordenes/nueva?equipo_id=${equipoId}`} className="boton boton--secundario boton--pequeno">
            <Plus size={15} /> Crear orden de trabajo
          </Link>
        )
      }
    >
      {lista === null ? (
        <Cargando />
      ) : lista.length ? (
        <ul className="visitas">
          {lista.map((o) => (
            <li key={o.id}>
              <button type="button" className="visitas__boton" onClick={() => navegar(`/ordenes/${o.id}`)}>
                <span>
                  <b className="tabla__codigo">{o.numero}</b> <span className="texto-suave">· {SERVICIOS_ORDEN[o.servicio]?.texto}</span>
                  <span className="tabla__secundario">
                    {fecha(o.fecha)} · {o.hora} {o.tecnicos?.length ? `· ${o.tecnicos.map((t) => t.nombre).join(', ')}` : ''}
                  </span>
                </span>
                <Insignia tipo={ESTADOS_ORDEN[o.estado]?.tipo}>{ESTADOS_ORDEN[o.estado]?.texto}</Insignia>
              </button>
            </li>
          ))}
        </ul>
      ) : (
        <EstadoVacio titulo="El ascensor no tiene órdenes de trabajo" descripcion="Cree una orden para programar un mantenimiento o atender una falla." />
      )}
    </Tarjeta>
  );
}
