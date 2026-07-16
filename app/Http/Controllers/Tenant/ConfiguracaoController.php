<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateBotRequest;
use App\Http\Requests\Tenant\UpdateConfiguracaoRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracaoController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        $cfg    = $tenant->configuracoes ?? [];

        return Inertia::render('Tenant/Configuracoes', [
            'tenant' => array_merge($tenant->only([
                'id',
                'nome',
                'tipo_servico',
                'tipo_servico_personalizado',
                'nome_agente',
                'tom_voz',
                'instrucoes_extras',
                'bot_saudacao',
                'bot_ativo',
                'modo_bot',
                'horario_atendimento',
                'mensagem_fora_horario',
                'ramo_negocio',
                'descricao_negocio',
                'cidade',
                'endereco',
                'horarios_funcionamento',
            ]), [
                'horarios_funcionamento_semana' => $cfg['horarios_funcionamento_semana'] ?? null,
                'lembrete_ativo' => $cfg['lembrete_ativo'] ?? true,
                'lembrete_texto' => $cfg['lembrete_texto'] ?? '',
            ]),
        ]);
    }

    public function update(UpdateConfiguracaoRequest $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validated();
        $horarios = $data['horarios_funcionamento_semana'];
        unset($data['horarios_funcionamento_semana']);

        $data['horarios_funcionamento'] = $this->resumirHorarios($horarios);
        $data['configuracoes'] = array_merge($tenant->configuracoes ?? [], [
            'horarios_funcionamento_semana' => $horarios,
        ]);

        $tenant->update($data);

        return back()->with('success', 'Configurações salvas.');
    }

    public function updateBot(UpdateBotRequest $request): RedirectResponse
    {
        $tenant = app('tenant');

        $data = $request->validated();

        $configuracoes = array_merge($tenant->configuracoes ?? [], [
            'lembrete_ativo' => $data['lembrete_ativo'] ?? true,
            'lembrete_texto' => $data['lembrete_texto'] ?? null,
        ]);

        unset($data['lembrete_ativo'], $data['lembrete_texto']);

        $tenant->update(array_merge($data, ['configuracoes' => $configuracoes]));

        return back()->with('success', 'Configurações do bot salvas.');
    }

    private function resumirHorarios(array $horarios): string
    {
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $grupos = [];

        foreach ($horarios as $indice => $config) {
            if (empty($config['ativo']) || empty($config['periodos'])) {
                continue;
            }

            $faixa = collect($config['periodos'])
                ->sortBy('abertura')
                ->map(fn (array $periodo) => "{$periodo['abertura']}–{$periodo['fechamento']}")
                ->join(' e ');
            $ultimo = end($grupos) ?: null;

            if ($ultimo && $ultimo['faixa'] === $faixa && $ultimo['fim'] === $indice - 1) {
                $grupos[array_key_last($grupos)]['fim'] = $indice;
            } else {
                $grupos[] = ['inicio' => $indice, 'fim' => $indice, 'faixa' => $faixa];
            }
        }

        return collect($grupos)->map(function (array $grupo) use ($dias) {
            $nomeDias = $grupo['inicio'] === $grupo['fim']
                ? $dias[$grupo['inicio']]
                : $dias[$grupo['inicio']].'–'.$dias[$grupo['fim']];

            return "{$nomeDias} {$grupo['faixa']}";
        })->join(', ');
    }
}
