<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    protected $fillable = ['tenant_id', 'cliente_id', 'profissional_id', 'servico_id', 'cliente_nome',
        'cliente_telefone', 'data_preferida', 'hora_inicio', 'hora_fim', 'status'];

    protected $casts = ['data_preferida' => 'date'];
}
