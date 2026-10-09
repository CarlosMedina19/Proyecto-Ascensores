<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Building;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BuildingPolicy;
use App\Policies\ClientPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Building::class, BuildingPolicy::class);
        Gate::policy(Equipment::class, EquipmentPolicy::class);

        Gate::define(
            'viewDashboard',
            fn (User $user): bool => $user->hasPermission('dashboard.view')
        );

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
