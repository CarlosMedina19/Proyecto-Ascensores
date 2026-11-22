<?php

namespace App\Providers;

use App\Models\AsignacionPlan;
use App\Models\Cliente;
use App\Models\Conjunto;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Permiso;
use App\Models\PlanMantenimiento;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Policies\AsignacionPlanPolicy;
use App\Policies\ClientePolicy;
use App\Policies\ConjuntoPolicy;
use App\Policies\ContratoPolicy;
use App\Policies\EdificioPolicy;
use App\Policies\EquipoPolicy;
use App\Policies\MantenimientoProgramadoPolicy;
use App\Policies\OrdenTrabajoPolicy;
use App\Policies\PermisoPolicy;
use App\Policies\PlanMantenimientoPolicy;
use App\Policies\RegistroAuditoriaPolicy;
use App\Policies\RolPolicy;
use App\Policies\TecnicoPolicy;
use App\Policies\UsuarioPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->documentacionApi();

        Gate::policy(Usuario::class, UsuarioPolicy::class);
        Gate::policy(Rol::class, RolPolicy::class);
        Gate::policy(RegistroAuditoria::class, RegistroAuditoriaPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Conjunto::class, ConjuntoPolicy::class);
        Gate::policy(Edificio::class, EdificioPolicy::class);
        Gate::policy(Equipo::class, EquipoPolicy::class);
        Gate::policy(Permiso::class, PermisoPolicy::class);
        Gate::policy(PlanMantenimiento::class, PlanMantenimientoPolicy::class);
        Gate::policy(AsignacionPlan::class, AsignacionPlanPolicy::class);
        Gate::policy(MantenimientoProgramado::class, MantenimientoProgramadoPolicy::class);
        Gate::policy(Contrato::class, ContratoPolicy::class);
        Gate::policy(Tecnico::class, TecnicoPolicy::class);
        Gate::policy(OrdenTrabajo::class, OrdenTrabajoPolicy::class);

        Gate::define(
            'configurar',
            fn (Usuario $usuario): bool => $usuario->tienePermiso('configuracion.editar')
        );

        Gate::define(
            'verResumen',
            fn (Usuario $usuario): bool => $usuario->tienePermiso('resumen.ver')
        );

        /*
         * RF-001 y RF-003: un token deja de ser válido cuando el usuario está
         * inactivo o cuando pasaron más minutos de inactividad de los permitidos.
         * Sanctum evalúa esta función antes de actualizar last_used_at.
         */
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $token, bool $esValido): bool {
            if (! $esValido) {
                return false;
            }

            $usuario = $token->tokenable;

            if ($usuario instanceof Usuario && ! $usuario->activo) {
                return false;
            }

            $minutos = (int) config('sigma.sesion.minutos_inactividad');
            $ultimoUso = $token->last_used_at ?? $token->created_at;

            return $minutos <= 0 || $ultimoUso->gt(now()->subMinutes($minutos));
        });

        RateLimiter::for('iniciar-sesion', function (Request $solicitud) {
            $correo = Str::lower((string) $solicitud->input('correo'));
            $respuesta = fn () => response()->json(['mensaje' => 'Demasiados intentos de inicio de sesión. Espere un minuto.'], 429);

            return [
                // Por cuenta: 5 intentos por minuto para un mismo correo desde una misma dirección.
                Limit::perMinute(5)->by($correo.'|'.$solicitud->ip())->response($respuesta),
                // Por dirección: evita probar contraseñas en muchas cuentas distintas desde una misma IP
                // (relleno de credenciales), que el límite por correo no detiene.
                Limit::perMinute(20)->by('ip|'.$solicitud->ip())->response($respuesta),
            ];
        });

        // Códigos de verificación (segundo paso y recuperación por teléfono): 10 por minuto por dirección.
        RateLimiter::for('verificar-codigo', function (Request $solicitud) {
            return Limit::perMinute(10)->by('codigo|'.$solicitud->ip())->response(
                fn () => response()->json(['mensaje' => 'Demasiados intentos con el código. Espere un minuto.'], 429)
            );
        });

        RateLimiter::for('restablecer-contrasena', function (Request $solicitud) {
            return Limit::perMinute(5)->by($solicitud->ip())->response(
                fn () => response()->json(['mensaje' => 'Demasiadas solicitudes. Espere un minuto.'], 429)
            );
        });

        // Reporte por QR sin sesión: pocos envíos por dirección y por ascensor para evitar abusos.
        RateLimiter::for('reporte-qr', function (Request $solicitud) {
            return [
                Limit::perMinute(3)->by('qr-ip|'.$solicitud->ip()),
                Limit::perHour(20)->by('qr-ascensor|'.$solicitud->route('codigo')),
            ];
        });

        RateLimiter::for('recuperar-contrasena', function (Request $solicitud) {
            return Limit::perMinute(3)->by($solicitud->ip())->response(
                fn () => response()->json(['mensaje' => 'Demasiadas solicitudes. Espere un minuto.'], 429)
            );
        });

        // Asistente de la página pública (sin sesión; cada pregunta puede ir a la IA, que tiene costo).
        // Una conversación real no pasa de unos pocos mensajes por minuto.
        // Límite general de la API (300 solicitudes por minuto por usuario, o por IP sin sesión). El panel
        // usa una fracción de eso; frena scripts con error o un token robado que intente saturar el servidor.
        RateLimiter::for('api', function (Request $solicitud) {
            return Limit::perMinute((int) config('sigma.limite_api_por_minuto', 300))
                ->by($solicitud->user()?->id ? 'api-usuario|'.$solicitud->user()->id : 'api-ip|'.$solicitud->ip())
                ->response(fn () => response()->json(['mensaje' => 'Demasiadas solicitudes seguidas. Espere un momento.'], 429));
        });

        // /up (chequeo de salud de Docker y del monitor externo) también comprueba la base de datos.
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        RateLimiter::for('asistente-publico', function (Request $solicitud) {
            $respuesta = fn () => response()->json(['mensaje' => 'Ha enviado muchos mensajes seguidos. Intente de nuevo en unos minutos.'], 429);

            return [
                Limit::perMinute(10)->by('asistente-min|'.$solicitud->ip())->response($respuesta),
                Limit::perHour(60)->by('asistente-hora|'.$solicitud->ip())->response($respuesta),
            ];
        });
    }

    /**
     * RNF-11: documentación OpenAPI generada desde las rutas, validaciones y respuestas (Scramble).
     * Scramble es dependencia de desarrollo: en producción no está instalado y esto no hace nada.
     * Se ve en /docs/api (solo en desarrollo) y se exporta con `php artisan scramble:export`.
     */
    private function documentacionApi(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        // Scramble lee su configuración antes que este proveedor: título, versión y servidores se fijan
        // directamente sobre el documento generado.
        Scramble::configure()->withDocumentTransformers(function ($documento) {
            $documento->info->title = 'SIGMA API';
            $documento->info->setVersion('1.1');
            $documento->info->setDescription('API de SIGMA (Sistema Integral de Gestión y Mantenimiento de Ascensores). '
                .'Todas las rutas, salvo el ingreso, la recuperación de contraseña y el reporte por código QR, '
                .'exigen el token que entrega POST /v1/iniciar-sesion en el encabezado Authorization: Bearer. '
                .'Cada rol solo accede a las rutas que sus permisos le permiten.');
            $documento->servers = [];
            $documento->addServer(Server::make('https://sigma.proyectoscampus.top/api')->setDescription('Producción'));
            $documento->addServer(Server::make('http://127.0.0.1:8000/api')->setDescription('Desarrollo'));
            $documento->secure(SecurityScheme::http('bearer'));
        });
    }
}
