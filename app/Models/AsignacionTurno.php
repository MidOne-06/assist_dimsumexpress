<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['colaborador_id', 'turno_id', 'fecha', 'observacion', 'asignado_por'])]
class AsignacionTurno extends Model
{
    protected $table = 'asignaciones_turno';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function ajusteAutomatico(): HasOne
    {
        return $this->hasOne(AjusteTurnoAutomatico::class);
    }
}
