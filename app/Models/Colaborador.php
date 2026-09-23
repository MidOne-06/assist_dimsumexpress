<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'sucursal_id', 'punto_venta_id', 'nombre_completo', 'documento_identidad', 'cargo', 'fecha_ingreso', 'activo'])]
class Colaborador extends Model
{
    protected $table = 'colaboradores';

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function asignacionesTurno(): HasMany
    {
        return $this->hasMany(AsignacionTurno::class);
    }

    public function marcaciones(): HasMany
    {
        return $this->hasMany(Marcacion::class);
    }

    public function turnoDelDia(?string $fecha = null): ?Turno
    {
        $asignacion = $this->asignacionesTurno()
            ->where('fecha', $fecha ?? now()->toDateString())
            ->first();

        return $asignacion?->turno;
    }

    /**
     * Da de baja sin borrar el historial laboral ni las marcaciones.
     * También invalida sesiones y el token "recordarme" de su cuenta.
     */
    public function desactivarAcceso(): void
    {
        DB::transaction(function (): void {
            $this->update(['activo' => false]);

            if (! $this->user_id) {
                return;
            }

            $this->user?->forceFill(['remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $this->user_id)->delete();
        });
    }

    /** Reactiva la cuenta existente sin modificar su contraseña ni historial. */
    public function reactivarAcceso(): void
    {
        $this->update(['activo' => true]);
    }
}
