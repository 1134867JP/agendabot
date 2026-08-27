<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueioAgenda extends Model
{
    protected $table = 'bloqueios_agenda';

    protected $fillable = [
        'tenant_id', 'profissional_id', 'recurso_id', 'inicio', 'fim', 'motivo',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }
}
