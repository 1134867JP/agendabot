<?php
// app/Models/Mensagem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensagem extends Model
{
    protected $fillable = ['conversa_id', 'remetente', 'conteudo', 'evolution_message_id', 'enviada_em'];
    protected $casts = ['enviada_em' => 'datetime'];

    public function conversa(): BelongsTo { return $this->belongsTo(Conversa::class); }
}
