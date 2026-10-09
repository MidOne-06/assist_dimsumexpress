<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[Fillable(['turno_id', 'sucursal_id', 'punto_venta_id', 'prioridad', 'activo'])]
class TurnoOperativo extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $regla): void {
            if (! $regla->getOriginal('activo') && $regla->isDirty()) {
                throw ValidationException::withMessages([
                    'turno_operativo' => 'Las reglas históricas son de solo lectura.',
                ]);
            }
        });

        static::saving(function (self $regla): void {
            if (! $regla->activo) {
                return;
            }

            $turnoVigente = Turno::query()
                ->whereKey($regla->turno_id)
                ->where('activo', true)
                ->exists();

            if (! $turnoVigente) {
                throw ValidationException::withMessages([
                    'turno_id' => 'Solo se puede habilitar una regla para un turno vigente.',
                ]);
            }

            $duplicada = static::query()
                ->where('sucursal_id', $regla->sucursal_id)
                ->where('turno_id', $regla->turno_id)
                ->when(
                    $regla->punto_venta_id === null,
                    fn ($query) => $query->whereNull('punto_venta_id'),
                    fn ($query) => $query->where('punto_venta_id', $regla->punto_venta_id),
                )
                ->where('activo', true)
                ->when($regla->exists, fn ($query) => $query->whereKeyNot($regla->getKey()))
                ->exists();

            if ($duplicada) {
                throw ValidationException::withMessages([
                    'turno_id' => 'Este turno ya está habilitado para la estación seleccionada.',
                ]);
            }
        });
    }

    /**
     * La base productiva usa el esquema PostgreSQL `public`, pero la
     * resolución sin esquema puede fallar con identificadores entre comillas
     * (como los que genera Eloquent). Declararlo evita que Filament dependa
     * del search_path de cada conexión.
     */
    public function getTable(): string
    {
        // En PostgreSQL se califica el esquema para que Eloquent no dependa
        // del search_path de la conexión. SQLite se usa en pruebas y no
        // conoce el esquema public.
        return $this->getConnection()->getDriverName() === 'pgsql'
            ? 'public.turnos_operativos'
            : 'turnos_operativos';
    }

    /**
     * Un turno manual solo es válido si la estación base del colaborador lo
     * tiene habilitado. Una configuración de caja reemplaza la del local;
     * si no hay configuración de caja, se hereda la del local.
     */
    public static function turnoHabilitadoEnEstacion(int $sucursalId, ?int $puntoVentaId, int $turnoId): bool
    {
        $base = static::query()
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true);

        $especificas = $puntoVentaId
            ? (clone $base)->where('punto_venta_id', $puntoVentaId)
            : null;

        $candidatas = $especificas && $especificas->exists()
            ? $especificas
            : $base->whereNull('punto_venta_id');

        return $candidatas->where('turno_id', $turnoId)->exists();
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'prioridad' => 'integer'];
    }

    public function turno(): BelongsTo { return $this->belongsTo(Turno::class); }
    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function puntoVenta(): BelongsTo { return $this->belongsTo(PuntoVenta::class); }
}
