<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['colaborador_id', 'turno_id', 'turno_operativo_id', 'fecha', 'origen', 'detectado_en', 'observacion', 'asignado_por'])]
class AsignacionTurno extends Model
{
    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_PROGRAMACION_MASIVA = 'programacion_masiva';

    public const ORIGEN_DETECTADO_AUTOMATICAMENTE = 'detectado_automaticamente';

    public const ORIGEN_REGULARIZADO_MANUAL = 'regularizado_manual';

    protected $table = 'asignaciones_turno';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'detectado_en' => 'datetime',
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

    public function turnoOperativo(): BelongsTo
    {
        return $this->belongsTo(TurnoOperativo::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por');
    }

    public function ajusteAutomatico(): HasOne
    {
        return $this->hasOne(AjusteTurnoAutomatico::class);
    }

    public function resumenJornada(): HasOne
    {
        return $this->hasOne(ResumenJornada::class);
    }

    public function regularizacionSalida(): HasOne
    {
        return $this->hasOne(RegularizacionSalida::class);
    }

    public function entradaMarcacion(): ?Marcacion
    {
        return \App\Support\JornadaMarcacion::marcaciones($this->colaborador, $this)->firstWhere('tipo', Marcacion::TIPO_ENTRADA);
    }

    public function ultimaMarcacion(): ?Marcacion
    {
        return \App\Support\JornadaMarcacion::ultimaMarcacion($this->colaborador, $this);
    }

    public function vencioEn(): \Carbon\Carbon
    {
        return \App\Support\JornadaMarcacion::finVigencia($this);
    }

    public function salidaSugerida(): \Carbon\Carbon
    {
        return \App\Support\JornadaMarcacion::limites($this)['fin'];
    }
}
