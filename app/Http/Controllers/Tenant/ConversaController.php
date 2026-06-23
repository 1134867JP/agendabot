<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Conversa;
use App\Services\EvolutionApiService;
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

        if ($status = $request->status) {
            $query->where('status_v2', $status);
        }

        return Inertia::render('Tenant/Conversas/Index', [
            'conversas' => $query->paginate(30)->withQueryString(),
            'filtros'   => $request->only('status'),
        ]);
    }

    public function mensagens(Conversa $conversa): JsonResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);

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
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);

        $conversa->update(['status_v2' => 'em_atendimento_humano']);
        $conversa->registrarMensagem('bot', '⚠️ Atendimento assumido por um humano.');

        return back()->with('success', 'Atendimento assumido.');
    }

    public function devolver(Conversa $conversa): RedirectResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);

        $conversa->update(['status_v2' => 'ativa']);
        $conversa->registrarMensagem('bot', '🤖 Bot reativado. Continuando atendimento automático.');

        return back()->with('success', 'Bot reativado.');
    }

    public function enviarMensagem(Request $request, Conversa $conversa, EvolutionApiService $evolution): RedirectResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);

        $data = $request->validate([
            'conteudo' => 'required|string|max:4000',
        ]);

        $tenant = app('tenant');

        $conversa->registrarMensagem('humano', $data['conteudo']);
        $evolution->enviarMensagem($tenant->evolution_instance, $conversa->telefone_cliente, $data['conteudo']);

        return back();
    }
}
