# SIGMA — panel web (React + Vite)

Panel administrativo y portal del cliente. Consume la misma API (`/api/v1`) que la app móvil
y muestra a cada usuario solo los módulos que permite su rol.

## Ejecutar

```sh
npm install
copy .env.example .env     # VITE_API_URL=http://localhost:8000/api/v1
npm run dev                # http://localhost:5173
```

La API debe estar corriendo (ver `../backend`). Para producción: `npm run build` genera `dist/`.

## Pruebas

```sh
npm test
```

## Estructura

```
src/
  api/            cliente HTTP (token Bearer, errores en español) y servicios por recurso
  contexto/       sesión del usuario y notificaciones
  hooks/          listados paginados, formularios, inactividad (RF-001) y tema claro/oscuro
  componentes/    estructura, rutas protegidas, tabla, campos, logo y esquema del ascensor
  paginas/        autenticación, panel, clientes, edificios, ascensores, usuarios, roles, auditoría, perfil,
                  planes, contratos, calendario de mantenimientos y configuración (fase 2)
  estilos/        sistema visual (tokens de color, tipografía, modo oscuro)
  pruebas/        pruebas con Vitest y Testing Library
```

## Fase 2 en el panel

- **Planes y precios:** catálogo con el precio por visita para cada periodicidad (1 a 6 meses),
  cambio de precios con motivo e historial de precios.
- **Plan del ascensor** (en el detalle del ascensor): asignar plan, cambiar plan o periodicidad,
  precio y vigencia con motivo, finalizar, próximas visitas y cambios registrados.
- **Calendario:** vista mensual y agenda; reprogramar (día hábil y motivo), registrar la
  ejecución o cancelar. Las visitas pendientes con fecha pasada se marcan como atrasadas.
- **Contratos:** listado con vencimiento, formulario con los ascensores del cliente,
  seguimiento de la renovación, renovación y cancelación.
- **Panel de operación** (inicio del personal administrativo): indicadores con tendencia,
  mantenimientos por semana, alertas, cola de hoy, estado por ascensor y técnicos.
- **Buscador global** en el encabezado (Ctrl+K): ascensores, órdenes, edificios, clientes y contratos.
- **Fotos del ascensor:** hasta 3 al registrarlo, editarlo o desde su detalle.
- **Calendario por horas:** vistas de día, semana (con la hora actual y los festivos) y mes;
  `/calendario?fecha=AAAA-MM-DD&vista=dia|semana|mes` abre una fecha concreta.
- **Campana de notificaciones** en el encabezado y **Configuración** (solo administrador):
  canales y días de aviso, días de alerta de contratos e IVA.

## Fase 3 en el panel

- **Órdenes de trabajo:** lista con filtros, creación (desde cero, desde un ascensor o desde una
  visita del plan en el calendario), detalle con asignación de técnicos (muestra quién está libre y
  por qué no se puede asignar a los demás), reprogramación, prioridad, cancelación, tiempos y
  trazabilidad. El técnico asignado avanza los estados: en camino, en proceso, pendiente y finalizada.
- **Técnicos:** registro (crea la cuenta), perfil con documento, especialidades y disponibilidad,
  activar/desactivar y su agenda de 30 días.
- **Calendario:** órdenes por tipo de servicio (preventivo, correctivo, inspección) con sus técnicos,
  y visitas del plan que aún no tienen orden. El técnico solo ve sus órdenes.
- **Panel:** técnicos en campo, correctivos abiertos, cola de hoy con órdenes y ocupación de cada técnico.

## Fase 4 en el panel

- **Planilla del preventivo** en el detalle de la orden: secciones con avance, INS/EST/N.A., B/R/M,
  observación obligatoria en R o M, fotos y creación de la orden correctiva desde un ítem.
- **Firmas** del técnico y de quien recibe (dibujadas en pantalla) e **informes PDF** con sus versiones.
- **Última revisión** por sección en el detalle del ascensor y en el panel.

## Clientes, edificios y ascensores

- En el menú, **Clientes** se despliega en **Conjuntos**, **Institucionales** y **Residenciales**: cada uno
  lista los edificios de ese tipo y cada fila despliega sus ascensores. Edificios y Ascensores ya no son
  entradas fijas del menú (sus páginas siguen disponibles desde los despliegues, el buscador y los enlaces).
- En la lista de clientes, cada fila se despliega en el mismo lugar: tipos que tiene el cliente → edificios →
  ascensores, con accesos para agregar edificio o ascensor. Las listas de edificios y de ascensores también
  tienen filas desplegables.
- Al crear un ascensor, la **ubicación** se toma del edificio elegido (no se escribe a mano).

## Sistema visual

Basado en el prototipo *SIGMA Ascensores*: morado (#4E29AA) para marca y acciones, verde lima para
datos en vivo y verde/ámbar/rojo para los estados Bueno/Regular/Malo. El menú lateral es oscuro en
los dos temas y se puede contraer a solo íconos (se recuerda en el navegador). Las tarjetas y los
encabezados de página llevan un borde luminoso degradado. El logo es la Σ trazada como el recorrido
de un ascensor; la marca usa Michroma, los títulos Bricolage Grotesque, el texto Geist y los datos
JetBrains Mono. El ingreso muestra un edificio con el indicador de piso y, al entrar, una transición
de puertas de ascensor (se omite si el sistema pide movimiento reducido). Incluye modo oscuro.

## Sesión

El token se guarda en `sessionStorage` (se borra al cerrar el navegador). A los 9 minutos sin
actividad aparece un aviso y a los 10 se cierra la sesión; mientras el usuario trabaja se
avisa al servidor para que el token no venza.
