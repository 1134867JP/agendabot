<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\EvolutionApiService;
use App\Services\OutboundMessageService;
use App\Services\WhatsAppSyncState;
use App\Support\TenantAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ConversaController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');

        $query = TenantAccess::scopeConversas(Conversa::where('tenant_id', $tenant->id), $tenant)
            ->with([
                'cliente',
                'mensagens' => fn ($q) => $q->orderByDesc('enviada_em')->limit(1),
            ])
            ->whereHas('mensagens')
            ->orderByRaw("CASE status_v2 WHEN 'aguardando_humano' THEN 0 WHEN 'em_atendimento_humano' THEN 1 WHEN 'ativa' THEN 2 ELSE 3 END")
            ->orderByDesc('ultima_mensagem_em');

        if ($status = $request->status_v2) {
            $query->where('status_v2', $status);
        }

        if ($busca = trim((string) $request->busca)) {
            $termo = mb_strtolower($busca);
            $termoLike = '%'.addcslashes($termo, '%_\\').'%';
            $telefone = preg_replace('/\D/', '', $busca);

            $query->where(function ($q) use ($termoLike, $telefone) {
                $q->whereHas('cliente', function ($cliente) use ($termoLike, $telefone) {
                    $cliente->whereRaw('LOWER(nome) LIKE ?', [$termoLike]);
                    if ($telefone !== '') {
                        $cliente->orWhere('telefone', 'like', "%{$telefone}%");
                    }
                });

                if ($telefone !== '') {
                    $q->orWhere('telefone_cliente', 'like', "%{$telefone}%");
                }
            });
        }

        return Inertia::render('Tenant/Conversas/Index', [
            'conversas' => Inertia::scroll(
                fn () => $query->paginate(30)->withQueryString()
            ),
            'filtros' => $request->only('status_v2', 'busca'),
        ]);
    }

    public function mensagens(Conversa $conversa): JsonResponse
    {
        TenantAccess::assertConversa($conversa, app('tenant'));

        $conversa->update(['ultima_leitura_em' => now()]);

        $mensagens = $conversa->mensagens()
            ->with('outboundMessage:id,mensagem_id,status')
            ->orderByDesc('enviada_em')
            ->limit(50)
            ->get()
            ->sortBy('enviada_em')
            ->values()
            ->map(function (Mensagem $mensagem): Mensagem {
                $mensagem->setAttribute('delivery_status', $mensagem->outboundMessage?->status);
                $mensagem->unsetRelation('outboundMessage');

                return $mensagem;
            });

        return response()->json($mensagens);
    }

    public function notificacoes(): JsonResponse
    {
        $tenant = app('tenant');

        $ultimaMensagem = Mensagem::whereHas('conversa', function ($query) use ($tenant): void {
            TenantAccess::scopeConversas($query->where('tenant_id', $tenant->id), $tenant);
        })
            ->orderByDesc('enviada_em')
            ->orderByDesc('id')
            ->first(['id', 'conversa_id', 'enviada_em']);

        $query = TenantAccess::scopeConversas(Conversa::where('tenant_id', $tenant->id), $tenant)
            ->whereNotNull('ultima_mensagem_em')
            ->where(function ($q) {
                $q->whereNull('ultima_leitura_em')
                    ->orWhereColumn('ultima_mensagem_em', '>', 'ultima_leitura_em');
            });

        $total = $query->count();

        $preview = (clone $query)
            ->with([
                'cliente:id,nome,telefone',
                'mensagens' => fn ($q) => $q->orderByDesc('enviada_em')->limit(1),
            ])
            ->orderByDesc('ultima_mensagem_em')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nome' => $c->cliente?->nome ?? $c->telefone_cliente,
                'telefone' => $c->telefone_cliente,
                'preview' => $c->mensagens->first()?->conteudo ?? '',
                'tipo' => $c->mensagens->first()?->tipo ?? 'texto',
                'remetente' => $c->mensagens->first()?->remetente ?? 'cliente',
                'em' => $c->ultima_mensagem_em,
            ]);

        return response()->json([
            'conversas_nao_lidas' => $total,
            'preview' => $preview,
            'ultima_conversa_id' => $ultimaMensagem?->conversa_id,
            'ultima_mensagem_id' => $ultimaMensagem?->id,
            'ultima_mensagem_em' => $ultimaMensagem?->enviada_em,
        ]);
    }

    public function marcarLida(Conversa $conversa): JsonResponse
    {
        TenantAccess::assertConversa($conversa, app('tenant'));
        $conversa->update(['ultima_leitura_em' => now()]);

        return response()->json(['ok' => true]);
    }

    public function atualizarNomeCliente(Request $request, Conversa $conversa): RedirectResponse
    {
        $tenant = app('tenant');
        TenantAccess::assertConversa($conversa, $tenant);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
        ]);
        $nome = trim($data['nome']);

        $cliente = $conversa->cliente ?? Cliente::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone' => $conversa->telefone_cliente],
            ['nome' => $nome]
        );

        $cliente->update(['nome' => $nome]);
        if ((int) $conversa->cliente_id !== (int) $cliente->id) {
            $conversa->update(['cliente_id' => $cliente->id]);
        }

        return back()->with('success', 'Nome do contato atualizado.');
    }

    public function assumir(Conversa $conversa): RedirectResponse
    {
        $tenant = app('tenant');
        TenantAccess::assertConversa($conversa, $tenant);

        $dados = ['status_v2' => 'em_atendimento_humano'];
        if ($profissionalId = TenantAccess::profissionalId($tenant)) {
            $dados['profissional_id'] = $profissionalId;
        }
        $conversa->update($dados);

        return back()->with('success', 'Você assumiu o atendimento. O bot foi pausado para esta conversa.');
    }

    public function devolver(Conversa $conversa): RedirectResponse
    {
        TenantAccess::assertConversa($conversa, app('tenant'));

        $conversa->update(['status_v2' => 'ativa']);

        return back()->with('success', 'Bot reativado. Ele responderá as próximas mensagens do cliente.');
    }

    public function enviarMensagem(Request $request, Conversa $conversa, OutboundMessageService $outboundMessages): RedirectResponse
    {
        TenantAccess::assertConversa($conversa, app('tenant'));

        $data = $request->validate([
            'conteudo' => 'required|string|max:4000',
        ]);

        $tenant = app('tenant');

        // Responder enquanto o bot está ativo assume o atendimento no mesmo request.
        // O evento é apenas operacional e não deve aparecer como se fosse mensagem do bot.
        if ($conversa->status_v2 !== 'em_atendimento_humano') {
            $conversa->update(['status_v2' => 'em_atendimento_humano']);
        }

        $outboundMessages->queueConversationMessage(
            $tenant,
            $conversa,
            'humano',
            $data['conteudo'],
            $conversa->telefone_cliente,
            'human_reply',
        );

        return back()->with('success', 'Mensagem adicionada à fila de envio. Você está atendendo esta conversa.');
    }

    public function iniciar(Request $request, OutboundMessageService $outboundMessages): RedirectResponse
    {
        $tenant = app('tenant');

        $validated = $request->validate([
            'telefone' => ['required', 'string', 'max:20'],
            'mensagem' => ['required', 'string', 'max:4000'],
        ]);

        $telefone = preg_replace('/\D/', '', $validated['telefone']);

        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone' => $telefone],
            ['nome' => $telefone]
        );

        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone_cliente' => $telefone],
            array_filter([
                'cliente_id' => $cliente->id,
                'status_v2' => 'em_atendimento_humano',
                'profissional_id' => TenantAccess::profissionalId($tenant),
            ])
        );

        TenantAccess::assertConversa($conversa, $tenant);

        if ($conversa->status_v2 === 'ativa') {
            $conversa->update(['status_v2' => 'em_atendimento_humano']);
        }

        $outboundMessages->queueConversationMessage(
            $tenant,
            $conversa,
            'humano',
            $validated['mensagem'],
            $telefone,
            'human_first_contact',
        );

        return back()->with('success', 'Mensagem adicionada à fila de envio. O atendimento ficou com a equipe.');
    }

    public function media(Conversa $conversa, Mensagem $mensagem, EvolutionApiService $evolution): HttpResponse
    {
        TenantAccess::assertConversa($conversa, app('tenant'));
        abort_if((int) $mensagem->conversa_id !== (int) $conversa->id, 404);
        abort_if(! $mensagem->evolution_message_id, 404);

        $tenant = app('tenant');
        $fromMe = $mensagem->remetente === 'humano';
        $remoteJid = $conversa->telefone_cliente.'@s.whatsapp.net';

        $dados = $evolution->fetchMedia($tenant->evolution_instance, $mensagem->evolution_message_id, $fromMe, $remoteJid);

        if (! $dados || empty($dados['base64'])) {
            abort(404);
        }

        $binary = base64_decode($dados['base64']);
        $mimetype = $dados['mimetype'] ?? 'image/jpeg';

        return response($binary, 200, [
            'Content-Type' => $mimetype,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function statusSincronizacao(WhatsAppSyncState $syncState): JsonResponse
    {
        $tenant = app('tenant');
        $status = $syncState->status($tenant);

        if ($status === []) {
            return response()->json([
                'status' => 'idle',
                'message' => 'Pronto para sincronizar.',
            ]);
        }

        return response()->json($status);
    }

    public function sincronizar(WhatsAppSyncState $syncState): RedirectResponse
    {
        $tenant = app('tenant');

        if (! $tenant->evolution_instance || ! $tenant->whatsapp_conectado) {
            return back()->withErrors(['erro' => 'Conecte o WhatsApp antes de sincronizar.']);
        }

        $statusAtual = $syncState->status($tenant);

        if (is_array($statusAtual) && in_array($statusAtual['status'] ?? null, ['queued', 'running'], true)) {
            return back();
        }

        $executionId = $syncState->iniciar($tenant);
        SincronizarConversasWhatsappJob::dispatch($tenant, $executionId)->onQueue('sync');

        return back();
    }

    public function cancelarSincronizacao(WhatsAppSyncState $syncState): JsonResponse
    {
        $tenant = app('tenant');
        $status = $syncState->cancelar($tenant);

        return response()->json([
            'ok' => true,
            'status' => $status,
        ]);
    }
}
