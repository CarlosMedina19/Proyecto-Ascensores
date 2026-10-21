# SIGMA · Redacción profesional (Fase 4)

Criterios aplicados en el panel web y en la app:

- Español de Colombia con trato de **usted** en todas las pantallas.
- Botones con **verbo + resultado**, en mayúscula inicial y sin signos de exclamación.
- Mensajes de error que dicen qué pasó y cómo resolverlo; estados vacíos con mensaje y siguiente paso.
- Fechas `17/10/2026` en tablas y `17 de octubre de 2026` en textos; pesos con separador de miles
  (`$ 1.250.000`); unidades con espacio (`630 kg`, `1,0 m/s`).
- Las referencias internas (SENA, fases, RF-xxx, cuentas de prueba) solo se ven con el **modo demostración**.

## Glosario único

| Término | Uso |
| --- | --- |
| Orden de trabajo | Trabajo asignado a uno o más técnicos (código OT-AAAA-NNNNN). |
| Mantenimiento preventivo | Visita programada según el plan, con planilla de revisión. |
| Mantenimiento correctivo | Reparación de una falla o de un hallazgo. |
| Hallazgo | Ítem de la planilla calificado como regular o malo. |
| Puerta de cabina | Puerta que viaja con la cabina. |
| Puerta de piso | Puerta de cada parada; se registra por piso con su estado. |
| Cliente | Persona o empresa que contrata el servicio. |
| Edificio | Sede del cliente: conjunto, institucional o residencial. |
| Ascensor | Equipo instalado en un edificio (antes "equipo" en varios textos). |
| Técnico | Persona que ejecuta las órdenes de trabajo en campo. |
| Informe técnico | PDF firmado que se emite al cerrar la orden (antes "informe PDF"). |

## Antes / después

| Pantalla | Antes | Después |
| --- | --- | --- |
| Inicio de sesión (web y app) | Ingresa a SIGMA | Ingrese a SIGMA |
| Inicio de sesión (web y app) | Usa el correo y la contraseña de tu cuenta. | Use el correo y la contraseña de su cuenta. |
| Inicio de sesión (pie, web y app) | SENA · Proyecto formativo SIGMA | SIGMA · Gestión y mantenimiento de ascensores (el texto anterior queda en modo demostración) |
| Menú lateral | Conectado · Fase 4 | Conectado (en modo demostración: Conectado · Demostración) |
| Clientes | Nuevo cliente | Registrar cliente |
| Contratos | Nuevo contrato | Crear contrato |
| Edificios | Nuevo edificio | Registrar edificio |
| Ascensores | Nuevo ascensor | Registrar ascensor |
| Inicio y órdenes | Nueva orden | Crear orden de trabajo |
| Planes | Nuevo plan | Crear plan |
| Roles | Nuevo rol | Crear rol |
| Técnicos | Nuevo técnico | Registrar técnico |
| Usuarios | Nuevo usuario | Crear usuario |
| Importar desde Excel | Listo | Cerrar |
| Ficha del ascensor | Datos del equipo / Historial del equipo | Datos del ascensor / Historial del ascensor |
| Formulario del ascensor | Datos generales del equipo | Datos generales del ascensor |
| Lista de ascensores | Equipos registrados con su ficha técnica… | Ascensores registrados con su ficha técnica… |
| Inicio | Últimos eventos de los equipos | Últimos eventos de los ascensores |
| Órdenes (web y app) | Preventivo / Correctivo | Mantenimiento preventivo / Mantenimiento correctivo |
| Historial de la orden | Informe PDF emitido | Informe técnico emitido |
| Inicio | Buenas tardes, Super. Hoy no hay órdenes de trabajo y 1 visita del plan atrasada. | Buenas tardes. (los datos del día pasan a avisos al lado del saludo) |
| Inicio | ▲ 2 esta semana · 1 críticos | ▲ 2 esta semana · 1 crítico |
| Inicio | de 1 activos | de 1 activo |
| Inicio | Cumplimiento semanal 0/0 | Cumplimiento semanal sin visitas programadas (o "3 de 5 visitas") |
| Tablas y fichas | 17 oct 2026 | 17/10/2026 |
| Ficha del ascensor | 60 m/min | 1,0 m/s |
| Formulario del ascensor | Entre 6 y 600 m/min (0,1 a 10 m/s). | Equivale a 1,0 m/s. Rango: 6 a 600 m/min. (se calcula al escribir) |
| Ascensores vacío | No se encontraron ascensores | + Ajuste los filtros, registre un ascensor o impórtelos desde Excel con la plantilla de SIGMA. |
| Contratos vacío | No se encontraron contratos | + Ajuste los filtros o cree un contrato para un cliente con ascensores registrados. |
| Edificio sin ascensores | El edificio no tiene ascensores registrados | + Registre el primer ascensor desde el botón de este edificio. |
| Órdenes del ascensor | El ascensor no tiene órdenes | El ascensor no tiene órdenes de trabajo + Cree una orden para programar un mantenimiento o atender una falla. |
| Auditoría vacía | No hay registros para los filtros seleccionados | + Amplíe el rango de fechas o quite filtros. |
| Planilla (ayuda INS/EST) | Significado de INS y EST por definir (RF-037) | Tipo de revisión: INS, EST o N.A. (la referencia RF-037 queda en modo demostración) |
| App · Inicio del cliente | Disponible en la fase 6 (portal del cliente)… | Para solicitar un servicio, comuníquese con su coordinador. |
| App · Visita | El descuento de inventario se habilita en la fase 5. | Registre los repuestos usados; el inventario se actualiza desde el panel web. |
| App · Visitas | Se habilitan en la fase 3. Por ahora, registre la visita… | Registre la visita desde la ficha del ascensor. |

## Modo demostración

- **Panel web:** Mi perfil → Presentaciones → *Modo demostración*. También con `?demo=1` o `?demo=0` en la
  dirección del inicio de sesión. Activo, muestra la insignia "Demostración" en el encabezado, las
  referencias del proyecto y, en el inicio de sesión, las cuentas de prueba para llenar el formulario con un
  clic.
- **App:** Mi perfil → *Modo demostración*.
- Las cuentas y la contraseña de prueba **no están en el código**: se leen de `VITE_DEMO_CUENTAS` y
  `VITE_DEMO_CLAVE` en `frontend/.env`. No las defina en producción.

## Descripciones de las secciones

Las descripciones debajo de cada título pasaron de párrafo a lista corta (una idea por elemento). Ejemplo:

| Antes | Después |
| --- | --- |
| Operación de mantenimiento: órdenes, técnicos, ascensores, planes y contratos. | • Órdenes y técnicos • Ascensores y planes • Contratos |
| Cuentas de acceso al sistema. Los usuarios no se eliminan: se desactivan y conservan su historial. | • Cuentas de acceso al sistema • Activar, desactivar o eliminar • El historial se conserva |
