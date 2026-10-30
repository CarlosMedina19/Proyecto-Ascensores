import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Trash2 } from 'lucide-react';
import { inventario } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Insignia, Tarjeta } from '../../componentes/Basicos';
import { cantidad } from '../inventario/Inventario';

/**
 * RF-042: repuestos usados en la orden. Los registra el técnico asignado o quien gestiona órdenes mientras
 * la orden no esté finalizada ni cancelada; se descuentan del inventario al finalizarla.
 */
export default function RepuestosOrden({ orden, notificar }) {
  const { tienePermiso } = useAutenticacion();
  const [usados, setUsados] = useState(null);
  const [opciones, setOpciones] = useState([]);
  const [repuestoId, setRepuestoId] = useState('');
  const [cant, setCant] = useState('1');
  const [guardando, setGuardando] = useState(false);

  const final = ['finalizada', 'cancelada'].includes(orden.estado);
  const edita = !final && (orden.permisos?.ejecutar || tienePermiso('ordenes.editar')) && tienePermiso('inventario.ver');

  const cargar = useCallback(() => {
    inventario
      .deOrden(orden.id)
      .then((r) => setUsados(r.datos))
      .catch(() => setUsados([]));
  }, [orden.id]);
  useEffect(cargar, [cargar]);

  useEffect(() => {
    if (!edita) return;
    inventario
      .listar({ estado: 'activos', por_pagina: 100 })
      .then((r) => setOpciones(r.datos))
      .catch(() => {});
  }, [edita]);

  if (!usados || (!usados.length && !edita)) return null;

  async function agregar(evento) {
    evento.preventDefault();
    if (!repuestoId) return;
    setGuardando(true);
    try {
      const r = await inventario.agregarAOrden(orden.id, { repuesto_id: Number(repuestoId), cantidad: Number(cant) });
      notificar(r.mensaje);
      setRepuestoId('');
      setCant('1');
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setGuardando(false);
    }
  }

  async function quitar(registro) {
    try {
      const r = await inventario.quitarDeOrden(orden.id, registro.id);
      notificar(r.mensaje);
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    }
  }

  return (
    <Tarjeta titulo={`Repuestos usados (${usados.length})`}>
      {usados.length > 0 ? (
        <ul className="repuestos-orden">
          {usados.map((u) => (
            <li key={u.id}>
              <div>
                <Link to={`/inventario/${u.repuesto?.id}`}>
                  <strong>{u.repuesto?.nombre}</strong>
                </Link>
                <div className="texto-pequeno texto-suave">
                  {u.repuesto?.codigo} · {cantidad(u.cantidad, u.repuesto?.unidad)}
                </div>
              </div>
              {final ? (
                u.faltante > 0 ? (
                  <Insignia tipo="alerta">Faltaron {cantidad(u.faltante)}</Insignia>
                ) : (
                  <Insignia tipo="exito">Descontado</Insignia>
                )
              ) : (
                edita && (
                  <button type="button" className="boton-icono" onClick={() => quitar(u)} aria-label={`Quitar ${u.repuesto?.nombre}`}>
                    <Trash2 size={16} />
                  </button>
                )
              )}
            </li>
          ))}
        </ul>
      ) : (
        <p className="texto-suave texto-pequeno" style={{ marginTop: 0 }}>
          Registre los repuestos usados; se descuentan del inventario al finalizar la orden.
        </p>
      )}
      {edita && (
        <form className="repuestos-orden__agregar" onSubmit={agregar}>
          <select className="campo__control" value={repuestoId} onChange={(e) => setRepuestoId(e.target.value)} aria-label="Repuesto">
            <option value="">Elija un repuesto…</option>
            {opciones.map((r) => (
              <option key={r.id} value={r.id}>
                {r.nombre} · {r.codigo} (stock {cantidad(r.stock, r.unidad)})
              </option>
            ))}
          </select>
          <input className="campo__control" inputMode="decimal" value={cant} onChange={(e) => setCant(e.target.value)} aria-label="Cantidad" />
          <button type="submit" className="boton boton--secundario" disabled={!repuestoId || guardando}>
            <Plus size={16} aria-hidden="true" /> Agregar
          </button>
        </form>
      )}
    </Tarjeta>
  );
}
