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

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        // Mientras solo exista el panel "admin" (gestión), el acceso se limita a
        // los correos de administradores listados en FILAMENT_ADMIN_EMAILS. Cuando
        // se construya el flujo de marcado para colaboradores, este método deberá
        // ampliarse (o crearse un panel/guard separado) para permitirles solo esa
        // vista, sin darles acceso a los recursos administrativos.
        $correosAdmin = array_filter(array_map(
            'trim',
            explode(',', (string) config('services.filament_admin_emails', ''))
        ));

        return in_array($this->email, $correosAdmin, true);
    }
}
