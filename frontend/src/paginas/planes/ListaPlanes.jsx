import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { planes } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Tarjeta } from '../../componentes/Basicos';

/** SIGMA tiene un solo plan de alquiler: abre su detalle o invita a crearlo. */
export default function ListaPlanes() {
  const { tienePermiso } = useAutenticacion();
  const [plan, setPlan] = useState(undefined);
  const [error, setError] = useState('');

  useEffect(() => {
    planes
      .listar({ por_pagina: 1 })
      .then((r) => setPlan(r.datos[0] ?? null))
      .catch((e) => setError(e.message));
  }, []);

  if (error) return <Alerta>{error}</Alerta>;
  if (plan === undefined) return <Cargando />;
  // SIGMA tiene un solo plan de alquiler: se abre directamente.
  if (plan) return <Navigate to={`/planes/${plan.id}`} replace />;

  return (
    <>
      <EncabezadoPagina titulo="Plan de alquiler del software" descripcion={['Un solo plan', 'El precio sube según el tiempo de alquiler', 'Cada contrato puede ajustarlo']} />
      <Tarjeta>
        <EstadoVacio titulo="Aún no se ha creado el plan" descripcion="Indique sus servicios y el precio por 1 mes, 3 meses, 6 meses…">
          {tienePermiso('planes.crear') && (
            <Link to="/planes/nuevo" className="boton">
              <Plus size={16} /> Crear plan
            </Link>
          )}
        </EstadoVacio>
      </Tarjeta>
    </>
  );
}
