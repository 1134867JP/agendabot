<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\EvolutionApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->orderByDesc('ultima_mensagem_em');

        if ($status = $request->status_v2) {
            $query->where('status_v2', $status);
        }

        return Inertia::render('Tenant/Conversas/Index', [
            'conversas' => $query->paginate(30)->withQueryString(),
            'filtros'   => $request->only('status_v2'),
        ]);
    }

    public function mensagens(Conversa $conversa): JsonResponse
    {
        abort_if((int)$conversa->tenant_id !== (int)app('tenant')->id, 403);

        $mensagens = $conversa->mensagens()
            ->orderByDesc('enviada_em')
            ->limit(50)
            ->get()
            ->sortBy('enviada_em')
            ->values();

        return response()->json($mensagens);
    }

    public function assumir(Conversa $conversa): RedirectResponse
    {
        abort_if((int)$conversa->tenant_id !== (int)app('tenant')->id, 403);

        $conversa->update(['status_v2' => 'em_atendimento_humano']);
        $conversa->registrarMensagem('bot', '⚠️ Atendimento assumido por um humano.');

        return back()->with('success', 'Atendimento assumido.');
    }

    public function devolver(Conversa $conversa): RedirectResponse
    {
        abort_if((int)$conversa->tenant_id !== (int)app('tenant')->id, 403);

        $conversa->update(['status_v2' => 'ativa']);
        $conversa->registrarMensagem('bot', '🤖 Bot reativado. Continuando atendimento automático.');

        return back()->with('success', 'Bot reativado.');
    }

    public function enviarMensagem(Request $request, Conversa $conversa, EvolutionApiService $evolution): RedirectResponse
    {
        abort_if((int)$conversa->tenant_id !== (int)app('tenant')->id, 403);

        $data = $request->validate([
            'conteudo' => 'required|string|max:4000',
        ]);

        $tenant = app('tenant');

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

    public function sincronizar(EvolutionApiService $evolution): RedirectResponse
    {
        $tenant = app('tenant');

        if (!$tenant->evolution_instance) {
            return back()->withErrors(['erro' => 'WhatsApp não configurado.']);
        }

        $chats = $evolution->fetchChats($tenant->evolution_instance);

        foreach ($chats as $chat) {
            $remoteJid = data_get($chat, 'id');
            if (!$remoteJid || str_contains($remoteJid, '@g.us')) {
                continue;
            }

            $telefone = str_replace('@s.whatsapp.net', '', $remoteJid);
            $nome     = data_get($chat, 'name') ?? $telefone;

            $cliente = Cliente::firstOrCreate(
                ['tenant_id' => $tenant->id, 'telefone' => $telefone],
                ['nome' => $nome]
            );

            $conversa = Conversa::firstOrCreate(
                ['tenant_id' => $tenant->id, 'telefone_cliente' => $telefone],
                ['cliente_id' => $cliente->id, 'status_v2' => 'ativa']
            );

            $msgs = $evolution->fetchMessages($tenant->evolution_instance, $remoteJid, 100);
            foreach ($msgs as $msg) {
                $evolutionId = data_get($msg, 'key.id');
                if (!$evolutionId) {
                    continue;
                }

                if (Mensagem::where('evolution_message_id', $evolutionId)->exists()) {
                    continue;
                }

                $fromMe   = (bool) data_get($msg, 'key.fromMe', false);
                $conteudo = data_get($msg, 'message.conversation')
                         ?? data_get($msg, 'message.extendedTextMessage.text')
                         ?? '[mídia]';
                $ts = data_get($msg, 'messageTimestamp');

                $conversa->mensagens()->create([
                    'remetente'            => $fromMe ? 'humano' : 'cliente',
                    'conteudo'             => $conteudo,
                    'evolution_message_id' => $evolutionId,
                    'enviada_em'           => $ts ? Carbon::createFromTimestamp((int)$ts) : now(),
                ]);
            }

            $ultimaMensagem = $conversa->mensagens()->orderByDesc('enviada_em')->value('enviada_em');
            if ($ultimaMensagem) {
                $conversa->update(['ultima_mensagem_em' => $ultimaMensagem]);
            }
        }

        return back()->with('success', 'Conversas sincronizadas com sucesso.');
    }
}
