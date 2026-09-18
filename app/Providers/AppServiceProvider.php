<?php

namespace App\Providers;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // RolePolicy (generada por shield:generate) queda fuera de la
        // convención de descubrimiento de políticas de Laravel porque su
        // modelo (Spatie\Permission\Models\Role) no vive en app/Models --
        // sin esto, cualquier usuario con acceso al panel podría gestionar
        // roles sin que el recurso de Roles respete sus propios permisos.
        FilamentShield::enforcePolicies();
    }
}
