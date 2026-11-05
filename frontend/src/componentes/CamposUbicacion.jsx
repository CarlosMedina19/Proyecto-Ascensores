import { useEffect, useMemo, useState } from 'react';
import { ubicaciones } from '../api/servicios';
import { CampoBuscador } from './Campos';

// Departamentos y municipios: se piden una vez por sesión.
let pedido = null;
function cargarUbicaciones() {
  pedido ??= ubicaciones.listar().then((r) => r.datos).catch((e) => {
    pedido = null;
    throw e;
  });
  return pedido;
}

/** Departamento y ciudad con búsqueda: la ciudad solo ofrece municipios del departamento elegido. */
export default function CamposUbicacion({ departamento, ciudad, alCambiar, errores = {}, obligatorio = true }) {
  const [datos, setDatos] = useState(null);

  useEffect(() => {
    cargarUbicaciones().then(setDatos).catch(() => setDatos({}));
  }, []);

  const departamentos = useMemo(() => Object.keys(datos ?? {}).map((d) => ({ valor: d, texto: d })), [datos]);
  const ciudades = useMemo(() => {
    if (!datos) return [];
    if (departamento && datos[departamento]) return datos[departamento].map((c) => ({ valor: c, texto: c }));
    // Sin departamento se busca en todo el país; al elegir la ciudad se completa el departamento.
    return Object.entries(datos).flatMap(([d, lista]) => lista.map((c) => ({ valor: `${c}|${d}`, texto: c, detalle: d })));
  }, [datos, departamento]);

  // Un valor guardado que no está en la lista (datos antiguos) se sigue mostrando.
  const opcionesDepartamento = departamento && datos && !datos[departamento] ? [{ valor: departamento, texto: departamento }, ...departamentos] : departamentos;
  const valorCiudad = departamento ? ciudad : (ciudades.find((c) => c.texto === ciudad)?.valor ?? '');
  const opcionesCiudad =
    ciudad && departamento && !ciudades.some((c) => c.valor === ciudad) ? [{ valor: ciudad, texto: ciudad, detalle: 'No está en la lista' }, ...ciudades] : ciudades;

  return (
    <>
      <CampoBuscador
        etiqueta="Departamento"
        obligatorio={obligatorio}
        valor={departamento}
        opciones={opcionesDepartamento}
        placeholder={datos ? 'Busque el departamento' : 'Cargando…'}
        error={errores.departamento}
        alCambiar={(nuevo) => alCambiar({ departamento: nuevo, ciudad: datos?.[nuevo]?.includes(ciudad) ? ciudad : '' })}
      />
      <CampoBuscador
        etiqueta="Ciudad o municipio"
        obligatorio={obligatorio}
        valor={valorCiudad}
        opciones={opcionesCiudad}
        placeholder={departamento ? 'Busque la ciudad' : 'Busque la ciudad en todo el país'}
        error={errores.ciudad}
        alCambiar={(nuevo) => {
          if (!departamento && nuevo.includes('|')) {
            const [c, d] = nuevo.split('|');
            alCambiar({ ciudad: c, departamento: d });
          } else {
            alCambiar({ ciudad: nuevo, departamento });
          }
        }}
      />
    </>
  );
}
