<?php

// app/Models/OpcaoExtra.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcaoExtra extends Model
{
    protected $table = 'opcoes_extras';

    protected $fillable = ['tenant_id', 'tipo', 'nome', 'ativo'];

    protected $casts = ['tenant_id' => 'integer', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
