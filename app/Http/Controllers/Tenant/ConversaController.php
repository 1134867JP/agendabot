<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppSyncState;
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

        $query = Conversa::where('tenant_id', $tenant->id)
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
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);

        $conversa->update(['ultima_leitura_em' => now()]);

        $mensagens = $conversa->mensagens()
            ->orderByDesc('enviada_em')
            ->limit(50)
            ->get()
            ->sortBy('enviada_em')
            ->values();

        return response()->json($mensagens);
    }

    public function notificacoes(): JsonResponse
    {
        $tenant = app('tenant');

        $ultimaMensagem = Mensagem::whereHas(
            'conversa',
            fn ($q) => $q->where('tenant_id', $tenant->id),
        )
            ->orderByDesc('enviada_em')
            ->orderByDesc('id')
            ->first(['id', 'conversa_id', 'enviada_em']);

        $query = Conversa::where('tenant_id', $tenant->id)
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
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);
        $conversa->update(['ultima_leitura_em' => now()]);

        return response()->json(['ok' => true]);
    }

    public function atualizarNomeCliente(Request $request, Conversa $conversa): RedirectResponse
    {
        $tenant = app('tenant');
        abort_if((int) $conversa->tenant_id !== (int) $tenant->id, 403);

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
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);

        $conversa->update(['status_v2' => 'em_atendimento_humano']);
        $conversa->registrarMensagem('bot', 'Atendimento assumido por uma pessoa da equipe.');

        return back()->with('success', 'Atendimento assumido.');
    }

    public function devolver(Conversa $conversa): RedirectResponse
    {
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);

        $conversa->update(['status_v2' => 'ativa']);
        $conversa->registrarMensagem('bot', 'Atendimento automático reativado.');

        return back()->with('success', 'Bot reativado.');
    }

    public function enviarMensagem(Request $request, Conversa $conversa, EvolutionApiService $evolution): RedirectResponse
    {
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);

        $data = $request->validate([
            'conteudo' => 'required|string|max:4000',
        ]);

        $tenant = app('tenant');

        // Enviar enquanto o bot está ativo também assume o atendimento. Fazer isso
        // no mesmo request evita que uma segunda navegação do Inertia cancele o envio.
        if ($conversa->status_v2 !== 'em_atendimento_humano') {
            $conversa->update(['status_v2' => 'em_atendimento_humano']);
            $conversa->registrarMensagem('bot', 'Atendimento assumido por uma pessoa da equipe.');
        }

        $conversa->registrarMensagem('humano', $data['conteudo']);
        $evolution->enviarMensagem($tenant->evolution_instance, $conversa->telefone_cliente, $data['conteudo']);

        return back();
    }

    public function iniciar(Request $request, EvolutionApiService $evolution): RedirectResponse
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
            ['cliente_id' => $cliente->id, 'status_v2' => 'em_atendimento_humano']
        );

        if ($conversa->status_v2 === 'ativa') {
            $conversa->update(['status_v2' => 'em_atendimento_humano']);
        }

        $conversa->registrarMensagem('humano', $validated['mensagem']);
        $evolution->enviarMensagem($tenant->evolution_instance, $telefone, $validated['mensagem']);

        return back()->with('success', 'Mensagem enviada.');
    }

    public function media(Conversa $conversa, Mensagem $mensagem, EvolutionApiService $evolution): HttpResponse
    {
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);
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
