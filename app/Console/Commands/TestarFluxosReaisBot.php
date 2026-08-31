<?php

namespace App\Console\Commands;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\HorarioProfissional;
use App\Models\OutboundMessage;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Services\ConversationSimulatorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class TestarFluxosReaisBot extends Command
{
    protected $signature = 'bot:testar-fluxos-reais {--provider=gemini : Provider primário (gemini ou groq)}';

    protected $description = 'Executa cenários reais de IA em dados descartáveis, sem acionar o WhatsApp';

    public function handle(ConversationSimulatorService $simulador): int
    {
        $provider = (string) $this->option('provider');
        if (! in_array($provider, ['gemini', 'groq'], true)) {
            $this->error('Provider inválido. Use gemini ou groq.');

            return self::INVALID;
        }

        $sufixo = Str::lower(Str::random(10));
        $tenant = $this->criarTenant($sufixo, $provider);
        $falhas = [];

        $this->info("Tenant temporário criado: {$tenant->slug}");

        try {
            [$profissional, $servico] = $this->configurarAgenda($tenant);
            $cliente = Cliente::create([
                'tenant_id' => $tenant->id,
                'nome' => 'Cliente de Validação',
                'telefone' => '555'.random_int(100000000, 999999999),
            ]);
            $amanha = Carbon::tomorrow($tenant->resolvedTimezone())->setTime(10, 0);
            $agendamento = $this->criarAgendamento($tenant, $cliente, $profissional, $servico, $amanha);

            $this->executar('IA reconhece agendamento existente', function () use ($simulador, $tenant, $cliente, $agendamento, $sufixo): void {
                $antes = Agendamento::whereKey($agendamento->id)->value('id');
                $resultado = $simulador->enviar($tenant, $cliente->telefone, 'Qual é meu próximo agendamento?', 'real_existing_'.$sufixo);

                $this->line('  Resposta: '.$resultado['resposta']);
                $this->garantir($antes === $agendamento->id, 'O agendamento existente foi alterado ao apenas consultar.');
            }, $falhas);

            $this->executar('IA cancela agendamento', function () use ($simulador, $tenant, $cliente, $agendamento, $sufixo): void {
                $resultado = $simulador->enviar($tenant, $cliente->telefone, 'Pode cancelar meu agendamento, por favor.', 'real_cancel_'.$sufixo);
                $status = $agendamento->fresh()->status;

                $this->line('  Resposta: '.$resultado['resposta']);
                $this->garantir($status === 'cancelado', "Cancelamento não concluído (status: {$status}).");
            }, $falhas);

            $reagendar = $this->criarAgendamento($tenant, $cliente, $profissional, $servico, Carbon::tomorrow($tenant->resolvedTimezone())->setTime(11, 0));
            $this->executar('IA reagenda agendamento', function () use ($simulador, $tenant, $cliente, $reagendar, $sufixo): void {
                $resultado = $simulador->enviar($tenant, $cliente->telefone, "Quero remarcar meu agendamento #{$reagendar->id} para amanhã às 14:00.", 'real_reschedule_'.$sufixo);
                $hora = $reagendar->fresh()->data_hora?->setTimezone($tenant->resolvedTimezone())->format('H:i');

                $this->line('  Resposta: '.$resultado['resposta']);
                $this->garantir($hora === '14:00', "Reagendamento não concluído (horário: {$hora}).");
            }, $falhas);

            $novoCliente = Cliente::create([
                'tenant_id' => $tenant->id,
                'nome' => 'Cliente Novo',
                'telefone' => '555'.random_int(100000000, 999999999),
            ]);
            $this->executar('IA cria agendamento após confirmação', function () use ($simulador, $tenant, $novoCliente, $profissional, $servico, $sufixo): void {
                $simulador->enviar(
                    $tenant,
                    $novoCliente->telefone,
                    "Quero agendar {$servico->nome} com {$profissional->nome} amanhã às 15:00.",
                    'real_booking_'.$sufixo,
                );
                $resultado = $simulador->enviar($tenant, $novoCliente->telefone, 'Confirmo. Meu nome completo é Cliente Novo.', 'real_booking_confirm_'.$sufixo);
                $criado = Agendamento::where('tenant_id', $tenant->id)->where('cliente_id', $novoCliente->id)->exists();

                $this->line('  Resposta: '.$resultado['resposta']);
                $this->garantir($criado, 'A IA não criou o agendamento solicitado.');
            }, $falhas);

            $this->executar('Lembrete de 24h é gerado sem WhatsApp', function () use ($simulador, $reagendar): void {
                $texto = $simulador->preverLembrete($reagendar->fresh(['tenant', 'cliente', 'profissional', 'recurso']));

                $this->line('  Prévia: '.str_replace("\n", ' ', $texto));
                $this->garantir(str_contains($texto, 'Lembrando que você tem um agendamento *amanhã*'), 'Texto do lembrete de 24h inválido.');
            }, $falhas);

            $this->executar('Nenhuma mensagem é enviada ao WhatsApp', function () use ($tenant): void {
                $this->garantir(OutboundMessage::where('tenant_id', $tenant->id)->count() === 0, 'O simulador criou uma mensagem de saída.');
            }, $falhas);
        } finally {
            Conversa::where('tenant_id', $tenant->id)->each(function (Conversa $conversa): void {
                $conversa->mensagens()->delete();
                $conversa->delete();
            });
            Agendamento::where('tenant_id', $tenant->id)->delete();
            Cliente::where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
            $this->line('Dados temporários removidos.');
        }

        if ($falhas !== []) {
            $this->error('Falhas: '.implode(' | ', $falhas));

            return self::FAILURE;
        }

        $this->info('Todos os cenários reais foram aprovados.');

        return self::SUCCESS;
    }

    private function criarTenant(string $sufixo, string $provider): Tenant
    {
        return Tenant::create([
            'nome' => 'Validação IA '.$sufixo,
            'slug' => 'validacao-ia-'.$sufixo,
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'bot_ativo' => true,
            'timezone' => 'America/Sao_Paulo',
            'configuracoes' => ['ai' => ['provider' => $provider, 'fallback_providers' => ['groq']]],
        ]);
    }

    private function configurarAgenda(Tenant $tenant): array
    {
        $profissional = Profissional::create(['tenant_id' => $tenant->id, 'nome' => 'Dra. Teste', 'ativo' => true]);
        $servico = Servico::create(['tenant_id' => $tenant->id, 'nome' => 'Consulta de teste', 'duracao_minutos' => 30, 'ativo' => true]);
        $profissional->servicos()->attach($servico->id);

        foreach (range(0, 6) as $dia) {
            HorarioProfissional::create([
                'profissional_id' => $profissional->id,
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fim' => '18:00',
                'duracao_slot' => 30,
            ]);
        }

        return [$profissional, $servico];
    }

    private function criarAgendamento(Tenant $tenant, Cliente $cliente, Profissional $profissional, Servico $servico, Carbon $dataHora): Agendamento
    {
        return Agendamento::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'cliente_nome' => $cliente->nome,
            'cliente_telefone' => $cliente->telefone,
            'profissional_id' => $profissional->id,
            'servico_id' => $servico->id,
            'data_hora' => $dataHora,
            'inicio' => $dataHora,
            'fim' => $dataHora->copy()->addMinutes(30),
            'duracao_minutos' => 30,
            'status' => 'pendente',
            'origem' => 'teste_ia',
        ]);
    }

    private function executar(string $nome, callable $cenario, array &$falhas): void
    {
        try {
            $cenario();
            $this->info("✓ {$nome}");
        } catch (\Throwable $e) {
            $falhas[] = "{$nome}: {$e->getMessage()}";
            $this->error("✗ {$nome}: {$e->getMessage()}");
        }
    }

    private function garantir(bool $condicao, string $mensagem): void
    {
        if (! $condicao) {
            throw new RuntimeException($mensagem);
        }
    }
}
