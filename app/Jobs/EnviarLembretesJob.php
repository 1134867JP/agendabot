<?php

namespace App\Jobs;

use App\Jobs\Concerns\RegistraFalha;
use App\Models\Agendamento;
use App\Services\OutboundMessageService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Lembrete relativo ao horário do atendimento (padrão: 2h antes na barbearia,
 * 24h nos demais ramos). Roda a cada 15 minutos e respeita o horário de
 * silêncio, para não acordar o cliente.
 */
class EnviarLembretesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RegistraFalha, SerializesModels;

    /** Não envia lembrete antes das 8h nem a partir das 21h (fuso do estabelecimento). */
    public const HORA_INICIO_ENVIO = 8;

    public const HORA_FIM_ENVIO = 21;

    /** Quem marcou há menos tempo que isso acabou de receber a confirmação. */
    public const HORAS_MINIMAS_DESDE_A_MARCACAO = 2;

    public function handle(OutboundMessageService $outboundMessages): void
    {
        // Pré-filtro amplo; a janela exata é calculada no fuso de cada tenant.
        $agendamentos = Agendamento::query()
            ->with(['recurso', 'profissional', 'tenant', 'cliente', 'servico', 'itensServico'])
            ->whereIn('status', ['agendado', 'confirmado'])
            ->where('lembrete_enviado', false)
            ->where(fn ($q) => $q
                ->whereBetween('data_hora', [now()->subDay(), now()->addDays(2)])
                ->orWhere(fn ($q) => $q
                    ->whereNull('data_hora')
                    ->whereBetween('inicio', [now()->subDay(), now()->addDays(2)]))
            )
            ->whereHas('tenant', fn ($q) => $q->where('whatsapp_conectado', true)
                ->whereIn('subscription_status', ['trial', 'active'])
            )
            ->get();

        foreach ($agendamentos as $agendamento) {
            $tenant = $agendamento->tenant;
            $cfg = $tenant->configuracoes ?? [];
            if (($cfg['lembrete_ativo'] ?? true) === false) {
                continue;
            }

            $telefone = $agendamento->cliente_telefone
                ?? $agendamento->cliente?->telefone;

            if (! $telefone || ! $this->estaNaJanela($agendamento)) {
                continue;
            }

            try {
                $outboundMessages->queue(
                    tenant: $tenant,
                    telefone: $telefone,
                    conteudo: self::montarMensagem($agendamento, $cfg['lembrete_texto'] ?? null),
                    purpose: 'appointment_reminder',
                    idempotencyKey: "appointment-reminder:{$agendamento->id}",
                    agendamento: $agendamento,
                );
            } catch (\Throwable $e) {
                Log::error("Falha ao enfileirar lembrete agendamento #{$agendamento->id}: {$e->getMessage()}");
            }
        }
    }

    private function estaNaJanela(Agendamento $agendamento): bool
    {
        $tz = $agendamento->tenant->resolvedTimezone();
        $inicio = self::inicioNoFuso($agendamento);
        $agora = Carbon::now($tz);

        if ($inicio->lte($agora) || $inicio->gt($agora->copy()->addHours($agendamento->tenant->lembreteHorasAntes()))) {
            return false;
        }

        if ($agora->hour < self::HORA_INICIO_ENVIO || $agora->hour >= self::HORA_FIM_ENVIO) {
            return false;
        }

        return ! $agendamento->created_at
            || $agendamento->created_at->lte(now()->subHours(self::HORAS_MINIMAS_DESDE_A_MARCACAO));
    }

    /**
     * data_hora é gravado com o horário de parede do estabelecimento; relê no
     * fuso do tenant para não depender do fuso do processo.
     */
    public static function inicioNoFuso(Agendamento $agendamento): Carbon
    {
        $tz = $agendamento->tenant?->resolvedTimezone() ?? config('app.timezone');
        $bruto = $agendamento->getRawOriginal('data_hora') ?? $agendamento->getRawOriginal('inicio');

        return $bruto
            ? Carbon::parse(substr((string) $bruto, 0, 19), $tz)
            : Carbon::parse($agendamento->data_hora ?? $agendamento->inicio)->setTimezone($tz);
    }

    public static function montarMensagem(Agendamento $agendamento, ?string $textoPersonalizado): string
    {
        $tz = $agendamento->tenant->resolvedTimezone();
        $dataHora = self::inicioNoFuso($agendamento)->locale('pt_BR');
        $hoje = Carbon::now($tz);
        $quando = match (true) {
            $dataHora->isSameDay($hoje) => 'hoje',
            $dataHora->isSameDay($hoje->copy()->addDay()) => 'amanhã',
            default => $dataHora->translatedFormat('l, d \d\e F'),
        };
        $nomeCliente = $agendamento->cliente_nome ?? $agendamento->cliente?->nome ?? 'Cliente';
        $recursoNome = $agendamento->recurso?->nome ?? $agendamento->profissional?->nome ?? '';
        $tenant = $agendamento->tenant->nome;

        $cabecalho = "👋 *Olá, {$nomeCliente}!*\n\n"
                   ."Passando para lembrar do seu horário *{$quando}*:\n\n"
                   ."📅 *Data:* {$dataHora->translatedFormat('l, d \d\e F')}\n"
                   ."⏰ *Horário:* {$dataHora->format('H:i')}\n"
                   .(($servicos = $agendamento->descricaoServicos()) ? "✂️ *Serviço:* {$servicos}\n" : '')
                   ."📍 *Com:* {$recursoNome} — {$tenant}\n\n";

        $corpo = match (true) {
            (bool) $textoPersonalizado => trim($textoPersonalizado)."\n\n",
            $quando === 'hoje' => "Se não puder vir, responda ❌ *CANCELAR* para liberar o horário.\n\n",
            default => "Para *confirmar*, responda: ✅ *CONFIRMO*\nPara *cancelar*, responda: ❌ *CANCELAR*\n\n",
        };

        return $cabecalho.$corpo.'_Até já!_';
    }

    public function failed(\Throwable $e): void
    {
        $this->registrarFalha($e, null, ['evento' => 'enviar_lembretes']);
    }
}
