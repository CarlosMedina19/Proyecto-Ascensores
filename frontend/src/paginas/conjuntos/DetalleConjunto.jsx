import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Pencil, Plus } from 'lucide-react';
import { conjuntos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, Datos, EncabezadoPagina, EstadoVacio, Insignia, Tabla, Tarjeta } from '../../componentes/Basicos';
import { AscensoresDelEdificio } from '../../componentes/Despliegues';
import { TIPOS_EDIFICIO } from '../../utilidades/formato';

export default function DetalleConjunto() {
  const { id } = useParams();
  const navegar = useNavigate();
  const { tienePermiso } = useAutenticacion();
  const [conjunto, setConjunto] = useState(null);
  const [error, setError] = useState('');

  const cargar = useCallback(() => {
    conjuntos
      .obtener(id)
      .then((r) => setConjunto(r.datos))
      .catch((e) => setError(e.message));
  }, [id]);

  useEffect(cargar, [cargar]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!conjunto) return <Cargando />;

  const grupo = TIPOS_EDIFICIO[conjunto.tipo];
  const lista = conjunto.edificios || [];

  return (
    <>
      <EncabezadoPagina
        titulo={conjunto.nombre}
        migas={grupo ? <Link to={grupo.ruta}>{grupo.plural}</Link> : undefined}
        descripcion={
          <>
            <Link to={`/clientes/${conjunto.cliente_id}`}>{conjunto.cliente?.nombre}</Link> · {grupo?.texto} ·{' '}
            <Insignia tipo={conjunto.activo ? 'exito' : 'neutro'}>{conjunto.activo ? 'Activo' : 'Inactivo'}</Insignia>
          </>
        }
      >
        {tienePermiso('edificios.editar') && (
          <Link to={`/conjuntos/${id}/editar`} className="boton boton--secundario">
            <Pencil size={16} /> Editar
          </Link>
        )}
        {tienePermiso('edificios.crear') && conjunto.activo && (
          <Link to={`/edificios/nuevo?conjunto_id=${id}`} className="boton">
            <Plus size={16} /> Agregar edificio
          </Link>
        )}
      </EncabezadoPagina>

      <div className="rejilla">
        <div className="rejilla rejilla--2">
          <Tarjeta titulo="Ubicación">
            <Datos
              elementos={[
                ['Dirección', conjunto.direccion],
                ['Ciudad', conjunto.ciudad],
                ['Departamento', conjunto.departamento],
                ['Código postal', conjunto.codigo_postal],
              ]}
            />
          </Tarjeta>
          <Tarjeta titulo="Contacto">
            <Datos
              columnas={2}
              elementos={[
                ['Nombre', conjunto.nombre_contacto],
                ['Teléfono', conjunto.telefono_contacto],
                ['Correo', conjunto.correo_contacto],
                ['Ascensores', conjunto.equipos_count],
              ]}
            />
          </Tarjeta>
        </div>

        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">
              <span>Edificios ({lista.length})</span>
            </h2>
          </div>
          <Tabla
            filas={lista}
            alSeleccionar={(e) => navegar(`/edificios/${e.id}`)}
            expandir={(e) => <AscensoresDelEdificio edificioId={e.id} />}
            etiquetaExpandir="Ver ascensores de"
            vacio={<EstadoVacio titulo="Aún no tiene edificios" descripcion="Agregue el primero: tomará la dirección y el contacto de aquí." />}
            columnas={[
              { clave: 'nombre', titulo: 'Edificio', render: (e) => <strong>{e.nombre}</strong> },
              { clave: 'direccion', titulo: 'Dirección', render: (e) => `${e.direccion}, ${e.ciudad}` },
              { clave: 'pisos', titulo: 'Pisos', clase: 'numero', render: (e) => e.pisos ?? '—' },
              { clave: 'equipos_count', titulo: 'Ascensores', clase: 'numero' },
              { clave: 'activo', titulo: 'Estado', render: (e) => <Insignia tipo={e.activo ? 'exito' : 'neutro'}>{e.activo ? 'Activo' : 'Inactivo'}</Insignia> },
            ]}
          />
        </Tarjeta>
      </div>
    </>
  );
}
