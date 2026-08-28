<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only([
                    'id', 'name', 'email', 'telefone', 'is_super_admin',
                ]),
            ],
            'currentTenant' => function () {
                if (! app()->bound('tenant')) {
                    return null;
                }

                return app('tenant')->only([
                    'id', 'nome', 'slug', 'tipo_servico', 'tipo_servico_personalizado',
                    'telefone_whatsapp', 'whatsapp_conectado', 'ramo_negocio',
                    'descricao_negocio', 'cidade', 'endereco', 'horarios_funcionamento',
                    'timezone',
                    'nome_agente', 'tom_voz', 'bot_saudacao', 'bot_ativo', 'modo_bot',
                    'horario_atendimento', 'mensagem_fora_horario', 'ativo',
                ]);
            },
            'impersonando' => fn () => (bool) session('impersonando_tenant_id'),
            'tenantPapel' => function () use ($request) {
                if (! app()->bound('tenant') || ! $request->user()) {
                    return null;
                }

                return app('tenant')->users()->where('user_id', $request->user()->id)->value('papel');
            },
            'flash' => [
                'success' => fn () => session('success'),
                'erro' => fn () => session('erro'),
            ],
            'subscription' => function () {
                if (! app()->bound('tenant')) {
                    return null;
                }
                $tenant = app('tenant');

                return [
                    'status' => $tenant->subscription_status,
                    'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                    'subscription_ends_at' => $tenant->subscription_ends_at?->toIso8601String(),
                    'plano' => $tenant->plano,
                    'isento_cobranca' => $tenant->isento_cobranca,
                ];
            },
        ];
    }
}
