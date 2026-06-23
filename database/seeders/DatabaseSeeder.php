<?php

namespace Database\Seeders;

use App\Models\Agendamento;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Super Admin ────────────────────────────────────────────────────
        $joao = User::firstOrCreate(
            ['email' => 'joao@agendabot.com'],
            [
                'name'           => 'João Pedro',
                'password'       => Hash::make('password'),
                'is_super_admin' => true,
            ]
        );

        // Manter e-mail legacy para compatibilidade com testes anteriores
        $adminLegacy = User::firstOrCreate(
            ['email' => 'admin@agendabot.com'],
            [
                'name'           => 'Admin',
                'password'       => Hash::make('password'),
                'is_super_admin' => true,
            ]
        );

        // ── 2. Tenant: Clínica Dental (novo com v2 structure) ──────────────────
        $clinica = Tenant::firstOrCreate(
            ['slug' => 'clinica-dental-demo'],
            [
                'nome'               => 'Clínica Dental Demo',
                'tipo_servico'       => 'personalizado',
                'ramo_negocio'       => 'Clínica odontológica',
                'descricao_negocio'  => 'Clínica especializada em odontologia estética e preventiva. Atendemos com carinho e modernidade.',
                'cidade'             => 'Porto Alegre',
                'endereco'           => 'Rua das Flores, 123',
                'nome_agente'        => 'Bia',
                'tom_voz'            => 'semiformal',
                'instrucoes_extras'  => 'Sempre perguntar se é retorno ou primeira consulta. Aceitar Unimed e particular.',
                'evolution_instance' => 'clinica-dental-demo',
                'subscription_status' => 'trial',
                'trial_ends_at'      => now()->addDays(14),
                'ativo'              => true,
                'bot_ativo'          => true,
                'horarios_funcionamento' => ['seg_sex' => '08:00-18:00', 'sab' => '08:00-12:00'],
            ]
        );

        $clinica->users()->syncWithoutDetaching([$adminLegacy->id => ['papel' => 'admin']]);

        // Profissionais da clínica
        $drJoao = $clinica->profissionais()->firstOrCreate(
            ['nome' => 'Dr. João Silva'],
            [
                'especialidades' => ['Clínica Geral', 'Estética Dental'],
                'ativo' => true,
            ]
        );
        $draMaria = $clinica->profissionais()->firstOrCreate(
            ['nome' => 'Dra. Maria Souza'],
            [
                'especialidades' => ['Ortodontia', 'Implantes'],
                'ativo' => true,
            ]
        );

        // Horários de cada profissional (Seg-Sex, 08:00-18:00, slots de 30min)
        foreach ([$drJoao, $draMaria] as $prof) {
            for ($dia = 1; $dia <= 5; $dia++) {
                $prof->horarios()->firstOrCreate(
                    ['dia_semana' => $dia],
                    [
                        'hora_inicio' => '08:00',
                        'hora_fim'    => '18:00',
                        'duracao_slot' => 30,
                    ]
                );
            }
        }

        // Serviços da clínica
        $clinica->servicos()->firstOrCreate(
            ['nome' => 'Consulta'],
            ['descricao' => 'Consulta geral', 'valor_min' => 150, 'duracao_minutos' => 30, 'ativo' => true]
        );
        $clinica->servicos()->firstOrCreate(
            ['nome' => 'Limpeza'],
            ['valor_min' => 120, 'duracao_minutos' => 60, 'ativo' => true]
        );
        $clinica->servicos()->firstOrCreate(
            ['nome' => 'Clareamento'],
            ['valor_min' => 500, 'valor_max' => 800, 'duracao_minutos' => 90, 'ativo' => true]
        );

        // Opções extras da clínica
        $clinica->opcoes_extras()->firstOrCreate(
            ['nome' => 'Unimed'],
            ['tipo' => 'convenio', 'ativo' => true]
        );
        $clinica->opcoes_extras()->firstOrCreate(
            ['nome' => 'Particular'],
            ['tipo' => 'pagamento', 'ativo' => true]
        );
        $clinica->opcoes_extras()->firstOrCreate(
            ['nome' => 'PIX'],
            ['tipo' => 'pagamento', 'ativo' => true]
        );

        // ── 3. Atualizar Barbearia com campos v2 ────────────────────────────────
        $carlosDono = User::firstOrCreate(
            ['email' => 'carlos@barbearia.com'],
            [
                'name'     => 'Carlos Barbeiro',
                'password' => Hash::make('password'),
            ]
        );

        $barbearia = Tenant::firstOrCreate(
            ['slug' => 'barbearia-do-joao'],
            [
                'nome'                => 'Barbearia do João',
                'tipo_servico'        => 'barbeiro',
                'evolution_instance'  => 'barbearia-joao',
                'ativo'               => true,
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addMonth(),
                'plano'               => 'profissional',
            ]
        );

        // Atualizar barbearia com campos v2
        $barbearia->update([
            'ramo_negocio'       => 'Barbearia',
            'descricao_negocio'  => 'Barbearia tradicional com profissionais experientes. Cortes, barba e produtos de qualidade.',
            'cidade'             => 'São Paulo',
            'endereco'           => 'Av. Paulista, 1000',
            'nome_agente'        => 'João',
            'tom_voz'            => 'descontraido',
            'instrucoes_extras'  => 'Sempre oferecer produtos de cuidados. Horários preferenciais: terças de manhã.',
            'bot_ativo'          => true,
            'horarios_funcionamento' => ['seg_sex' => '09:00-19:00', 'sab' => '09:00-15:00'],
        ]);

        $barbearia->users()->syncWithoutDetaching([$carlosDono->id => ['papel' => 'admin']]);

        // Profissionais da barbearia
        $carlos = $barbearia->profissionais()->firstOrCreate(
            ['nome' => 'Carlos'],
            ['especialidades' => ['Corte Clássico', 'Barba'], 'ativo' => true]
        );
        $pedro = $barbearia->profissionais()->firstOrCreate(
            ['nome' => 'Pedro'],
            ['especialidades' => ['Corte Moderno', 'Design Barba'], 'ativo' => true]
        );
        $lucas = $barbearia->profissionais()->firstOrCreate(
            ['nome' => 'Lucas'],
            ['especialidades' => ['Coloração', 'Tratamento'], 'ativo' => true]
        );

        // Horários da barbearia (Seg-Sab, 09:00-19:00, slots de 30min)
        foreach ([$carlos, $pedro, $lucas] as $prof) {
            for ($dia = 1; $dia <= 6; $dia++) {
                $prof->horarios()->firstOrCreate(
                    ['dia_semana' => $dia],
                    [
                        'hora_inicio' => '09:00',
                        'hora_fim'    => '19:00',
                        'duracao_slot' => 30,
                    ]
                );
            }
        }

        // Serviços da barbearia
        $barbearia->servicos()->firstOrCreate(
            ['nome' => 'Corte'],
            ['descricao' => 'Corte de cabelo', 'valor_min' => 50, 'duracao_minutos' => 30, 'ativo' => true]
        );
        $barbearia->servicos()->firstOrCreate(
            ['nome' => 'Barba'],
            ['descricao' => 'Aparação e design de barba', 'valor_min' => 30, 'duracao_minutos' => 20, 'ativo' => true]
        );
        $barbearia->servicos()->firstOrCreate(
            ['nome' => 'Corte + Barba'],
            ['valor_min' => 70, 'duracao_minutos' => 45, 'ativo' => true]
        );

        // Opções extras da barbearia
        $barbearia->opcoes_extras()->firstOrCreate(
            ['nome' => 'Loção Pós-Barba'],
            ['tipo' => 'outro', 'ativo' => true]
        );
        $barbearia->opcoes_extras()->firstOrCreate(
            ['nome' => 'Pomada Premium'],
            ['tipo' => 'outro', 'ativo' => true]
        );

        // Agendamentos de exemplo para a barbearia (origens mistas)
        if ($barbearia->agendamentos()->count() === 0) {
            $this->seedAgendamentosV1($barbearia, $carlos->id ?? 1, 60, 30);
        }

        // ── 4. Atualizar Quadras com campos v2 ───────────────────────────────────
        $arenaDono = User::firstOrCreate(
            ['email' => 'roberto@arenasports.com'],
            [
                'name'     => 'Roberto Arena',
                'password' => Hash::make('password'),
            ]
        );

        $quadras = Tenant::firstOrCreate(
            ['slug' => 'arena-sports'],
            [
                'nome'                => 'Arena Sports',
                'tipo_servico'        => 'quadra',
                'evolution_instance'  => 'arena-sports',
                'ativo'               => true,
                'subscription_status' => 'trial',
                'trial_ends_at'       => now()->addDays(10),
                'plano'               => 'basico',
            ]
        );

        // Atualizar quadras com campos v2
        $quadras->update([
            'ramo_negocio'       => 'Aluguel de Quadras Esportivas',
            'descricao_negocio'  => 'Complexo esportivo com múltiplas quadras. Futsal, Beach Tennis e Padel com equipamento completo.',
            'cidade'             => 'Rio de Janeiro',
            'endereco'           => 'Av. Beira-Mar, 500',
            'nome_agente'        => 'Roberto',
            'tom_voz'            => 'entusiasmado',
            'instrucoes_extras'  => 'Informar sobre os pacotes de aulas. Horários de pico: noites e finais de semana.',
            'bot_ativo'          => true,
            'horarios_funcionamento' => ['seg_dom' => '07:00-22:00'],
        ]);

        $quadras->users()->syncWithoutDetaching([$arenaDono->id => ['papel' => 'admin']]);

        // Profissionais/Instrutores das quadras
        $prof1 = $quadras->profissionais()->firstOrCreate(
            ['nome' => 'Instrutor Futsal - Marcos'],
            ['especialidades' => ['Futsal', 'Técnica'], 'ativo' => true]
        );
        $prof2 = $quadras->profissionais()->firstOrCreate(
            ['nome' => 'Instrutor Beach Tennis - Ana'],
            ['especialidades' => ['Beach Tennis', 'Treino'], 'ativo' => true]
        );

        // Horários dos instrutores (Todos os dias, 07:00-22:00, slots de 60min)
        foreach ([$prof1, $prof2] as $prof) {
            for ($dia = 0; $dia <= 6; $dia++) {
                $prof->horarios()->firstOrCreate(
                    ['dia_semana' => $dia],
                    [
                        'hora_inicio' => '07:00',
                        'hora_fim'    => '22:00',
                        'duracao_slot' => 60,
                    ]
                );
            }
        }

        // Serviços das quadras
        $quadras->servicos()->firstOrCreate(
            ['nome' => 'Aluguel Quadra de Futsal'],
            ['valor_min' => 120, 'duracao_minutos' => 60, 'ativo' => true]
        );
        $quadras->servicos()->firstOrCreate(
            ['nome' => 'Aluguel Beach Tennis'],
            ['valor_min' => 150, 'duracao_minutos' => 60, 'ativo' => true]
        );
        $quadras->servicos()->firstOrCreate(
            ['nome' => 'Aluguel Padel'],
            ['valor_min' => 140, 'duracao_minutos' => 60, 'ativo' => true]
        );

        // Opções extras das quadras
        $quadras->opcoes_extras()->firstOrCreate(
            ['nome' => 'Aula com Instrutor'],
            ['tipo' => 'outro', 'ativo' => true]
        );
        $quadras->opcoes_extras()->firstOrCreate(
            ['nome' => 'Material Incluso'],
            ['tipo' => 'outro', 'ativo' => true]
        );
        $quadras->opcoes_extras()->firstOrCreate(
            ['nome' => 'Desconto Mensal'],
            ['tipo' => 'outro', 'ativo' => true]
        );

        // Agendamentos de exemplo para as quadras (origens mistas)
        if ($quadras->agendamentos()->count() === 0) {
            $this->seedAgendamentosV1($quadras, $prof1->id ?? 1, 120, 60);
        }
    }

    private function seedAgendamentosV1(Tenant $tenant, $profissionalId, float $valorHora, int $duracaoMin): void
    {
        $clientes = [
            ['Rafael Silva', '5511991110001'],
            ['Ana Costa', '5511991110002'],
            ['Bruno Lima', '5511991110003'],
            ['Carla Souza', '5511991110004'],
            ['Diego Pereira', '5511991110005'],
        ];

        $origens = ['whatsapp', 'whatsapp', 'manual', 'whatsapp', 'manual'];
        $hoje = Carbon::today();

        // Agendamentos passados (concluídos)
        foreach ($clientes as $i => [$nome, $tel]) {
            $inicio = $hoje->copy()->subDays(rand(1, 7))->setHour(9 + $i)->setMinute(0)->setSecond(0);
            $fim = $inicio->copy()->addMinutes($duracaoMin);

            Agendamento::firstOrCreate(
                [
                    'tenant_id'        => $tenant->id,
                    'cliente_telefone' => $tel,
                    'inicio'           => $inicio,
                ],
                [
                    'cliente_nome'     => $nome,
                    'fim'              => $fim,
                    'status'           => 'concluido',
                    'origem'           => $origens[$i],
                    'valor_total'      => $valorHora * ($duracaoMin / 60),
                ]
            );
        }

        // Agendamentos de hoje e futuros (confirmados)
        foreach ($clientes as $i => [$nome, $tel]) {
            $inicio = $hoje->copy()->addDays($i % 3)->setHour(10 + $i)->setMinute(0)->setSecond(0);
            $fim = $inicio->copy()->addMinutes($duracaoMin);

            Agendamento::firstOrCreate(
                [
                    'tenant_id'        => $tenant->id,
                    'cliente_telefone' => $tel,
                    'inicio'           => $inicio,
                ],
                [
                    'cliente_nome'     => $nome . ' (futuro)',
                    'fim'              => $fim,
                    'status'           => 'confirmado',
                    'origem'           => $origens[$i],
                    'valor_total'      => $valorHora * ($duracaoMin / 60),
                ]
            );
        }

        // Um cancelado
        $inicio = $hoje->copy()->addDays(1)->setHour(15)->setMinute(0)->setSecond(0);
        Agendamento::firstOrCreate(
            [
                'tenant_id'        => $tenant->id,
                'cliente_telefone' => '5511991110099',
                'inicio'           => $inicio,
            ],
            [
                'cliente_nome'     => 'Fernanda Cancelou',
                'fim'              => $inicio->copy()->addMinutes($duracaoMin),
                'status'           => 'cancelado',
                'origem'           => 'whatsapp',
                'valor_total'      => null,
            ]
        );
    }
}
