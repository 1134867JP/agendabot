<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Configuração da clínica "Odonto Excellence".
 *
 * Reúne os dados do estabelecimento, profissionais, serviços, formas de
 * pagamento e — principalmente — as mensagens/scripts prontos que a clínica
 * já usa no WhatsApp. Esses scripts são injetados no system prompt do bot
 * via `instrucoes_extras`, fazendo o agente responder no mesmo tom e com a
 * mesma estratégia de atendimento da equipe.
 *
 * Rodar isoladamente:
 *   php artisan db:seed --class=Database\\Seeders\\OdontoExcellenceSeeder
 */
class OdontoExcellenceSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Dono / admin do tenant ─────────────────────────────────────────
        $dono = User::firstOrCreate(
            ['email' => 'contato@odontoexcellence.com'],
            [
                'name' => 'Odonto Excellence',
                'password' => Hash::make('password'),
            ]
        );

        // ── 2. Tenant ─────────────────────────────────────────────────────────
        $clinica = Tenant::firstOrCreate(
            ['slug' => 'odonto-excellence'],
            [
                'nome' => 'Odonto Excellence',
                'tipo_servico' => 'personalizado',
                'tipo_servico_personalizado' => 'Clínica odontológica',
                'evolution_instance' => 'odonto-excellence',
                'subscription_status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'ativo' => true,
                'bot_ativo' => true,
            ]
        );

        // Aplica/atualiza toda a configuração v2 (idempotente em re-seed).
        $clinica->update([
            'ramo_negocio' => 'Clínica odontológica',
            'descricao_negocio' => 'Clínica de odontologia avançada. Nossos dentistas são especialistas e '
                .'nosso compromisso é oferecer o melhor tratamento possível no menor tempo possível. '
                .'O paciente é sempre a prioridade.',
            'cidade' => 'Encantado - RS',
            'endereco' => 'R. Júlio de Castilhos, 1505 - Centro',
            'telefone_whatsapp' => '555137513826',
            'nome_agente' => 'Milena',
            'tom_voz' => 'semiformal',
            // Seg-Sex turno partido (almoço 12:00-13:30), Sáb só manhã.
            'horarios_funcionamento' => [
                'seg_sex' => '09:00-12:00 e 13:30-20:00',
                'sab' => '09:00-12:00',
                'dom' => 'Fechado',
            ],
            'instrucoes_extras' => $this->scriptsAtendimento(),
            'bot_ativo' => true,
        ]);

        $clinica->users()->syncWithoutDetaching([$dono->id => ['papel' => 'admin']]);

        // ── 3. Profissionais ──────────────────────────────────────────────────
        // Nomes genéricos de especialistas — ajuste com a equipe real da clínica.
        $especialistas = [
            ['nome' => 'Dr. Especialista 1', 'especialidades' => ['Clínica Geral', 'Implantes']],
            ['nome' => 'Dra. Especialista 2', 'especialidades' => ['Ortodontia', 'Estética Dental']],
        ];

        $profissionais = [];
        foreach ($especialistas as $e) {
            $prof = $clinica->profissionais()->firstOrCreate(
                ['nome' => $e['nome']],
                ['especialidades' => $e['especialidades'], 'ativo' => true]
            );

            // Horário de funcionamento (turno partido seg-sex, sáb de manhã).
            // duracao_slot menor na avaliação cortesia; usamos 30min como padrão.
            $faixas = [
                // Seg a Sex: manhã + tarde
                [1, '09:00', '12:00'], [1, '13:30', '20:00'],
                [2, '09:00', '12:00'], [2, '13:30', '20:00'],
                [3, '09:00', '12:00'], [3, '13:30', '20:00'],
                [4, '09:00', '12:00'], [4, '13:30', '20:00'],
                [5, '09:00', '12:00'], [5, '13:30', '20:00'],
                // Sábado: só manhã
                [6, '09:00', '12:00'],
            ];
            foreach ($faixas as [$dia, $ini, $fim]) {
                $prof->horarios()->firstOrCreate(
                    ['dia_semana' => $dia, 'hora_inicio' => $ini],
                    ['hora_fim' => $fim, 'duracao_slot' => 30]
                );
            }

            $profissionais[] = $prof;
        }

        // ── 4. Serviços ───────────────────────────────────────────────────────
        // A avaliação é GRATUITA (cortesia) e é a porta de entrada do paciente.
        $avaliacao = $clinica->servicos()->firstOrCreate(
            ['nome' => 'Avaliação (Cortesia)'],
            [
                'descricao' => 'Avaliação completa e gratuita com nossos especialistas. '
                    .'Porta de entrada para o plano de tratamento.',
                'valor_min' => 0,
                'duracao_minutos' => 30,
                'requer_avaliacao' => false,
                'ativo' => true,
            ]
        );

        // Tratamentos comuns (faixas de valor ilustrativas; ajustar conforme tabela real).
        $tratamentos = [
            ['nome' => 'Limpeza / Profilaxia', 'min' => 120, 'max' => null, 'dur' => 40],
            ['nome' => 'Clareamento Dental', 'min' => 500, 'max' => 900, 'dur' => 60],
            ['nome' => 'Tratamento Ortodôntico', 'min' => null, 'max' => null, 'dur' => 40, 'avaliacao' => true],
            ['nome' => 'Implante Dentário', 'min' => null, 'max' => null, 'dur' => 60, 'avaliacao' => true],
            ['nome' => 'Restauração', 'min' => 200, 'max' => 400, 'dur' => 40],
        ];
        foreach ($tratamentos as $t) {
            $clinica->servicos()->firstOrCreate(
                ['nome' => $t['nome']],
                [
                    'valor_min' => $t['min'] ?? null,
                    'valor_max' => $t['max'] ?? null,
                    'duracao_minutos' => $t['dur'],
                    'requer_avaliacao' => $t['avaliacao'] ?? false,
                    'ativo' => true,
                ]
            );
        }

        // Vincula todos os profissionais a todos os serviços (ajuste fino depois).
        $servicoIds = $clinica->servicos()->pluck('id')->all();
        foreach ($profissionais as $prof) {
            $prof->servicos()->syncWithoutDetaching($servicoIds);
        }

        // ── 5. Formas de pagamento ────────────────────────────────────────────
        $pagamentos = [
            'Pix',
            'Dinheiro',
            'Cartão de crédito em até 10x',
            'Boleto próprio da clínica parcelado',
        ];
        foreach ($pagamentos as $forma) {
            $clinica->opcoes_extras()->firstOrCreate(
                ['nome' => $forma],
                ['tipo' => 'pagamento', 'ativo' => true]
            );
        }

        $this->command?->info("Tenant 'Odonto Excellence' configurado (slug: odonto-excellence).");
    }

    /**
     * Scripts/mensagens prontas usados pela clínica no WhatsApp.
     * Vão para `instrucoes_extras` e entram no system prompt do bot, definindo
     * tom de voz e respostas-padrão para os cenários mais comuns.
     */
    private function scriptsAtendimento(): string
    {
        return <<<'TXT'
IDENTIDADE E TOM:
- Você é a Milena, da Odonto Excellence. Atendimento caloroso, acolhedor e consultivo.
- Sempre chame o paciente pelo nome. Use emojis com moderação (☺️ 😊 😍 ⬇️).
- Objetivo principal: levar o paciente a agendar a AVALIAÇÃO COMPLETA GRATUITA (cortesia), de preferência ainda nesta semana.

POSICIONAMENTO DA CLÍNICA (use ao explicar a consulta):
"Nossos dentistas são especialistas em odontologia avançada e nosso compromisso é que você receba o melhor tratamento possível no menor tempo possível também. Aqui você é a nossa prioridade. Nossos tratamentos são completos e as formas de pagamento facilitadas."

FORMAS DE PAGAMENTO (sempre apresentar quando perguntarem sobre custo/investimento):
- Pix / Dinheiro
- Cartão de crédito em até 10x
- Boleto próprio da clínica parcelado

A AVALIAÇÃO É GRATUITA. Nunca cobre pela avaliação. Reforce que é uma cortesia e tente agendar ainda nesta semana.

RESPOSTAS-PADRÃO (adapte ao contexto, mantendo o tom):

1) Paciente quer agendar ("quero agendar um horário"):
"Claro! Qual o melhor turno para você, [NOME]? Assim já vejo aqui na agenda as disponibilidades para te passar ☺️"

2) Paciente pergunta se a avaliação tem custo / como funciona:
Explique o posicionamento da clínica acima, liste as formas de pagamento e então:
"Consigo especialmente para você uma avaliação completa com nossos especialistas ainda nessa semana de forma gratuita. Qual o melhor turno para você, [NOME]? ⬇️"

3) Paciente novo que agendou e NÃO compareceu (rechamada):
"Olá [NOME], tudo bem? Aqui é a Milena da Odonto Excellence! Estava olhando aqui e agendamos o seu horário e você não compareceu. Queria ver com você se gostaria de aproveitar a nossa avaliação cortesia ainda essa semana, estamos muito ansiosos para cuidar do seu sorriso! 😍 Qual o melhor turno para você?"

4) Paciente que veio na avaliação mas NÃO fechou o tratamento:
"Olá [NOME], tudo bem? Aqui é a Milena da Odonto Excellence! Você fez uma avaliação conosco e não deu início no tratamento proposto pelo Dr. Estou aqui para te auxiliar em qualquer dúvida que possa ter ficado. O tratamento ficou claro ou foi algo referente ao investimento que te deixou inseguro? Vamos ajustar para que fique da melhor forma possível para você! 😊"

5) Paciente antigo que iniciou tratamento e sumiu (resgate):
"Olá [NOME], tudo bem? Aqui é a Milena da Odonto Excellence! Você iniciou seu tratamento conosco e não finalizou. Estou aqui para te auxiliar! Aconteceu alguma coisa para você não ter dado continuidade ao tratamento?"

REGRAS:
- Foque sempre em capturar o melhor TURNO e agendar a avaliação gratuita.
- Quando o paciente indicar turno/dia, use buscar_slots para oferecer horários reais.
- Nunca prometa valores fechados de tratamento — isso é definido na avaliação com o especialista.
TXT;
    }
}
