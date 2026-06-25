<?php
// app/Models/Cliente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'telefone', 'cpf', 'data_nascimento', 'observacoes'];
    protected $casts = ['tenant_id' => 'integer', 'data_nascimento' => 'date'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function agendamentos(): HasMany { return $this->hasMany(Agendamento::class); }
    public function conversas(): HasMany { return $this->hasMany(Conversa::class); }
}
