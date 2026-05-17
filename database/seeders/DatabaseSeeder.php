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
        $joao = User::create([
            'name'           => 'João Pedro',
            'email'          => 'joao@agendabot.com',
            'password'       => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        // Manter e-mail legacy para compatibilidade com testes anteriores
        User::create([
            'name'           => 'Admin',
            'email'          => 'admin@agendabot.com',
            'password'       => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        // ── 2. Dono da Barbearia ──────────────────────────────────────────────
        $carlosDono = User::create([
            'name'     => 'Carlos Barbeiro',
            'email'    => 'carlos@barbearia.com',
            'password' => Hash::make('password'),
        ]);

        $barbearia = Tenant::create([
            'nome'                => 'Barbearia do João',
            'slug'                => 'barbearia-do-joao',
            'tipo_servico'        => 'barbeiro',
            'evolution_instance'  => 'barbearia-joao',
            'ativo'               => true,
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
            'plano'               => 'profissional',
        ]);
        $barbearia->users()->attach($carlosDono->id, ['papel' => 'admin']);

        $barbeiros = [];
        foreach (['Carlos', 'Pedro', 'Lucas'] as $nome) {
            $recurso = $barbearia->recursos()->create([
                'nome'                   => "Barbeiro {$nome}",
                'valor_hora'             => 60,
                'duracao_padrao_minutos' => 30,
                'ativo'                  => true,
            ]);
            for ($dia = 1; $dia <= 6; $dia++) {
                $recurso->horariosFuncionamento()->create([
                    'dia_semana' => $dia,
                    'abertura'   => '09:00',
                    'fechamento' => '19:00',
                ]);
            }
            $barbeiros[] = $recurso;
        }

        // Agendamentos de exemplo para a barbearia (origens mistas)
        $this->seedAgendamentos($barbearia, $barbeiros[0], 60, 30);

        // ── 3. Dono das Quadras ───────────────────────────────────────────────
        $arenaDono = User::create([
            'name'     => 'Roberto Arena',
            'email'    => 'roberto@arenasports.com',
            'password' => Hash::make('password'),
        ]);

        $quadras = Tenant::create([
            'nome'                => 'Arena Sports',
            'slug'                => 'arena-sports',
            'tipo_servico'        => 'quadra',
            'evolution_instance'  => 'arena-sports',
            'ativo'               => true,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(10),
            'plano'               => 'basico',
        ]);
        $quadras->users()->attach($arenaDono->id, ['papel' => 'admin']);

        $quadrasRecursos = [];
        foreach (['Quadra de Futsal', 'Beach Tennis', 'Padel'] as $nome) {
            $recurso = $quadras->recursos()->create([
                'nome'                   => $nome,
                'valor_hora'             => 120,
                'duracao_padrao_minutos' => 60,
                'ativo'                  => true,
            ]);
            for ($dia = 0; $dia <= 6; $dia++) {
                $recurso->horariosFuncionamento()->create([
                    'dia_semana' => $dia,
                    'abertura'   => '07:00',
                    'fechamento' => '22:00',
                ]);
            }
            $quadrasRecursos[] = $recurso;
        }

        $this->seedAgendamentos($quadras, $quadrasRecursos[0], 120, 60);
    }

    private function seedAgendamentos(Tenant $tenant, $recurso, float $valorHora, int $duracaoMin): void
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

            Agendamento::create([
                'tenant_id'        => $tenant->id,
                'recurso_id'       => $recurso->id,
                'cliente_nome'     => $nome,
                'cliente_telefone' => $tel,
                'inicio'           => $inicio,
                'fim'              => $fim,
                'status'           => 'concluido',
                'origem'           => $origens[$i],
                'valor_total'      => $valorHora * ($duracaoMin / 60),
            ]);
        }

        // Agendamentos de hoje e futuros (confirmados)
        foreach ($clientes as $i => [$nome, $tel]) {
            $inicio = $hoje->copy()->addDays($i % 3)->setHour(10 + $i)->setMinute(0)->setSecond(0);
            $fim = $inicio->copy()->addMinutes($duracaoMin);

            Agendamento::create([
                'tenant_id'        => $tenant->id,
                'recurso_id'       => $recurso->id,
                'cliente_nome'     => $nome . ' (futuro)',
                'cliente_telefone' => $tel,
                'inicio'           => $inicio,
                'fim'              => $fim,
                'status'           => 'confirmado',
                'origem'           => $origens[$i],
                'valor_total'      => $valorHora * ($duracaoMin / 60),
            ]);
        }

        // Um cancelado
        $inicio = $hoje->copy()->addDays(1)->setHour(15)->setMinute(0)->setSecond(0);
        Agendamento::create([
            'tenant_id'        => $tenant->id,
            'recurso_id'       => $recurso->id,
            'cliente_nome'     => 'Fernanda Cancelou',
            'cliente_telefone' => '5511991110099',
            'inicio'           => $inicio,
            'fim'              => $inicio->copy()->addMinutes($duracaoMin),
            'status'           => 'cancelado',
            'origem'           => 'whatsapp',
            'valor_total'      => null,
        ]);
    }
}
