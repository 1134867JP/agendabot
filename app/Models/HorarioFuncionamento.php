<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioFuncionamento extends Model
{
    protected $fillable = ['recurso_id', 'dia_semana', 'abertura', 'fechamento'];

    protected $table = 'horarios_funcionamento';

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(Recurso::class);
    }
}
