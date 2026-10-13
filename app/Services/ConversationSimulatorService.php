<?php

namespace App\Services;

use App\Jobs\EnviarLembretesJob;
use App\Models\Agendamento;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Tenant;

/** Executa o núcleo conversacional sem criar mensagem de saída no WhatsApp. */
class ConversationSimulatorService
{
    private ConversaSyncService $sync;

    private AgendouService $agendou;

    public function __construct(
        ConversaSyncService $sync,
        AgendouService $agendou,
    ) {
        $this->sync = $sync;
        $this->agendou = $agendou;
    }

    public function enviar(Tenant $tenant, string $telefone, string $texto, string $messageId): array
    {
        $recebida = $this->sync->registrarMensagemRecebida($tenant, $telefone, $texto, $messageId, 'Cliente Simulado');
        if ($recebida === null) {
            return ['resposta' => 'Mensagem duplicada ignorada.', 'transferir' => false];
        }

        $conversa = Conversa::where('tenant_id', $tenant->id)->findOrFail($recebida->conversa_id);
        $cliente = Cliente::where('tenant_id', $tenant->id)->findOrFail($conversa->cliente_id);
        $historico = $conversa->mensagens()->latest('enviada_em')->latest('id')->limit(12)->get()->reverse()
            ->map(fn ($m) => ['role' => $m->remetente === 'cliente' ? 'user' : 'assistant', 'content' => $m->conteudo])->values()->all();
        while ($historico !== [] && $historico[0]['role'] !== 'user') {
            array_shift($historico);
        }
        $historicoMesclado = [];
        foreach ($historico as $item) {
            if ($historicoMesclado !== [] && end($historicoMesclado)['role'] === $item['role']) {
                $historicoMesclado[array_key_last($historicoMesclado)]['content'] .= "\n".$item['content'];
            } else {
                $historicoMesclado[] = $item;
            }
        }
        $historico = $historicoMesclado;

        $pendente = Agendamento::where('tenant_id', $tenant->id)->whereNotIn('status', ['cancelado', 'concluido'])
            ->where(fn ($q) => $q->where('cliente_id', $cliente->id)->orWhere('cliente_telefone', $telefone))
            ->where(fn ($q) => $q->where('data_hora', '>', now())->orWhere('inicio', '>', now()))
            ->orderByRaw('COALESCE(data_hora, inicio)')->first();
        $resultado = $this->agendou->processarMensagem($tenant, $historico, ['id' => $cliente->id, 'nome' => $cliente->nome, 'telefone' => $cliente->telefone], $pendente);
        if ($resultado['transferir']) {
            $conversa->update(['status_v2' => 'aguardando_humano']);
        }

        $conversa->registrarMensagem('bot', $resultado['resposta']);

        return $resultado;
    }

    /** Gera exatamente o texto do lembrete para inspeção, sem enfileirar ou enviar. */
    public function preverLembrete(Agendamento $agendamento): string
    {
        return EnviarLembretesJob::montarMensagem($agendamento, $agendamento->tenant->configuracoes['lembrete_texto'] ?? null);
    }
}
