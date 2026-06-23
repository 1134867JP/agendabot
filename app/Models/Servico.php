<?php
// app/Models/Servico.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servico extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'descricao', 'valor_min', 'valor_max', 'duracao_minutos', 'requer_avaliacao', 'ativo'];
    protected $casts = ['requer_avaliacao' => 'boolean', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
