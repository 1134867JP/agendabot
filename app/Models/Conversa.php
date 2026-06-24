<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversa extends Model
{
    protected $fillable = [
        'tenant_id', 'cliente_id', 'telefone_cliente',
        'etapa', 'contexto', 'historico_mensagens', 'atualizado_em',
        // v2
        'status_v2', 'ultima_mensagem_em',
    ];

    protected $casts = [
        'contexto'            => 'array',
        'historico_mensagens' => 'array',
        'atualizado_em'       => 'datetime',
        'ultima_mensagem_em'  => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function mensagens(): HasMany { return $this->hasMany(Mensagem::class); }

    // Método legado — mantido para compatibilidade com BotService antigo
    public function adicionarMensagem(string $role, string $content): void
    {
        $historico = $this->historico_mensagens ?? [];
        $historico[] = ['role' => $role, 'content' => $content];
        $this->historico_mensagens = array_slice($historico, -10);
        $this->atualizado_em = now();
        $this->save();
    }

    // v2: salvar mensagem na tabela mensagens
    public function registrarMensagem(string $remetente, string $conteudo, ?string $evolutionId = null): Mensagem
    {
        $mensagem = $this->mensagens()->create([
            'remetente'           => $remetente,
            'conteudo'            => $conteudo,
            'evolution_message_id' => $evolutionId,
            'enviada_em'          => now(),
        ]);

        $this->update(['ultima_mensagem_em' => now()]);

        return $mensagem;
    }
}
