# Prompt Complementar — Super Admin + Painel do Dono

> Adicionar ao projeto Agendou existente. Considere toda a estrutura já criada
> (tenants, recursos, agendamentos, conversas, evolution API, bot WhatsApp).

---

## Contexto

Existem dois níveis de acesso:

1. **Super Admin** — único usuário (`is_super_admin = true`), gerencia toda a plataforma
2. **Dono do Tenant** — acessa apenas seu próprio estabelecimento, com painel operacional completo

---

## Fase A — Super Admin

### A.1 Migration

```php
// Adicionar coluna na tabela users (se ainda não existir)
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_super_admin')->default(false)->after('email');
});
```

### A.2 Middleware `superadmin`

```php
// app/Http/Middleware/EnsureIsSuperAdmin.php
public function handle(Request $request, Closure $next): Response
{
    if (!$request->user()?->is_super_admin) {
        abort(403, 'Acesso restrito.');
    }
    return $next($request);
}
```

### A.3 Rotas Super Admin

```php
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {

    // Dashboard geral
    Route::get('/', [SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

    // Gestão de tenants
    Route::resource('tenants', SuperAdmin\TenantController::class);
    Route::patch('tenants/{tenant}/toggle-ativo', [SuperAdmin\TenantController::class, 'toggleAtivo']);

    // Visualizar agendamentos de qualquer tenant
    Route::get('agendamentos', [SuperAdmin\AgendamentoController::class, 'index']);

    // Impersonar tenant (entrar como dono para suporte)
    Route::post('tenants/{tenant}/impersonar', [SuperAdmin\TenantController::class, 'impersonar']);
    Route::delete('impersonar', [SuperAdmin\TenantController::class, 'pararImpersonar']);
});
```

### A.4 `SuperAdmin\DashboardController`

```php
public function index(): Response
{
    return Inertia::render('SuperAdmin/Dashboard', [
        'stats' => [
            'total_tenants'         => Tenant::count(),
            'tenants_ativos'        => Tenant::where('ativo', true)->count(),
            'tenants_conectados'    => Tenant::where('whatsapp_conectado', true)->count(),
            'agendamentos_hoje'     => Agendamento::whereDate('inicio', today())->count(),
            'agendamentos_mes'      => Agendamento::whereMonth('inicio', now()->month)->count(),
        ],
        'tenants' => Tenant::withCount(['agendamentos', 'recursos'])
            ->latest()
            ->paginate(20),
    ]);
}
```

### A.5 `SuperAdmin\TenantController`

```php
// index — lista todos os tenants com stats
public function index(): Response
{
    return Inertia::render('SuperAdmin/Tenants/Index', [
        'tenants' => Tenant::withCount(['agendamentos', 'recursos'])
            ->with('users')
            ->latest()
            ->paginate(20),
    ]);
}

// store — cria novo tenant + instância Evolution + cria usuário dono
public function store(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'nome'          => 'required|string|max:255',
        'tipo_servico'  => 'required|in:barbeiro,quadra,estetica,personalizado',
        'email_dono'    => 'required|email|unique:users,email',
        'senha_dono'    => 'required|min:8',
    ]);

    DB::transaction(function () use ($validated) {
        $slug = Str::slug($validated['nome']) . '-' . Str::random(4);

        $tenant = Tenant::create([
            'nome'               => $validated['nome'],
            'slug'               => $slug,
            'tipo_servico'       => $validated['tipo_servico'],
            'evolution_instance' => $slug,
        ]);

        $dono = User::create([
            'name'     => $validated['nome'],
            'email'    => $validated['email_dono'],
            'password' => Hash::make($validated['senha_dono']),
        ]);

        $tenant->users()->attach($dono->id, ['papel' => 'admin']);

        // Criar instância na Evolution API
        app(EvolutionApiService::class)->criarInstancia($slug);
        app(EvolutionApiService::class)->configurarWebhook(
            $slug,
            route('webhook', $slug)
        );
    });

    return redirect()->route('superadmin.tenants.index')
        ->with('success', 'Tenant criado com sucesso.');
}

// impersonar — super admin assume sessão de um tenant para suporte
public function impersonar(Request $request, Tenant $tenant): RedirectResponse
{
    session([
        'impersonando_tenant_id' => $tenant->id,
        'tenant_id'              => $tenant->id,
    ]);
    return redirect()->route('tenant.dashboard');
}

public function pararImpersonar(): RedirectResponse
{
    session()->forget(['impersonando_tenant_id', 'tenant_id']);
    return redirect()->route('superadmin.dashboard');
}
```

### A.6 Página `SuperAdmin/Dashboard.tsx`

```tsx
// Exibir:
// - Cards de stats (total tenants, conectados, agendamentos hoje/mês)
// - Tabela de tenants com: nome, tipo, status WhatsApp (badge), nº agendamentos, ações
// - Ação: Editar | Ativar/Desativar | Impersonar (entrar como dono)
// - Banner quando estiver impersonando: "Você está como [Nome Tenant]" + botão Sair
```

---

## Fase B — Painel do Dono do Tenant

### B.1 Rotas do Tenant

```php
Route::middleware(['auth', 'tenant'])->prefix('painel')->name('tenant.')->group(function () {

    Route::get('/', [Tenant\DashboardController::class, 'index'])->name('dashboard');

    // Agenda visual
    Route::get('agenda', [Tenant\AgendaController::class, 'index'])->name('agenda');
    Route::get('agenda/disponibilidade', [Tenant\AgendaController::class, 'disponibilidade']);

    // Agendamentos
    Route::get('agendamentos', [Tenant\AgendamentoController::class, 'index'])->name('agendamentos.index');
    Route::post('agendamentos', [Tenant\AgendamentoController::class, 'store'])->name('agendamentos.store'); // reserva manual
    Route::patch('agendamentos/{agendamento}/cancelar', [Tenant\AgendamentoController::class, 'cancelar']);
    Route::patch('agendamentos/{agendamento}/concluir', [Tenant\AgendamentoController::class, 'concluir']);

    // Recursos
    Route::resource('recursos', Tenant\RecursoController::class);

    // Horários de funcionamento
    Route::post('recursos/{recurso}/horarios', [Tenant\HorarioController::class, 'store']);
    Route::put('horarios/{horario}', [Tenant\HorarioController::class, 'update']);
    Route::delete('horarios/{horario}', [Tenant\HorarioController::class, 'destroy']);

    // WhatsApp
    Route::get('whatsapp', [Tenant\WhatsAppController::class, 'index'])->name('whatsapp');
    Route::get('whatsapp/qrcode', [Tenant\WhatsAppController::class, 'qrcode']);
    Route::get('whatsapp/status', [Tenant\WhatsAppController::class, 'status']);

    // Configurações gerais
    Route::get('configuracoes', [Tenant\ConfiguracaoController::class, 'index']);
    Route::put('configuracoes', [Tenant\ConfiguracaoController::class, 'update']);
});
```

### B.2 `Tenant\DashboardController`

```php
public function index(): Response
{
    $tenant = app('tenant');

    return Inertia::render('Tenant/Dashboard', [
        'stats' => [
            'agendamentos_hoje'     => Agendamento::where('tenant_id', $tenant->id)
                                        ->whereDate('inicio', today())
                                        ->where('status', 'confirmado')
                                        ->count(),
            'agendamentos_semana'   => Agendamento::where('tenant_id', $tenant->id)
                                        ->whereBetween('inicio', [now()->startOfWeek(), now()->endOfWeek()])
                                        ->where('status', 'confirmado')
                                        ->count(),
            'receita_mes'           => Agendamento::where('tenant_id', $tenant->id)
                                        ->whereMonth('inicio', now()->month)
                                        ->where('status', '!=', 'cancelado')
                                        ->sum('valor_total'),
            'whatsapp_conectado'    => $tenant->whatsapp_conectado,
        ],
        'proximos_agendamentos' => Agendamento::where('tenant_id', $tenant->id)
            ->with('recurso')
            ->where('inicio', '>=', now())
            ->where('status', 'confirmado')
            ->orderBy('inicio')
            ->limit(5)
            ->get(),
    ]);
}
```

### B.3 `Tenant\AgendaController`

```php
// Retorna view da agenda visual
public function index(): Response
{
    $tenant = app('tenant');

    return Inertia::render('Tenant/Agenda', [
        'recursos' => $tenant->recursos()->where('ativo', true)->get(),
    ]);
}

// Endpoint AJAX: retorna agendamentos de um recurso para um período
public function disponibilidade(Request $request): JsonResponse
{
    $tenant = app('tenant');

    $request->validate([
        'recurso_id' => 'required|exists:recursos,id',
        'data_inicio' => 'required|date',
        'data_fim'    => 'required|date',
    ]);

    $agendamentos = Agendamento::where('tenant_id', $tenant->id)
        ->where('recurso_id', $request->recurso_id)
        ->where('status', '!=', 'cancelado')
        ->whereBetween('inicio', [$request->data_inicio, $request->data_fim])
        ->with('recurso')
        ->get()
        ->map(fn($a) => [
            'id'              => $a->id,
            'title'           => $a->cliente_nome,
            'start'           => $a->inicio,
            'end'             => $a->fim,
            'telefone'        => $a->cliente_telefone,
            'status'          => $a->status,
            'valor_total'     => $a->valor_total,
            'origem'          => $a->origem, // 'whatsapp' | 'manual'
        ]);

    return response()->json($agendamentos);
}
```

### B.4 `Tenant\AgendamentoController` — com reserva manual

```php
// Listagem com filtros
public function index(Request $request): Response
{
    $tenant = app('tenant');

    $query = Agendamento::where('tenant_id', $tenant->id)
        ->with('recurso')
        ->orderBy('inicio', 'desc');

    // Filtros
    if ($request->data) {
        $query->whereDate('inicio', $request->data);
    }
    if ($request->recurso_id) {
        $query->where('recurso_id', $request->recurso_id);
    }
    if ($request->status) {
        $query->where('status', $request->status);
    }
    if ($request->busca) {
        $query->where(function ($q) use ($request) {
            $q->where('cliente_nome', 'ilike', "%{$request->busca}%")
              ->orWhere('cliente_telefone', 'like', "%{$request->busca}%");
        });
    }

    return Inertia::render('Tenant/Agendamentos/Index', [
        'agendamentos' => $query->paginate(20)->withQueryString(),
        'recursos'     => $tenant->recursos()->where('ativo', true)->get(),
        'filtros'      => $request->only(['data', 'recurso_id', 'status', 'busca']),
    ]);
}

// Reserva manual — dono cria agendamento pelo painel
public function store(Request $request): RedirectResponse
{
    $tenant = app('tenant');

    $validated = $request->validate([
        'recurso_id'       => 'required|exists:recursos,id',
        'cliente_nome'     => 'required|string|max:255',
        'cliente_telefone' => 'required|string|max:20',
        'inicio'           => 'required|date',
        'fim'              => 'required|date|after:inicio',
        'observacoes'      => 'nullable|string',
        'notificar_cliente' => 'boolean',
    ]);

    // Garantir que o recurso pertence ao tenant
    $recurso = Recurso::where('tenant_id', $tenant->id)
        ->findOrFail($validated['recurso_id']);

    $agendamento = app(AgendamentoService::class)->criar([
        ...$validated,
        'tenant_id'    => $tenant->id,
        'valor_total'  => $recurso->valor_hora * (
            Carbon::parse($validated['inicio'])->diffInMinutes($validated['fim']) / 60
        ),
        'origem'       => 'manual', // distingue de agendamentos via WhatsApp
    ]);

    // Enviar notificação WhatsApp se solicitado e tenant conectado
    if ($validated['notificar_cliente'] && $tenant->whatsapp_conectado) {
        NotificarAgendamentoJob::dispatch($agendamento, 'criado');
    }

    return redirect()->back()->with('success', 'Agendamento criado com sucesso.');
}
```

### B.5 `Tenant\HorarioController`

```php
// Salvar horários de funcionamento de um recurso
public function store(Request $request, Recurso $recurso): RedirectResponse
{
    // Verificar que recurso pertence ao tenant da sessão
    abort_if($recurso->tenant_id !== app('tenant')->id, 403);

    $request->validate([
        'horarios'              => 'required|array',
        'horarios.*.dia_semana' => 'required|integer|between:0,6',
        'horarios.*.abertura'   => 'required|date_format:H:i',
        'horarios.*.fechamento' => 'required|date_format:H:i|after:horarios.*.abertura',
        'horarios.*.ativo'      => 'boolean',
    ]);

    // Sincronizar: apagar existentes e recriar
    $recurso->horariosFuncionamento()->delete();

    $ativos = collect($request->horarios)->where('ativo', true);
    foreach ($ativos as $horario) {
        $recurso->horariosFuncionamento()->create($horario);
    }

    return redirect()->back()->with('success', 'Horários salvos.');
}
```

---

## Fase C — Migration adicional

```php
// Adicionar coluna 'origem' em agendamentos para distinguir canal
Schema::table('agendamentos', function (Blueprint $table) {
    $table->string('origem')->default('whatsapp')->after('status');
    // 'whatsapp' | 'manual'
    $table->text('observacoes')->nullable()->after('origem');
});
```

---

## Fase D — Frontend

### D.1 Layout com sidebar por papel

```tsx
// resources/js/Layouts/AppLayout.tsx
// Detectar papel do usuário e exibir menu correto:

// Super Admin → menu: Dashboard Geral | Tenants | Todos Agendamentos
// Dono Tenant → menu: Dashboard | Agenda | Agendamentos | Recursos | WhatsApp | Configurações
// Banner de impersonação quando super admin estiver como tenant
```

### D.2 Página `Tenant/Agenda.tsx` — grade visual de horários

```tsx
// Componente central do painel do dono
// Layout: seletor de recurso (tabs ou dropdown) + seletor de semana

interface AgendamentoCalendario {
  id: number;
  title: string;   // nome do cliente
  start: string;
  end: string;
  telefone: string;
  status: 'confirmado' | 'cancelado' | 'concluido';
  valor_total: number | null;
  origem: 'whatsapp' | 'manual';
}

// Comportamento:
// - Grade semanal (Seg-Dom) com colunas de horário (ex: 08:00-22:00)
// - Cada agendamento aparece como bloco colorido no slot correspondente
//   → Verde: confirmado (WhatsApp)
//   → Azul: confirmado (manual)
//   → Cinza: concluído
//   → Vermelho claro: cancelado (opcional exibir)
// - Ao clicar no bloco: modal com detalhes (nome, telefone, horário, valor, origem)
//   → Botões: Concluir | Cancelar | Ligar (link tel:)
// - Ao clicar em slot vazio: abre modal de reserva manual
// - Botão "Hoje" + navegação de semana (← →)
// - Buscar dados via GET /painel/agenda/disponibilidade?recurso_id=X&data_inicio=Y&data_fim=Z
//   sempre que mudar recurso ou semana
```

### D.3 Página `Tenant/Agendamentos/Index.tsx` — lista operacional

```tsx
// Tabela com:
// - Filtros: data, recurso (select), status (select), busca por nome/telefone
// - Colunas: cliente, telefone, recurso, data/hora, duração, valor, origem (badge), status (badge), ações
// - Badge de origem: 🤖 WhatsApp | 📞 Manual
// - Ações por linha: Concluir | Cancelar | Ver na agenda
// - Botão flutuante "Nova reserva manual" → abre modal

// Modal de reserva manual:
// - Campos: Recurso (select), Cliente nome, Telefone, Data, Horário início
// - Ao selecionar recurso + data, busca slots disponíveis via AJAX
// - Dropdown de horário só mostra slots livres
// - Checkbox "Notificar cliente via WhatsApp"
// - Observações (textarea)
```

### D.4 Página `Tenant/Recursos/Index.tsx` — gestão de recursos e horários

```tsx
// Lista de recursos com:
// - Nome, tipo, valor/hora, duração padrão, status (ativo/inativo)
// - Ao expandir: grade de horários de funcionamento por dia da semana

// Grade de horários:
// - 7 linhas (Dom-Sab) com toggle (ativo/inativo) + campos abertura/fechamento
// - Ex: [✓] Segunda  [09:00] até [19:00]
//        [ ] Domingo  (desabilitado)
// - Botão "Salvar horários" envia todos de uma vez
// - Ao desativar dia: campos ficam desabilitados visualmente

// Botão "Nova reserva manual" disponível também aqui
```

### D.5 Página `Tenant/WhatsApp.tsx`

```tsx
// Seção de conexão:
// - Status atual (badge): 🟢 Conectado | 🔴 Desconectado
// - Se desconectado: botão "Conectar WhatsApp"
//   → GET /painel/whatsapp/qrcode retorna base64 da imagem
//   → Exibe QR Code em modal
//   → Polling GET /painel/whatsapp/status a cada 3s
//   → Quando conectar: fecha modal, atualiza badge
// - Se conectado: número conectado + botão "Desconectar"

// Seção de informações:
// - URL do webhook: https://seu-dominio.com/webhook/{slug} (copiável)
// - Instância Evolution: {slug} (copiável)
// - Instruções de uso para o dono
```

---

## Fase E — Seeders atualizados

```php
// Atualizar DatabaseSeeder.php

// 1. Super Admin (você)
User::create([
    'name'           => 'João Pedro',
    'email'          => 'joao@agendou.com',
    'password'       => Hash::make('sua-senha-aqui'),
    'is_super_admin' => true,
]);

// 2. Dono de tenant de exemplo
$donoBarbearia = User::create([
    'name'     => 'Carlos Barbeiro',
    'email'    => 'carlos@barbearia.com',
    'password' => Hash::make('password'),
]);

$barbearia = Tenant::create([...]);
$barbearia->users()->attach($donoBarbearia->id, ['papel' => 'admin']);

// 3. Agendamentos de exemplo com origens mistas
// Alguns com origem='whatsapp', outros com origem='manual'
// Para visualizar a agenda funcionando
```

---

## Checklist de Entrega

### Super Admin
- [ ] Middleware `superadmin` protegendo rotas
- [ ] Dashboard com stats gerais de todos os tenants
- [ ] CRUD de tenants (criar já configura Evolution + webhook)
- [ ] Impersonação de tenant para suporte
- [ ] Banner visível durante impersonação com botão "Sair"

### Painel do Dono
- [ ] Dashboard com stats do próprio tenant
- [ ] Agenda visual semanal por recurso com blocos coloridos por origem/status
- [ ] Modal de detalhes ao clicar em agendamento existente
- [ ] Modal de reserva manual ao clicar em slot vazio
- [ ] Listagem com filtros e busca por nome/telefone
- [ ] Reserva manual com notificação opcional via WhatsApp
- [ ] Gestão de recursos com horários por dia da semana
- [ ] Conexão WhatsApp via QR Code com polling de status
- [ ] Distinção visual entre agendamentos via WhatsApp e manuais
