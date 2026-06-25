<?php
// app/Models/Servico.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servico extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'descricao', 'valor_min', 'valor_max', 'duracao_minutos', 'requer_avaliacao', 'ativo'];
    protected $casts = ['tenant_id' => 'integer', 'requer_avaliacao' => 'boolean', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function profissionais(): BelongsToMany { return $this->belongsToMany(Profissional::class, 'profissional_servico'); }
}
