<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignacion_turno_id',
    'colaborador_id',
    'turno_programado_id',
    'turno_efectivo_id',
    'detectado_en',
])]
class AjusteTurnoAutomatico extends Model
{
    protected $table = 'ajustes_turno_automaticos';

    protected function casts(): array
    {
        return [
            'detectado_en' => 'datetime',
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

    public function turnoProgramado(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_programado_id');
    }

    public function turnoEfectivo(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_efectivo_id');
    }
}
