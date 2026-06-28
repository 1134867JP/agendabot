<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Services\EvolutionApiService;
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

    public function media(Conversa $conversa, Mensagem $mensagem, EvolutionApiService $evolution): HttpResponse
    {
        abort_if((int) $conversa->tenant_id !== (int) app('tenant')->id, 403);
        abort_if((int) $mensagem->conversa_id !== (int) $conversa->id, 404);
        abort_if(! $mensagem->evolution_message_id, 404);

        $tenant    = app('tenant');
        $fromMe    = $mensagem->remetente === 'humano';
        $remoteJid = $conversa->telefone_cliente . '@s.whatsapp.net';

        $dados = $evolution->fetchMedia($tenant->evolution_instance, $mensagem->evolution_message_id, $fromMe, $remoteJid);

        if (! $dados || empty($dados['base64'])) {
            abort(404);
        }

        $binary   = base64_decode($dados['base64']);
        $mimetype = $dados['mimetype'] ?? 'image/jpeg';

        return response($binary, 200, [
            'Content-Type'  => $mimetype,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function sincronizar(): RedirectResponse
    {
        $tenant = app('tenant');

        if (!$tenant->evolution_instance) {
            return back()->withErrors(['erro' => 'WhatsApp não configurado.']);
        }

        SincronizarConversasWhatsappJob::dispatch($tenant)->onQueue('default');

        return back()->with('success', 'Sincronização iniciada em segundo plano. As conversas aparecem em instantes.');
    }
}
