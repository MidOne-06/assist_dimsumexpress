<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignacion_turno_id',
    'colaborador_id',
    'tipo',
    'detectada_en',
    'resuelta_en',
    'resuelta_por_id',
    'observacion_reporte',
    'observacion_resolucion',
])]
class IncidenciaMarcacion extends Model
{
    protected $table = 'incidencias_marcacion';

    public const TIPO_RETORNO_REFRIGERIO_PENDIENTE = 'retorno_refrigerio_pendiente';

    public const TIPO_SALIDA_TURNO_PENDIENTE = 'salida_turno_pendiente';

    public const TIPO_MARCACION_OMITIDA = 'marcacion_omitida';

    protected function casts(): array
    {
        return [
            'detectada_en' => 'datetime',
            'resuelta_en' => 'datetime',
        ];
    }

    public function asignacionTurno(): BelongsTo
    {
        return $this->belongsTo(AsignacionTurno::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function resueltaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelta_por_id');
    }

    public function estaPendiente(): bool
    {
        return $this->resuelta_en === null;
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_RETORNO_REFRIGERIO_PENDIENTE => 'Retorno de refrigerio pendiente',
            self::TIPO_SALIDA_TURNO_PENDIENTE => 'Salida de turno pendiente',
            self::TIPO_MARCACION_OMITIDA => 'Marcación omitida reportada',
            default => $tipo,
        };
    }
}
