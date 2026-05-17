<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversa extends Model
{
    protected $fillable = [
        'tenant_id', 'telefone_cliente', 'etapa', 'contexto', 'historico_mensagens', 'atualizado_em',
    ];

    protected $casts = [
        'contexto'             => 'array',
        'historico_mensagens'  => 'array',
        'atualizado_em'        => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function adicionarMensagem(string $role, string $content): void
    {
        $historico   = $this->historico_mensagens ?? [];
        $historico[] = ['role' => $role, 'content' => $content];
        $this->historico_mensagens = array_slice($historico, -20);
        $this->atualizado_em       = now();
        $this->save();
    }
}
