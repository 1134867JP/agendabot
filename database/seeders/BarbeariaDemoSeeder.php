<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Profissional;
use App\Models\Servico;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CatalogoServicos;
use App\Services\OnboardingPresetService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Barbearia de demonstração para apresentar o produto a donos de barbearia:
 * três barbeiros com preços e almoço próprios, serviços e combo, agenda de
 * hoje e dos próximos dias preenchida e uma conversa de exemplo com o bot.
 *
 * Todos os nomes, telefones e endereço são fictícios.
 *
 * Rodar (pode repetir; recria só os dados de demonstração):
 *   DEMO_BARBEARIA_EMAIL=... DEMO_BARBEARIA_PASSWORD=... \
 *   php artisan db:seed --class=Database\\Seeders\\BarbeariaDemoSeeder
 */
class BarbeariaDemoSeeder extends Seeder
{
    private const SLUG = 'barbearia-do-joao-demo';

    public function run(OnboardingPresetService $preset, CatalogoServicos $catalogo): void
    {
        $email = env('DEMO_BARBEARIA_EMAIL');
        $password = env('DEMO_BARBEARIA_PASSWORD');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 12) {
            throw new RuntimeException('Configure DEMO_BARBEARIA_EMAIL e DEMO_BARBEARIA_PASSWORD (12+ caracteres) antes de executar este seeder.');
        }

        DB::transaction(function () use ($email, $password, $preset, $catalogo): void {
            $dono = User::firstOrNew(['email' => $email]);
            $dono->fill([
                'name' => 'João (demonstração)',
                'password' => Hash::make($password),
                'terms_accepted_at' => now(),
                'privacy_accepted_at' => now(),
                'legal_version' => config('legal.version'),
            ]);
            $dono->email_verified_at ??= now();
            $dono->save();

            $tenant = Tenant::firstOrNew(['slug' => self::SLUG]);
            $tenant->fill([
                'nome' => 'Barbearia do João',
                'tipo_servico' => 'barbeiro',
                'timezone' => 'America/Sao_Paulo',
                'evolution_instance' => self::SLUG,
                'ativo' => true,
                'plano' => 'pro',
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addYear(),
                'isento_cobranca' => true,
            ]);
            $tenant->webhook_token ??= Str::random(48);
            $tenant->save();
            $tenant->users()->syncWithoutDetaching([$dono->id => ['papel' => 'admin']]);

            $this->limparDemonstracao($tenant);

            $preset->aplicar($tenant, [
                'nome_item' => 'João',
                'nome_servico' => 'Corte',
                'duracao_minutos' => 30,
                'valor' => 40,
                'dias_atendimento' => 'segunda_sabado',
                'hora_abertura' => '09:00',
                'hora_fechamento' => '19:00',
                'perfil_regras' => 'equilibrado',
            ]);

            $tenant->update([
                'nome_agente' => 'Zé, assistente da Barbearia do João',
                'tom_voz' => 'descontraido',
                'bot_saudacao' => 'Fala! Aqui é o Zé, da Barbearia do João 💈 Quer marcar um horário?',
                'descricao_negocio' => 'Barbearia de bairro com corte clássico e moderno, barba na navalha e toalha quente.',
                'cidade' => 'Passo Fundo - RS',
                'endereco' => 'Rua Exemplo, 123 - Centro (endereço fictício)',
                'horarios_funcionamento' => 'Seg–Sex 09:00–19:00; Sáb 09:00–17:00',
                'instrucoes_extras' => 'Aceitamos PIX, cartão e dinheiro. Atrasos acima de 15 minutos podem precisar ser remarcados.',
                'configuracoes' => array_merge($tenant->fresh()->configuracoes ?? [], [
                    'lembrete_horas_antes' => 2,
                ]),
            ]);

            $servicos = $this->servicos($tenant);
            $barbeiros = $this->barbeiros($tenant, $servicos);

            foreach (['PIX', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro'] as $forma) {
                $tenant->opcoes_extras()->updateOrCreate(['tipo' => 'pagamento', 'nome' => $forma], ['ativo' => true]);
            }

            $this->agenda($tenant, $barbeiros, $servicos, $catalogo);
            $this->conversaExemplo($tenant);
        });

        $this->command?->info('Barbearia de demonstração pronta: '.self::SLUG." (login: {$email})");
    }

    /** Remove apenas o que este seeder cria, para poder rodar de novo. */
    private function limparDemonstracao(Tenant $tenant): void
    {
        Agendamento::where('tenant_id', $tenant->id)->delete();
        Conversa::where('tenant_id', $tenant->id)->delete();
        Cliente::where('tenant_id', $tenant->id)->delete();
        $tenant->profissionais()->delete();
        $tenant->servicos()->delete();
    }

    /** @return array<string, Servico> */
    private function servicos(Tenant $tenant): array
    {
        $catalogo = [
            'corte' => ['Corte', 40, 30, 'Corte masculino na tesoura ou máquina, com acabamento.'],
            'barba' => ['Barba', 30, 30, 'Barba na navalha com toalha quente.'],
            'combo' => ['Corte + Barba', 65, 60, 'Combo com desconto.'],
            'sobrancelha' => ['Sobrancelha', 15, 15, 'Design na navalha.'],
            'pigmentacao' => ['Pigmentação de barba', 35, 30, 'Preenche falhas da barba.'],
            'infantil' => ['Corte infantil', 35, 30, 'Até 12 anos.'],
        ];

        $servicos = [];
        foreach ($catalogo as $chave => [$nome, $valor, $duracao, $descricao]) {
            $servicos[$chave] = $tenant->servicos()->updateOrCreate(['nome' => $nome], [
                'descricao' => $descricao,
                'valor_min' => $valor,
                'valor_max' => $valor,
                'duracao_minutos' => $duracao,
                'ativo' => true,
                'requer_profissional' => true,
                'requer_recurso' => false,
            ]);
        }

        return $servicos;
    }

    /** @return array<string, Profissional> */
    private function barbeiros(Tenant $tenant, array $servicos): array
    {
        $ids = fn (array $chaves) => array_map(fn ($c) => $servicos[$c]->id, $chaves);
        $todos = array_keys($servicos);

        $equipe = [
            // João é o dono e barbeiro sênior: corte e combo mais caros.
            'joao' => ['João', ['Corte clássico', 'Navalha'], ['12:00', '13:00'], [
                $servicos['corte']->id => ['valor' => 50, 'duracao_minutos' => 40],
                $servicos['combo']->id => ['valor' => 75, 'duracao_minutos' => 70],
            ], $ids(['barba', 'sobrancelha', 'pigmentacao'])],
            'pedro' => ['Pedro', ['Degradê', 'Barba'], ['13:00', '14:00'], [], $ids($todos)],
            'lucas' => ['Lucas', ['Corte infantil', 'Degradê'], ['12:30', '13:30'], [], $ids(['corte', 'barba', 'combo', 'sobrancelha', 'infantil'])],
        ];

        $barbeiros = [];
        foreach ($equipe as $chave => [$nome, $especialidades, [$almocoInicio, $almocoFim], $personalizados, $padrao]) {
            $barbeiro = $tenant->profissionais()->updateOrCreate(['nome' => $nome], ['ativo' => true, 'especialidades' => $especialidades]);

            $vinculos = array_fill_keys($padrao, ['valor' => null, 'duracao_minutos' => null]);
            $barbeiro->servicos()->sync(array_replace($vinculos, $personalizados));

            $barbeiro->horarios()->delete();
            for ($dia = 1; $dia <= 6; $dia++) {
                $barbeiro->horarios()->create([
                    'dia_semana' => $dia,
                    'hora_inicio' => '09:00',
                    'hora_fim' => $dia === 6 ? '17:00' : '19:00',
                    'intervalo_inicio' => $almocoInicio,
                    'intervalo_fim' => $almocoFim,
                    'duracao_slot' => 30,
                ]);
            }
            $barbeiros[$chave] = $barbeiro;
        }

        return $barbeiros;
    }

    private function agenda(Tenant $tenant, array $barbeiros, array $servicos, CatalogoServicos $catalogo): void
    {
        $tz = $tenant->resolvedTimezone();
        $agora = Carbon::now($tz);

        // Hoje e os próximos dias de atendimento (a barbearia fecha no domingo).
        $diasUteis = [];
        for ($dia = $agora->copy()->startOfDay(); count($diasUteis) < 3; $dia->addDay()) {
            if ($dia->dayOfWeek !== Carbon::SUNDAY) {
                $diasUteis[] = $dia->copy();
            }
        }

        // [dia relativo, barbeiro, hora, serviços, cliente, telefone, origem]
        $roteiro = [
            [0, 'joao', '09:00', ['corte'], 'Rafael Souza', '5554991110001', 'manual'],
            [0, 'joao', '10:00', ['combo'], 'Diego Lima', '5554991110002', 'bot'],
            [0, 'joao', '14:00', ['corte', 'sobrancelha'], 'Carlos Mendes', '5554991110003', 'bot'],
            [0, 'joao', '16:30', ['barba'], 'André Costa', '5554991110004', 'bot'],
            [0, 'pedro', '09:30', ['barba'], 'Bruno Alves', '5554991110005', 'manual'],
            [0, 'pedro', '11:00', ['corte', 'barba'], 'Thiago Rocha', '5554991110006', 'bot'],
            [0, 'pedro', '15:00', ['pigmentacao'], 'Marcos Dias', '5554991110007', 'bot'],
            [0, 'lucas', '10:30', ['infantil'], 'Enzo Martins', '5554991110008', 'manual'],
            [0, 'lucas', '16:00', ['corte'], 'Felipe Ramos', '5554991110009', 'bot'],
            [1, 'joao', '09:30', ['corte'], 'Gustavo Pires', '5554991110010', 'bot'],
            [1, 'joao', '15:00', ['combo'], 'Henrique Nunes', '5554991110011', 'bot'],
            [1, 'pedro', '10:00', ['corte', 'barba'], 'Igor Teixeira', '5554991110012', 'bot'],
            [1, 'lucas', '14:30', ['corte'], 'Rafael Souza', '5554991110001', 'manual'],
            [2, 'pedro', '16:00', ['barba', 'sobrancelha'], 'Diego Lima', '5554991110002', 'bot'],
        ];

        foreach ($roteiro as [$dias, $chave, $hora, $chaves, $nome, $telefone, $origem]) {
            $data = $diasUteis[$dias];

            $barbeiro = $barbeiros[$chave];
            $montagem = $catalogo->montar($tenant, array_map(fn ($c) => $servicos[$c]->id, $chaves), $barbeiro);
            $inicio = Carbon::createFromFormat('Y-m-d H:i', $data->format('Y-m-d')." {$hora}", $tz);
            $passado = $inicio->lt($agora);

            $cliente = Cliente::firstOrCreate(['tenant_id' => $tenant->id, 'telefone' => $telefone], ['nome' => $nome]);
            $agendamento = Agendamento::create([
                'tenant_id' => $tenant->id,
                'cliente_id' => $cliente->id,
                'cliente_nome' => $nome,
                'cliente_telefone' => $telefone,
                'profissional_id' => $barbeiro->id,
                'servico_id' => $montagem['itens'][0]['servico_id'],
                'data_hora' => $inicio,
                'inicio' => $inicio,
                'fim' => $inicio->copy()->addMinutes($montagem['duracao_minutos']),
                'duracao_minutos' => $montagem['duracao_minutos'],
                'valor_total' => $montagem['valor_total'],
                'status' => $passado ? 'concluido' : 'confirmado',
                'origem' => $origem,
                'lembrete_enviado' => $passado,
            ]);
            $catalogo->gravarItens($agendamento, $montagem);

            if ($origem === 'bot') {
                $this->conversaCurta($tenant, $cliente, [
                    ['cliente', "Oi, tem horário {$this->quando($inicio, $agora)}?"],
                    ['bot', "Tenho sim! {$montagem['nomes']} com {$barbeiro->nome} às {$hora}. Posso marcar?"],
                    ['cliente', 'Pode sim'],
                    ['bot', 'Fechado ✅ Te mando um lembrete antes do horário.'],
                ], $inicio->copy()->subHours(5));
            }
        }

        // Quem só perguntou e não marcou: deixa a taxa de conversão realista.
        foreach ([['Paulo Henrique', '5554991110013', 'Quanto tá o corte?'], ['Vinícius Prado', '5554991110014', 'Vocês abrem domingo?']] as [$nome, $telefone, $pergunta]) {
            $cliente = Cliente::firstOrCreate(['tenant_id' => $tenant->id, 'telefone' => $telefone], ['nome' => $nome]);
            $this->conversaCurta($tenant, $cliente, [
                ['cliente', $pergunta],
                ['bot', 'Oi! O corte sai R$ 40 (com o João, R$ 50). Atendemos de segunda a sábado. Quer marcar um horário?'],
            ], $agora->copy()->subHours(3));
        }
    }

    private function quando(Carbon $inicio, Carbon $agora): string
    {
        return $inicio->isSameDay($agora) ? 'hoje' : $inicio->locale('pt_BR')->translatedFormat('l');
    }

    private function conversaCurta(Tenant $tenant, Cliente $cliente, array $mensagens, Carbon $momento): void
    {
        $momento = $momento->copy()->min(now()->subMinutes(10));
        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone_cliente' => $cliente->telefone],
            ['cliente_id' => $cliente->id, 'status_v2' => 'encerrada', 'ultima_mensagem_em' => $momento],
        );
        foreach ($mensagens as [$remetente, $conteudo]) {
            $momento->addMinute();
            $conversa->mensagens()->create([
                'remetente' => $remetente,
                'tipo' => 'texto',
                'conteudo' => $conteudo,
                'enviada_em' => $momento->copy(),
            ]);
        }
        $conversa->update(['ultima_mensagem_em' => $momento->copy()]);
    }

    private function conversaExemplo(Tenant $tenant): void
    {
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone' => '5554991110003'],
            ['nome' => 'Carlos Mendes'],
        );
        $conversa = Conversa::updateOrCreate(
            ['tenant_id' => $tenant->id, 'telefone_cliente' => $cliente->telefone],
            ['cliente_id' => $cliente->id, 'status_v2' => 'ativa', 'ultima_mensagem_em' => now()],
        );
        $conversa->mensagens()->delete();

        $roteiro = [
            ['cliente', 'texto', 'Fala! Tem horário hoje à tarde pra corte e sobrancelha?'],
            ['bot', 'texto', 'Opa, Carlos! Com o João hoje tenho 14:00 ou 15:00. Corte + Sobrancelha sai R$ 65,00. Qual fica melhor?'],
            ['cliente', 'audio', '[áudio transcrito] Às duas tá ótimo, pode marcar'],
            ['bot', 'texto', 'Fechado ✅ Corte + Sobrancelha com o João, hoje às 14:00. Te lembro 2h antes 😉'],
        ];

        $momento = now()->subMinutes(count($roteiro) * 2);
        foreach ($roteiro as [$remetente, $tipo, $conteudo]) {
            $conversa->mensagens()->create([
                'remetente' => $remetente,
                'tipo' => $tipo,
                'conteudo' => $conteudo,
                'enviada_em' => $momento->addMinutes(2)->copy(),
            ]);
        }
    }
}
