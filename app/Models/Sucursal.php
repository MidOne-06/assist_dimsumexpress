<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

// token_pantalla NO está en Fillable a propósito: es el secreto de la
// estación física, se genera solo (ver booted()) y nunca debe poder
// llegar por un formulario ni por mass-assignment.
#[Fillable(['nombre', 'tipo', 'direccion', 'activo'])]
class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected static function booted(): void
    {
        static::creating(function (Sucursal $sucursal) {
            $sucursal->token_pantalla ??= Str::random(40);
        });
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function esPlanta(): bool
    {
        return $this->tipo === 'planta';
    }

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(QrToken::class);
    }

    /**
     * Enlace estable de la estación física para esta sucursal (incluye la
     * clave real). No hay otra forma de recuperarlo desde el panel -- sin
     * esto, configurar una pantalla física obligaba a leer la base de datos
     * a mano (hallazgo de la auditoría, 2026-09-18).
     */
    public function enlaceEstacion(): string
    {
        return route('estacion-marcado.show', ['sucursal' => $this->id, 'clave' => $this->token_pantalla]);
    }

    public function enlaceVisitaSupervisor(): string
    {
        // Compatibilidad con enlaces generados antes de que el QR de visita
        // fuese dinámico: ahora siempre abre la estación no registrable.
        return $this->enlaceEstacionVisita();
    }

    /** Pantalla física que muestra el QR sin registrar una visita. */
    public function enlaceEstacionVisita(): string
    {
        return route('estacion-visita.show', ['sucursal' => $this->id, 'clave' => $this->token_pantalla]);
    }

    public function regenerarTokenPantalla(): void
    {
        $this->forceFill(['token_pantalla' => Str::random(40)])->save();
    }
}
