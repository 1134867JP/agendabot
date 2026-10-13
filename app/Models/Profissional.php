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

    protected $fillable = ['tenant_id', 'user_id', 'nome', 'especialidades', 'ativo'];

    protected $casts = ['tenant_id' => 'integer', 'especialidades' => 'array', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return $this->belongsToMany(Servico::class, 'profissional_servico')
            ->withPivot(['valor', 'duracao_minutos']);
    }

    /**
     * Horários do dia na grade do profissional. Com $duracao, um horário só
     * fica disponível se o atendimento inteiro couber (antes do fim do
     * expediente, fora do almoço, sem encostar em outro agendamento ou
     * bloqueio, respeitando a folga $buffer entre atendimentos).
     *
     * @return list<array{hora: string, disponivel: bool}>
     */
    public function slotsDisponiveis(Carbon $data, ?int $duracao = null, int $buffer = 0): array
    {
        $diaSemana = (int) $data->format('N'); // Carbon: 1=seg..7=dom; banco: 0=dom..6=sab
        if ($diaSemana === 7) {
            $diaSemana = 0;
        }
        $horario = $this->horarios()->where('dia_semana', $diaSemana)->first();

        if (! $horario) {
            return [];
        }

        $tz = $this->tenant?->resolvedTimezone() ?? 'America/Sao_Paulo';
        $dia = $data->format('Y-m-d');
        $inicio = Carbon::createFromFormat('Y-m-d H:i', $dia.' '.substr($horario->hora_inicio, 0, 5), $tz);
        $fim = Carbon::createFromFormat('Y-m-d H:i', $dia.' '.substr($horario->hora_fim, 0, 5), $tz);
        $passo = (int) $horario->duracao_slot ?: 30;
        $necessario = max((int) $duracao, 0) ?: $passo;

        $inicioDia = Carbon::createFromFormat('Y-m-d H:i', $dia.' 00:00', $tz);
        $fimDia = $inicioDia->copy()->addDay();

        $ocupados = $this->agendamentos()
            ->where('data_hora', '<', $fimDia)
            ->where('data_hora', '>=', $inicioDia->copy()->subDay())
            ->whereNotIn('status', ['cancelado'])
            ->get(['data_hora', 'duracao_minutos'])
            ->map(function ($a) use ($tz, $passo, $buffer) {
                $comeco = Carbon::parse($a->getRawOriginal('data_hora'), $tz);

                return [
                    $comeco->copy()->subMinutes($buffer),
                    $comeco->copy()->addMinutes((int) ($a->duracao_minutos ?: $passo) + $buffer),
                ];
            })
            ->concat($this->bloqueiosAgenda()
                ->where('inicio', '<', $fimDia)
                ->where('fim', '>', $inicioDia)
                ->get(['inicio', 'fim'])
                ->map(fn ($b) => [Carbon::parse($b->inicio)->setTimezone($tz), Carbon::parse($b->fim)->setTimezone($tz)]));

        $intervalo = $horario->intervaloNoDia($data, $tz);
        $agora = Carbon::now($tz);
        $slots = [];
        $cursor = $inicio->copy();
        while ($cursor->copy()->addMinutes($necessario)->lte($fim)) {
            $slotFim = $cursor->copy()->addMinutes($necessario);
            // Atendimento que encosta no almoço não é oferecido; a grade recomeça na volta.
            // Qualquer posição até a volta também invadiria a pausa.
            if ($intervalo && $cursor->lt($intervalo[1]) && $slotFim->gt($intervalo[0])) {
                $cursor = $intervalo[1]->copy();

                continue;
            }
            if ($cursor->gt($agora)) {
                $ocupado = $ocupados->contains(fn ($o) => $cursor->lt($o[1]) && $slotFim->gt($o[0]));
                $slots[] = ['hora' => $cursor->format('H:i'), 'disponivel' => ! $ocupado];
            }
            $cursor->addMinutes($passo);
        }

        return $slots;
    }
}
