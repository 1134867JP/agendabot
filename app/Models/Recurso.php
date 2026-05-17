<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recurso extends Model
{
    protected $fillable = [
        'tenant_id', 'nome', 'descricao', 'valor_hora', 'duracao_padrao_minutos', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'valor_hora' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function horariosFuncionamento(): HasMany
    {
        return $this->hasMany(HorarioFuncionamento::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function slotsDisponiveis(Carbon $data): array
    {
        $diaSemana = $data->dayOfWeek;

        $horario = $this->horariosFuncionamento()
            ->where('dia_semana', $diaSemana)
            ->first();

        if (! $horario) {
            return [];
        }

        $abertura  = Carbon::parse($data->format('Y-m-d') . ' ' . $horario->abertura);
        $fechamento = Carbon::parse($data->format('Y-m-d') . ' ' . $horario->fechamento);
        $duracao    = $this->duracao_padrao_minutos;

        $agendados = $this->agendamentos()
            ->where('status', '!=', 'cancelado')
            ->whereDate('inicio', $data->format('Y-m-d'))
            ->get(['inicio', 'fim']);

        $slots = [];
        $cursor = $abertura->copy();

        while ($cursor->copy()->addMinutes($duracao)->lte($fechamento)) {
            $fimSlot = $cursor->copy()->addMinutes($duracao);

            $ocupado = $agendados->contains(function ($ag) use ($cursor, $fimSlot) {
                return Carbon::parse($ag->inicio)->lt($fimSlot)
                    && Carbon::parse($ag->fim)->gt($cursor);
            });

            $slots[] = [
                'hora'       => $cursor->format('H:i'),
                'disponivel' => ! $ocupado,
            ];

            $cursor->addMinutes($duracao);
        }

        return $slots;
    }
}
