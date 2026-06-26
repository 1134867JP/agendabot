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

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function horarios(): HasMany { return $this->hasMany(HorarioProfissional::class); }
    public function agendamentos(): HasMany { return $this->hasMany(Agendamento::class); }
    public function servicos(): BelongsToMany { return $this->belongsToMany(Servico::class, 'profissional_servico'); }

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

        $tz     = config('app.timezone', 'America/Sao_Paulo');
        $slots  = [];
        $inicio = Carbon::createFromFormat('Y-m-d H:i', $data->format('Y-m-d') . ' ' . $horario->hora_inicio, $tz);
        $fim    = Carbon::createFromFormat('Y-m-d H:i', $data->format('Y-m-d') . ' ' . $horario->hora_fim, $tz);
        $duracao = $horario->duracao_slot;

        $inicioDia = $data->copy()->startOfDay();
        $fimDia    = $data->copy()->endOfDay();

        $agendados = $this->agendamentos()
            ->whereBetween('data_hora', [$inicioDia, $fimDia])
            ->whereNotIn('status', ['cancelado'])
            ->pluck('data_hora')
            ->map(fn ($dt) => Carbon::parse($dt, $tz)->format('H:i'))
            ->toArray();

        $cursor = $inicio->copy();
        while ($cursor->copy()->addMinutes($duracao)->lte($fim)) {
            $hora = $cursor->format('H:i');
            $slots[] = ['hora' => $hora, 'disponivel' => ! in_array($hora, $agendados)];
            $cursor->addMinutes($duracao);
        }

        return $slots;
    }
}
