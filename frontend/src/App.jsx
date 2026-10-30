import { BrowserRouter, Link, Navigate, Route, Routes } from 'react-router-dom';
import TransicionPuertas from './componentes/TransicionPuertas';
import { ProveedorAutenticacion } from './contexto/AutenticacionContexto';
import { ProveedorNotificaciones } from './contexto/NotificacionesContexto';
import { ConPermiso, RutaPrivada, RutaPublica } from './componentes/Rutas';
import { EstadoVacio } from './componentes/Basicos';
import Estructura from './componentes/Estructura';
import IniciarSesion from './paginas/autenticacion/IniciarSesion';
import RecuperarContrasena from './paginas/autenticacion/RecuperarContrasena';
import RestablecerContrasena from './paginas/autenticacion/RestablecerContrasena';
import Inicio from './paginas/Inicio';
import Perfil from './paginas/Perfil';
import ListaClientes from './paginas/clientes/ListaClientes';
import Cartera from './paginas/cartera/Cartera';
import Inventario from './paginas/inventario/Inventario';
import DetalleRepuesto from './paginas/inventario/DetalleRepuesto';
import FormularioCliente from './paginas/clientes/FormularioCliente';
import DetalleCliente from './paginas/clientes/DetalleCliente';
import ListaEdificios from './paginas/edificios/ListaEdificios';
import FormularioEdificio from './paginas/edificios/FormularioEdificio';
import DetalleEdificio from './paginas/edificios/DetalleEdificio';
import ListaEquipos from './paginas/equipos/ListaEquipos';
import FormularioEquipo from './paginas/equipos/FormularioEquipo';
import DetalleEquipo from './paginas/equipos/DetalleEquipo';
import ListaUsuarios from './paginas/usuarios/ListaUsuarios';
import FormularioUsuario from './paginas/usuarios/FormularioUsuario';
import ListaRoles from './paginas/roles/ListaRoles';
import DetalleRol from './paginas/roles/DetalleRol';
import ListaAuditoria from './paginas/auditoria/ListaAuditoria';
import ListaPlanes from './paginas/planes/ListaPlanes';
import FormularioPlan from './paginas/planes/FormularioPlan';
import DetallePlan from './paginas/planes/DetallePlan';
import ListaContratos from './paginas/contratos/ListaContratos';
import FormularioContrato from './paginas/contratos/FormularioContrato';
import DetalleContrato from './paginas/contratos/DetalleContrato';
import Calendario from './paginas/mantenimientos/Calendario';
import Configuracion from './paginas/configuracion/Configuracion';
import ListaTecnicos from './paginas/tecnicos/ListaTecnicos';
import FormularioTecnico from './paginas/tecnicos/FormularioTecnico';
import DetalleTecnico from './paginas/tecnicos/DetalleTecnico';
import ListaOrdenes from './paginas/ordenes/ListaOrdenes';
import FormularioOrden from './paginas/ordenes/FormularioOrden';
import DetalleOrden from './paginas/ordenes/DetalleOrden';
import Emergencias from './paginas/servicio/Emergencias';
import DetalleEmergencia from './paginas/servicio/DetalleEmergencia';
import Cotizaciones from './paginas/servicio/Cotizaciones';
import DetalleCotizacion from './paginas/servicio/DetalleCotizacion';
import Certificaciones from './paginas/servicio/Certificaciones';
import Indicadores from './paginas/servicio/Indicadores';
import ReportarFalla from './paginas/publico/ReportarFalla';

const conPermiso = (permiso, elemento) => <ConPermiso permiso={permiso}>{elemento}</ConPermiso>;

export default function App() {
  return (
    <BrowserRouter>
      <ProveedorNotificaciones>
        <ProveedorAutenticacion>
          <TransicionPuertas />
          <Routes>
            <Route path="/iniciar-sesion" element={<RutaPublica><IniciarSesion /></RutaPublica>} />
            <Route path="/recuperar-contrasena" element={<RutaPublica><RecuperarContrasena /></RutaPublica>} />
            <Route path="/restablecer-contrasena" element={<RutaPublica><RestablecerContrasena /></RutaPublica>} />

            {/* Reporte de fallas desde el QR de la cabina: no pide sesión. */}
            <Route path="/reportar/:codigo" element={<ReportarFalla />} />

            <Route element={<RutaPrivada><Estructura /></RutaPrivada>}>
              <Route index element={<Navigate to="/inicio" replace />} />
              <Route path="/inicio" element={<Inicio />} />
              <Route path="/perfil" element={<Perfil />} />

              <Route path="/clientes" element={conPermiso('clientes.ver', <ListaClientes />)} />
              <Route path="/clientes/nuevo" element={conPermiso('clientes.crear', <FormularioCliente />)} />
              {/* Edificios de cada tipo, desplegados desde Clientes en el menú. */}
              <Route path="/clientes/conjuntos" element={conPermiso('edificios.ver', <ListaEdificios key="conjunto" tipo="conjunto" />)} />
              <Route path="/clientes/institucionales" element={conPermiso('edificios.ver', <ListaEdificios key="institucional" tipo="institucional" />)} />
              <Route path="/clientes/residenciales" element={conPermiso('edificios.ver', <ListaEdificios key="residencial" tipo="residencial" />)} />
              <Route path="/clientes/conjuntos/ascensores" element={conPermiso('equipos.ver', <ListaEquipos key="conjunto" tipo="conjunto" />)} />
              <Route path="/clientes/institucionales/ascensores" element={conPermiso('equipos.ver', <ListaEquipos key="institucional" tipo="institucional" />)} />
              <Route path="/clientes/residenciales/ascensores" element={conPermiso('equipos.ver', <ListaEquipos key="residencial" tipo="residencial" />)} />
              <Route path="/clientes/:id" element={conPermiso('clientes.ver', <DetalleCliente />)} />
              <Route path="/clientes/:id/editar" element={conPermiso('clientes.editar', <FormularioCliente />)} />

              <Route path="/edificios" element={conPermiso('edificios.ver', <ListaEdificios />)} />
              <Route path="/edificios/nuevo" element={conPermiso('edificios.crear', <FormularioEdificio />)} />
              <Route path="/edificios/:id" element={conPermiso('edificios.ver', <DetalleEdificio />)} />
              <Route path="/edificios/:id/editar" element={conPermiso('edificios.editar', <FormularioEdificio />)} />

              <Route path="/equipos" element={conPermiso('equipos.ver', <ListaEquipos />)} />
              <Route path="/equipos/nuevo" element={conPermiso('equipos.crear', <FormularioEquipo />)} />
              <Route path="/equipos/:id" element={conPermiso('equipos.ver', <DetalleEquipo />)} />
              <Route path="/equipos/:id/editar" element={conPermiso('equipos.editar', <FormularioEquipo />)} />

              <Route path="/cartera" element={conPermiso('cartera.ver', <Cartera />)} />
              <Route path="/inventario" element={conPermiso('inventario.ver', <Inventario />)} />
              <Route path="/inventario/:id" element={conPermiso('inventario.ver', <DetalleRepuesto />)} />

              <Route path="/usuarios" element={conPermiso('usuarios.ver', <ListaUsuarios />)} />
              <Route path="/usuarios/nuevo" element={conPermiso('usuarios.crear', <FormularioUsuario />)} />
              <Route path="/usuarios/:id/editar" element={conPermiso('usuarios.editar', <FormularioUsuario />)} />

              <Route path="/roles" element={conPermiso('roles.ver', <ListaRoles />)} />
              <Route path="/roles/:id" element={conPermiso('roles.ver', <DetalleRol />)} />

              <Route path="/auditoria" element={conPermiso('registros_auditoria.ver', <ListaAuditoria />)} />

              {/* Fase 2: planes, precios, mantenimientos programados y contratos (RF-010 a RF-021). */}
              <Route path="/calendario" element={conPermiso('mantenimientos.ver', <Calendario />)} />

              <Route path="/planes" element={conPermiso('planes.ver', <ListaPlanes />)} />
              <Route path="/planes/nuevo" element={conPermiso('planes.crear', <FormularioPlan />)} />
              <Route path="/planes/:id" element={conPermiso('planes.ver', <DetallePlan />)} />
              <Route path="/planes/:id/editar" element={conPermiso('planes.editar', <FormularioPlan />)} />

              <Route path="/contratos" element={conPermiso('contratos.ver', <ListaContratos />)} />
              <Route path="/contratos/nuevo" element={conPermiso('contratos.crear', <FormularioContrato />)} />
              <Route path="/contratos/:id" element={conPermiso('contratos.ver', <DetalleContrato />)} />
              <Route path="/contratos/:id/editar" element={conPermiso('contratos.editar', <FormularioContrato />)} />

              <Route path="/configuracion" element={conPermiso('configuracion.editar', <Configuracion />)} />

              {/* Fase 3: técnicos, agenda y órdenes de trabajo (RF-022 a RF-029). */}
              <Route path="/ordenes" element={conPermiso('ordenes.ver', <ListaOrdenes />)} />
              <Route path="/ordenes/nueva" element={conPermiso('ordenes.crear', <FormularioOrden />)} />
              <Route path="/ordenes/:id" element={conPermiso('ordenes.ver', <DetalleOrden />)} />

              <Route path="/tecnicos" element={conPermiso('tecnicos.ver', <ListaTecnicos />)} />
              <Route path="/tecnicos/nuevo" element={conPermiso('tecnicos.crear', <FormularioTecnico />)} />
              <Route path="/tecnicos/:id" element={conPermiso('tecnicos.ver', <DetalleTecnico />)} />
              <Route path="/tecnicos/:id/editar" element={conPermiso('tecnicos.editar', <FormularioTecnico />)} />

              <Route path="/emergencias" element={conPermiso('emergencias.ver', <Emergencias />)} />
              <Route path="/emergencias/:id" element={conPermiso('emergencias.ver', <DetalleEmergencia />)} />
              <Route path="/cotizaciones" element={conPermiso('cotizaciones.ver', <Cotizaciones />)} />
              <Route path="/cotizaciones/:id" element={conPermiso('cotizaciones.ver', <DetalleCotizacion />)} />
              <Route path="/certificaciones" element={conPermiso('certificaciones.ver', <Certificaciones />)} />
              <Route path="/indicadores" element={conPermiso('indicadores.ver', <Indicadores />)} />

              <Route
                path="*"
                element={
                  <EstadoVacio titulo="Página no encontrada" descripcion="La dirección no existe.">
                    <Link to="/inicio" className="boton">
                      Volver al inicio
                    </Link>
                  </EstadoVacio>
                }
              />
            </Route>
          </Routes>
        </ProveedorAutenticacion>
      </ProveedorNotificaciones>
    </BrowserRouter>
  );
}
