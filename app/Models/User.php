<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function colaborador(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Colaborador::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Roles y permisos nativos de Filament (Shield + spatie/laravel-permission,
        // 2026-09-18), en reemplazo de la lista fija FILAMENT_ADMIN_EMAILS -- un
        // colaborador raso (solo usa /marcar, nunca se le asigna un rol) nunca
        // tiene ningún rol, así que esto lo sigue bloqueando igual que antes.
        // Qué puede HACER cada rol dentro del panel lo deciden los permisos
        // generados por Shield sobre cada Resource/Page, no este método.
        return $this->roles()->exists();
    }
}
