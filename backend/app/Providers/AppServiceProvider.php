<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Permiso;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Usuario;
use App\Policies\ClientePolicy;
use App\Policies\EdificioPolicy;
use App\Policies\EquipoPolicy;
use App\Policies\PermisoPolicy;
use App\Policies\RegistroAuditoriaPolicy;
use App\Policies\RolPolicy;
use App\Policies\UsuarioPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Usuario::class, UsuarioPolicy::class);
        Gate::policy(Rol::class, RolPolicy::class);
        Gate::policy(RegistroAuditoria::class, RegistroAuditoriaPolicy::class);
        Gate::policy(Cliente::class, ClientePolicy::class);
        Gate::policy(Edificio::class, EdificioPolicy::class);
        Gate::policy(Equipo::class, EquipoPolicy::class);
        Gate::policy(Permiso::class, PermisoPolicy::class);

        Gate::define(
            'verResumen',
            fn (Usuario $usuario): bool => $usuario->tienePermiso('resumen.ver')
        );

        RateLimiter::for('iniciar-sesion', function (Request $solicitud) {
            $correo = Str::lower((string) $solicitud->input('correo'));

            return Limit::perMinute(5)->by($correo.'|'.$solicitud->ip());
        });
    }
}
