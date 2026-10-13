<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendamentoServico extends Model
{
    protected $table = 'agendamento_servicos';

    protected $fillable = ['agendamento_id', 'servico_id', 'nome', 'valor', 'duracao_minutos', 'ordem'];

    protected $casts = [
        'valor' => 'decimal:2',
        'duracao_minutos' => 'integer',
        'ordem' => 'integer',
    ];

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
