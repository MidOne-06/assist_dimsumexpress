<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignacion_turno_id',
    'colaborador_id',
    'marcacion_id',
    'regularizada_por_id',
    'salida_en',
    'motivo',
    'ip_origen',
    'user_agent',
])]
class RegularizacionSalida extends Model
{
    protected $table = 'regularizaciones_salida';

    protected function casts(): array
    {
        return [
            'salida_en' => 'datetime',
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

    public function marcacion(): BelongsTo
    {
        return $this->belongsTo(Marcacion::class);
    }

    public function regularizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularizada_por_id');
    }
}
