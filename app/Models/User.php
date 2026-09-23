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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function sucursalesSupervisadas(): BelongsToMany
    {
        return $this->belongsToMany(Sucursal::class, 'supervisor_sucursal')
            ->withTimestamps();
    }

    public function visitasSupervisor(): HasMany
    {
        return $this->hasMany(VisitaSupervisor::class, 'supervisor_id');
    }

    /** Las cuentas de colaborador dependen del estado de su ficha laboral. */
    public function estaActivoParaAcceso(): bool
    {
        return $this->colaborador?->activo ?? true;
    }

    /**
     * Cambia una contraseña sin conservarla ni exponerla y cierra las sesiones
     * anteriores de la cuenta. El cast `hashed` persiste únicamente su hash.
     */
    public function restablecerContrasena(string $password, ?int $actorId = null): void
    {
        DB::transaction(function () use ($password): void {
            $this->forceFill(['password' => $password])->save();
            $this->invalidarSesiones();
        });

        Log::notice('Contraseña restablecida desde administración.', [
            'actor_user_id' => $actorId,
            'target_user_id' => $this->getKey(),
        ]);
    }

    /** Invalida los accesos persistentes y las sesiones de navegador actuales. */
    public function invalidarSesiones(): void
    {
        $this->forceFill(['remember_token' => Str::random(60)])->save();

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $this->getKey())
            ->delete();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->estaActivoParaAcceso()
            && $this->can('Access:AdminPanel');
    }
}
