<?php

// app/Models/HorarioProfissional.php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioProfissional extends Model
{
    protected $table = 'horarios_profissional';

    protected $fillable = ['profissional_id', 'dia_semana', 'hora_inicio', 'hora_fim', 'intervalo_inicio', 'intervalo_fim', 'duracao_slot'];

    /**
     * Pausa do dia (almoço) como [inicio, fim] no fuso informado, ou null.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function intervaloNoDia(Carbon $data, string $tz): ?array
    {
        if (! $this->intervalo_inicio || ! $this->intervalo_fim) {
            return null;
        }

        $dia = $data->format('Y-m-d');

        return [
            Carbon::createFromFormat('Y-m-d H:i', $dia.' '.substr($this->intervalo_inicio, 0, 5), $tz),
            Carbon::createFromFormat('Y-m-d H:i', $dia.' '.substr($this->intervalo_fim, 0, 5), $tz),
        ];
    }

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }
}
