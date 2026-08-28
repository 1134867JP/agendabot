<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id', 'conversa_id', 'mensagem_id', 'agendamento_id',
        'telefone', 'conteudo', 'purpose', 'idempotency_key', 'status',
        'attempts', 'last_error', 'locked_at', 'sent_at', 'failed_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'locked_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class);
    }

    public function mensagem(): BelongsTo
    {
        return $this->belongsTo(Mensagem::class);
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class);
    }
}
