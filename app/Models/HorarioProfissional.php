<?php
// app/Models/HorarioProfissional.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioProfissional extends Model
{
    protected $table = 'horarios_profissional';
    protected $fillable = ['profissional_id', 'dia_semana', 'hora_inicio', 'hora_fim', 'duracao_slot'];

    public function profissional(): BelongsTo { return $this->belongsTo(Profissional::class); }
}
