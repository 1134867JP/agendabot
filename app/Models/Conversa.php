<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversa extends Model
{
    protected $fillable = [
        'tenant_id', 'cliente_id', 'telefone_cliente',
        'status_v2', 'ultima_mensagem_em',
    ];

    protected $casts = [
        'tenant_id'          => 'integer',
        'ultima_mensagem_em' => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function mensagens(): HasMany { return $this->hasMany(Mensagem::class); }

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
