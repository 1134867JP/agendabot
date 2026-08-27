<?php

// app/Models/Profissional.php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profissional extends Model
{
    protected $table = 'profissionais';

    protected $fillable = ['tenant_id', 'nome', 'especialidades', 'ativo'];

    protected $casts = ['tenant_id' => 'integer', 'especialidades' => 'array', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioProfissional::class);
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class);
    }

    public function bloqueiosAgenda(): HasMany
    {
        return $this->hasMany(BloqueioAgenda::class);
    }

    public function servicos(): BelongsToMany
    {
        return $this->belongsToMany(Servico::class, 'profissional_servico');
    }

    public function slotsDisponiveis(Carbon $data): array
    {
        $diaSemana = (int) $data->format('N'); // 1=seg..7=dom → usar 1..6 para seg..sab
        // Carbon format('N'): 1=seg..7=dom; banco usa 0=dom..6=sab
        if ($diaSemana === 7) {
            $diaSemana = 0;
        }
        $horario = $this->horarios()->where('dia_semana', $diaSemana)->first();

        if (! $horario) {
            return [];
        }

        $tz = config('app.timezone', 'America/Sao_Paulo');
        $slots = [];
        $horaInicio = substr($horario->hora_inicio, 0, 5); // garante HH:MM
        $horaFim = substr($horario->hora_fim, 0, 5);
        $inicio = Carbon::createFromFormat('Y-m-d H:i', $data->format('Y-m-d').' '.$horaInicio, $tz);
        $fim = Carbon::createFromFormat('Y-m-d H:i', $data->format('Y-m-d').' '.$horaFim, $tz);
        $duracao = $horario->duracao_slot;

        $inicioDia = $data->copy()->startOfDay();
        $fimDia = $data->copy()->endOfDay();

        $agendados = $this->agendamentos()
            ->whereBetween('data_hora', [$inicioDia, $fimDia])
            ->whereNotIn('status', ['cancelado'])
            ->get(['data_hora', 'duracao_minutos'])
            ->map(fn ($a) => [
                'inicio' => Carbon::parse($a->data_hora, $tz),
                'fim' => Carbon::parse($a->data_hora, $tz)->addMinutes((int) ($a->duracao_minutos ?? $duracao)),
            ])
            ->all();

        $agendadosCollection = collect($agendados);
        $bloqueios = $this->bloqueiosAgenda()
            ->where('inicio', '<', $fimDia)
            ->where('fim', '>', $inicioDia)
            ->get(['inicio', 'fim'])
            ->map(fn ($bloqueio) => [
                'inicio' => Carbon::parse($bloqueio->inicio, $tz),
                'fim' => Carbon::parse($bloqueio->fim, $tz),
            ]);
        $agora = Carbon::now($tz);
        $cursor = $inicio->copy();
        while ($cursor->copy()->addMinutes($duracao)->lte($fim)) {
            $hora = $cursor->format('H:i');
            $slotFim = $cursor->copy()->addMinutes($duracao);
            if ($cursor->gt($agora)) {
                $ocupado = $agendadosCollection->contains(
                    fn ($a) => $cursor->lt($a['fim']) && $slotFim->gt($a['inicio'])
                ) || $bloqueios->contains(
                    fn ($bloqueio) => $cursor->lt($bloqueio['fim']) && $slotFim->gt($bloqueio['inicio'])
                );
                $slots[] = ['hora' => $hora, 'disponivel' => ! $ocupado];
            }
            $cursor->addMinutes($duracao);
        }

        return $slots;
    }
}
