<?php

namespace App\Services;

use App\Jobs\SendOutboundMessageJob;
use App\Models\Agendamento;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OutboundMessageService
{
    public function queueConversationMessage(
        Tenant $tenant,
        Conversa $conversa,
        string $remetente,
        string $conteudo,
        string $telefone,
        string $purpose = 'conversation',
    ): Mensagem {
        [$mensagem, $outbound] = DB::transaction(function () use ($tenant, $conversa, $remetente, $conteudo, $telefone, $purpose): array {
            $mensagem = $conversa->registrarMensagem($remetente, $conteudo);
            $outbound = $this->create(
                tenant: $tenant,
                telefone: $telefone,
                conteudo: $conteudo,
                purpose: $purpose,
                idempotencyKey: "conversation-message:{$mensagem->id}",
                conversa: $conversa,
                mensagem: $mensagem,
            );

            return [$mensagem, $outbound];
        });

        $this->dispatchIfPending($outbound);

        return $mensagem;
    }

    public function queue(
        Tenant $tenant,
        string $telefone,
        string $conteudo,
        string $purpose,
        string $idempotencyKey,
        ?Agendamento $agendamento = null,
    ): OutboundMessage {
        $outbound = DB::transaction(fn (): OutboundMessage => $this->create(
            tenant: $tenant,
            telefone: $telefone,
            conteudo: $conteudo,
            purpose: $purpose,
            idempotencyKey: $idempotencyKey,
            agendamento: $agendamento,
        ));

        $this->dispatchIfPending($outbound);

        return $outbound;
    }

    public function dispatchIfPending(OutboundMessage $outbound): void
    {
        if ($outbound->status !== OutboundMessage::STATUS_PENDING) {
            return;
        }

        SendOutboundMessageJob::dispatch($outbound->id)->onQueue('messages');
    }

    private function create(
        Tenant $tenant,
        string $telefone,
        string $conteudo,
        string $purpose,
        string $idempotencyKey,
        ?Conversa $conversa = null,
        ?Mensagem $mensagem = null,
        ?Agendamento $agendamento = null,
    ): OutboundMessage {
        $telefone = preg_replace('/\D+/', '', $telefone) ?? '';

        if ($telefone === '') {
            throw new InvalidArgumentException('O telefone da mensagem de saída é obrigatório.');
        }

        if (! $tenant->evolution_instance) {
            throw new InvalidArgumentException('O tenant não possui uma instância do WhatsApp configurada.');
        }

        return OutboundMessage::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'tenant_id' => $tenant->id,
                'conversa_id' => $conversa?->id,
                'mensagem_id' => $mensagem?->id,
                'agendamento_id' => $agendamento?->id,
                'telefone' => $telefone,
                'conteudo' => $conteudo,
                'purpose' => $purpose,
                'status' => OutboundMessage::STATUS_PENDING,
            ],
        );
    }
}
