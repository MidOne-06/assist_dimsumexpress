<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'colaborador_id', 'user_id', 'generado_por_id', 'token_hash', 'expira_en',
    'usado_en', 'revocado_en', 'generado_desde_ip', 'usado_desde_ip', 'user_agent_uso',
])]
class EnlaceAccesoColaborador extends Model
{
    protected $table = 'enlaces_acceso_colaborador';

    protected function casts(): array
    {
        return [
            'expira_en' => 'datetime',
            'usado_en' => 'datetime',
            'revocado_en' => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por_id');
    }

    public function estaVigente(): bool
    {
        return $this->usado_en === null
            && $this->revocado_en === null
            && $this->expira_en->isFuture();
    }
}
