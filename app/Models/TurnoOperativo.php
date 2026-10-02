<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turno_id', 'sucursal_id', 'punto_venta_id', 'prioridad', 'activo'])]
class TurnoOperativo extends Model
{
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
