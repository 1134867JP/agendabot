# Prompt Claude Code — SaaS de Agendamento via WhatsApp

## Visão do Produto

**Agendou** — plataforma SaaS multi-tenant onde estabelecimentos (barbearias, quadras esportivas, estéticas, etc.) conectam seu WhatsApp Business e passam a receber agendamentos automaticamente via bot conversacional com IA.

- O **cliente final** agenda pelo WhatsApp do estabelecimento, sem instalar nada
- O **dono do estabelecimento** acessa o painel web apenas para configurar
- O **bot** usa Claude API (Haiku 4.5) para entender linguagem natural e conduzir o agendamento
- Arquitetura **multi-tenant**: cada estabelecimento é um tenant isolado

### Stack
- **Backend**: Laravel 11
- **Frontend**: React + TypeScript + Inertia.js
- **Banco**: PostgreSQL
- **WhatsApp**: Evolution API (container existente)
- **IA**: Claude API — `claude-haiku-4-5` (Anthropic)
- **Filas**: Laravel Queue (database driver)
- **Estilização**: Tailwind CSS

---

## Fase 1 — Setup

```bash
composer create-project laravel/laravel agendou
cd agendou
composer require laravel/breeze inertia/laravel-inertia tightenco/ziggy guzzlehttp/guzzle
php artisan breeze:install react --typescript
npm install
php artisan queue:table
php artisan migrate
```

### `.env`
```env
APP_NAME=Agendou
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_DATABASE=agendou
DB_USERNAME=postgres
DB_PASSWORD=secret

QUEUE_CONNECTION=database

EVOLUTION_API_URL=http://localhost:8080
EVOLUTION_API_KEY=your-global-api-key

CLAUDE_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-haiku-4-5-20251001
```

---

## Fase 2 — Banco de Dados

### 2.1 `tenants` — os estabelecimentos

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('slug')->unique(); // ex: barbearia-do-joao
    $table->string('tipo_servico'); // barbeiro | quadra | estetica | personalizado
    $table->string('telefone_whatsapp', 20)->nullable();
    $table->string('evolution_instance')->nullable(); // nome da instância na Evolution API
    $table->boolean('whatsapp_conectado')->default(false);
    $table->json('configuracoes')->nullable(); // JSON flexível para configs extras
    $table->boolean('ativo')->default(true);
    $table->timestamps();
});
```

### 2.2 `tenant_users` — donos/admins do tenant

```php
Schema::create('tenant_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('papel')->default('admin'); // admin | operador
    $table->timestamps();
});
```

### 2.3 `recursos` — o que pode ser agendado

```php
// Exemplos: "Quadra de Futsal", "Barbeiro João", "Sala de Massagem"
Schema::create('recursos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('nome');
    $table->string('descricao')->nullable();
    $table->decimal('valor_hora', 8, 2)->nullable();
    $table->integer('duracao_padrao_minutos')->default(60);
    $table->boolean('ativo')->default(true);
    $table->timestamps();
});
```

### 2.4 `horarios_funcionamento`

```php
Schema::create('horarios_funcionamento', function (Blueprint $table) {
    $table->id();
    $table->foreignId('recurso_id')->constrained()->cascadeOnDelete();
    $table->tinyInteger('dia_semana'); // 0=Dom ... 6=Sab
    $table->time('abertura');
    $table->time('fechamento');
    $table->timestamps();
});
```

### 2.5 `agendamentos`

```php
Schema::create('agendamentos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->foreignId('recurso_id')->constrained();
    $table->string('cliente_nome');
    $table->string('cliente_telefone', 20);
    $table->timestampTz('inicio');
    $table->timestampTz('fim');
    $table->string('status')->default('confirmado'); // confirmado | cancelado | concluido
    $table->decimal('valor_total', 8, 2)->nullable();
    $table->timestamps();

    $table->index(['recurso_id', 'inicio', 'fim']);
    $table->index(['tenant_id', 'inicio']);
});
```

### 2.6 `conversas` — estado da conversa no WhatsApp

```php
Schema::create('conversas', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('telefone_cliente', 20);
    $table->string('etapa')->default('idle');
    // idle | escolhendo_recurso | escolhendo_data | escolhendo_horario | confirmando | concluido
    $table->json('contexto')->nullable();
    // Armazena dados parciais: recurso_id escolhido, data escolhida, etc.
    $table->json('historico_mensagens')->nullable();
    // Array de {role: user|assistant, content: string} para o Claude
    $table->timestamp('atualizado_em')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'telefone_cliente']);
});
```

### 2.7 Constraint de exclusão mútua (double-booking)

Execute via migration adicional:
```php
DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
DB::statement("
    ALTER TABLE agendamentos
    ADD CONSTRAINT no_double_booking
    EXCLUDE USING gist (
        recurso_id WITH =,
        tstzrange(inicio, fim) WITH &&
    )
    WHERE (status != 'cancelado')
");
```

---

## Fase 3 — Models

### `Tenant`
```php
class Tenant extends Model
{
    protected $fillable = [
        'nome', 'slug', 'tipo_servico', 'telefone_whatsapp',
        'evolution_instance', 'whatsapp_conectado', 'configuracoes', 'ativo'
    ];
    protected $casts = [
        'configuracoes' => 'array',
        'whatsapp_conectado' => 'boolean',
    ];

    public function recursos(): HasMany { ... }
    public function agendamentos(): HasMany { ... }
    public function conversas(): HasMany { ... }
    public function users(): BelongsToMany { ... }
}
```

### `Recurso`
```php
class Recurso extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'descricao', 'valor_hora', 'duracao_padrao_minutos', 'ativo'];

    public function tenant(): BelongsTo { ... }
    public function horariosFuncionamento(): HasMany { ... }
    public function agendamentos(): HasMany { ... }

    public function slotsDisponiveis(Carbon $data): array
    {
        // 1. Buscar horário do dia da semana
        // 2. Gerar slots com base no duracao_padrao_minutos
        // 3. Filtrar slots já agendados
        // 4. Retornar array [['hora' => '09:00', 'disponivel' => true], ...]
    }
}
```

### `Conversa`
```php
class Conversa extends Model
{
    protected $fillable = ['tenant_id', 'telefone_cliente', 'etapa', 'contexto', 'historico_mensagens', 'atualizado_em'];
    protected $casts = [
        'contexto' => 'array',
        'historico_mensagens' => 'array',
        'atualizado_em' => 'datetime',
    ];

    public function adicionarMensagem(string $role, string $content): void
    {
        $historico = $this->historico_mensagens ?? [];
        $historico[] = ['role' => $role, 'content' => $content];
        // Limitar a últimas 20 mensagens para não explodir tokens
        $this->historico_mensagens = array_slice($historico, -20);
        $this->atualizado_em = now();
        $this->save();
    }
}
```

---

## Fase 4 — Services

### 4.1 `ClaudeService` — cérebro do bot

```php
// app/Services/ClaudeService.php

class ClaudeService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.claude.key');
        $this->model  = config('services.claude.model'); // claude-haiku-4-5-20251001
    }

    /**
     * Processa mensagem do cliente e retorna resposta + próxima etapa
     */
    public function processarMensagem(
        Tenant $tenant,
        Conversa $conversa,
        string $mensagemCliente
    ): array {
        $systemPrompt = $this->montarSystemPrompt($tenant, $conversa);

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => 500,
            'system'     => $systemPrompt,
            'messages'   => array_merge(
                $conversa->historico_mensagens ?? [],
                [['role' => 'user', 'content' => $mensagemCliente]]
            ),
        ]);

        $content = $response->json('content.0.text');

        // Claude deve responder em JSON estruturado
        $parsed = json_decode($content, true);

        return [
            'resposta'       => $parsed['resposta'],
            'proxima_etapa'  => $parsed['proxima_etapa'],
            'dados_extraidos' => $parsed['dados'] ?? [],
        ];
    }

    private function montarSystemPrompt(Tenant $tenant, Conversa $conversa): string
    {
        $recursos = $tenant->recursos()->where('ativo', true)->get()
            ->map(fn($r) => "- ID {$r->id}: {$r->nome} (R$ {$r->valor_hora}/h)")
            ->join("\n");

        $contexto = json_encode($conversa->contexto ?? []);
        $etapaAtual = $conversa->etapa;

        return <<<PROMPT
Você é o assistente de agendamento de "{$tenant->nome}", um(a) {$tenant->tipo_servico}.
Seu objetivo é ajudar o cliente a fazer um agendamento de forma rápida e simpática.

RECURSOS DISPONÍVEIS:
{$recursos}

ETAPA ATUAL DA CONVERSA: {$etapaAtual}
CONTEXTO PARCIAL: {$contexto}

FLUXO:
1. idle → saudação e perguntar o que deseja
2. escolhendo_recurso → mostrar recursos disponíveis e capturar escolha
3. escolhendo_data → sugerir dias da semana com disponibilidade e capturar data
4. escolhendo_horario → listar slots disponíveis e capturar horário
5. confirmando → resumir e pedir nome para confirmar
6. concluido → confirmar agendamento com detalhes

REGRAS:
- Seja breve e direto (máximo 3 linhas por mensagem)
- Use emojis moderadamente
- Se o cliente mencionar recurso + data + horário na mesma mensagem, avance várias etapas
- NUNCA invente horários disponíveis; use apenas os fornecidos no contexto
- Se não entender, peça gentilmente para repetir

RESPONDA SEMPRE EM JSON com esta estrutura:
{
  "resposta": "mensagem para o cliente",
  "proxima_etapa": "idle|escolhendo_recurso|escolhendo_data|escolhendo_horario|confirmando|concluido",
  "dados": {
    "recurso_id": null,
    "data": null,
    "horario": null,
    "nome_cliente": null
  }
}
PROMPT;
    }
}
```

### 4.2 `BotService` — orquestrador do fluxo

```php
// app/Services/BotService.php

class BotService
{
    public function __construct(
        private ClaudeService $claude,
        private AgendamentoService $agendamento,
        private EvolutionApiService $evolution,
    ) {}

    public function processarWebhook(Tenant $tenant, string $telefone, string $mensagem): void
    {
        // 1. Buscar ou criar conversa
        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $tenant->id, 'telefone_cliente' => $telefone],
            ['etapa' => 'idle', 'historico_mensagens' => []]
        );

        // 2. Injetar slots disponíveis no contexto se na etapa correta
        if ($conversa->etapa === 'escolhendo_horario' && isset($conversa->contexto['recurso_id'], $conversa->contexto['data'])) {
            $this->injetarSlotsNoContexto($conversa);
        }

        // 3. Processar com Claude
        $resultado = $this->claude->processarMensagem($tenant, $conversa, $mensagem);

        // 4. Atualizar histórico e etapa
        $conversa->adicionarMensagem('user', $mensagem);
        $conversa->adicionarMensagem('assistant', $resultado['resposta']);
        $conversa->etapa = $resultado['proxima_etapa'];

        // 5. Mesclar dados extraídos no contexto
        $contexto = $conversa->contexto ?? [];
        foreach ($resultado['dados_extraidos'] as $key => $value) {
            if ($value !== null) $contexto[$key] = $value;
        }
        $conversa->contexto = $contexto;
        $conversa->save();

        // 6. Se concluído, criar agendamento
        if ($resultado['proxima_etapa'] === 'concluido') {
            $this->finalizarAgendamento($conversa, $tenant);
        }

        // 7. Enviar resposta ao cliente via WhatsApp
        $this->evolution->enviarMensagem(
            $tenant->evolution_instance,
            $telefone,
            $resultado['resposta']
        );
    }

    private function injetarSlotsNoContexto(Conversa $conversa): void
    {
        $recurso = Recurso::find($conversa->contexto['recurso_id']);
        $data = Carbon::parse($conversa->contexto['data']);
        $slots = $recurso->slotsDisponiveis($data);
        $disponiveis = collect($slots)->where('disponivel', true)->pluck('hora')->join(', ');

        $contexto = $conversa->contexto;
        $contexto['slots_disponiveis'] = $disponiveis;
        $conversa->contexto = $contexto;
        $conversa->save();
    }

    private function finalizarAgendamento(Conversa $conversa, Tenant $tenant): void
    {
        $ctx = $conversa->contexto;

        $recurso = Recurso::find($ctx['recurso_id']);
        $inicio  = Carbon::parse("{$ctx['data']} {$ctx['horario']}");
        $fim     = $inicio->copy()->addMinutes($recurso->duracao_padrao_minutos);

        $this->agendamento->criar([
            'tenant_id'        => $tenant->id,
            'recurso_id'       => $ctx['recurso_id'],
            'cliente_nome'     => $ctx['nome_cliente'],
            'cliente_telefone' => $conversa->telefone_cliente,
            'inicio'           => $inicio,
            'fim'              => $fim,
            'valor_total'      => $recurso->valor_hora * ($recurso->duracao_padrao_minutos / 60),
        ]);

        // Resetar conversa para próximo agendamento
        $conversa->update(['etapa' => 'idle', 'contexto' => null]);
    }
}
```

### 4.3 `EvolutionApiService`

```php
// app/Services/EvolutionApiService.php

class EvolutionApiService
{
    private string $baseUrl;
    private string $globalApiKey;

    public function __construct()
    {
        $this->baseUrl      = config('services.evolution.url');
        $this->globalApiKey = config('services.evolution.key');
    }

    public function enviarMensagem(string $instance, string $telefone, string $mensagem): bool
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/message/sendText/{$instance}", [
                'number' => $telefone,
                'text'   => $mensagem,
            ]);

        return $response->successful();
    }

    public function criarInstancia(string $instanceName): array
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/instance/create", [
                'instanceName' => $instanceName,
                'qrcode'       => true,
            ]);

        return $response->json();
    }

    public function obterQrCode(string $instance): string|null
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->get("{$this->baseUrl}/instance/connect/{$instance}");

        return $response->json('base64') ?? null;
    }

    public function statusInstancia(string $instance): string
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->get("{$this->baseUrl}/instance/fetchInstances");

        $instancias = collect($response->json());
        $found = $instancias->firstWhere('instance.instanceName', $instance);

        return $found['instance']['state'] ?? 'desconhecido';
        // open = conectado | close = desconectado
    }

    public function configurarWebhook(string $instance, string $webhookUrl): bool
    {
        $response = Http::withHeaders(['apikey' => $this->globalApiKey])
            ->post("{$this->baseUrl}/webhook/set/{$instance}", [
                'url'     => $webhookUrl,
                'enabled' => true,
                'events'  => ['MESSAGES_UPSERT'],
            ]);

        return $response->successful();
    }
}
```

### 4.4 `AgendamentoService`

```php
class AgendamentoService
{
    public function criar(array $dados): Agendamento
    {
        return DB::transaction(function () use ($dados) {
            // Trava para evitar race condition
            DB::select('SELECT pg_advisory_xact_lock(?)', [$dados['recurso_id']]);

            // Verificar conflito manualmente antes da constraint
            $conflito = Agendamento::where('recurso_id', $dados['recurso_id'])
                ->where('status', '!=', 'cancelado')
                ->where('inicio', '<', $dados['fim'])
                ->where('fim', '>', $dados['inicio'])
                ->exists();

            if ($conflito) {
                throw new HorarioIndisponivelException('Horário não disponível.');
            }

            return Agendamento::create($dados);
        });
    }

    public function cancelar(Agendamento $agendamento): void
    {
        $agendamento->update(['status' => 'cancelado']);
    }
}
```

---

## Fase 5 — Webhook Controller

```php
// app/Http/Controllers/WebhookController.php

class WebhookController extends Controller
{
    public function __construct(private BotService $bot) {}

    public function handle(Request $request, string $tenantSlug): Response
    {
        // Identificar tenant pelo slug na URL
        $tenant = Tenant::where('slug', $tenantSlug)
            ->where('ativo', true)
            ->firstOrFail();

        $data = $request->json()->all();

        // Ignorar mensagens que não sejam do tipo texto simples
        $tipo = data_get($data, 'data.messageType');
        if ($tipo !== 'conversation' && $tipo !== 'extendedTextMessage') {
            return response('ok');
        }

        // Ignorar mensagens enviadas pelo próprio bot
        if (data_get($data, 'data.key.fromMe')) {
            return response('ok');
        }

        $telefone  = data_get($data, 'data.key.remoteJid'); // ex: 5554999999999@s.whatsapp.net
        $mensagem  = data_get($data, 'data.message.conversation')
                  ?? data_get($data, 'data.message.extendedTextMessage.text');

        // Processar de forma assíncrona
        ProcessarMensagemJob::dispatch($tenant, $telefone, $mensagem);

        return response('ok');
    }
}
```

---

## Fase 6 — Jobs

### `ProcessarMensagemJob`

```php
class ProcessarMensagemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
    ) {}

    public function handle(BotService $bot): void
    {
        $bot->processarWebhook($this->tenant, $this->telefone, $this->mensagem);
    }
}
```

---

## Fase 7 — Routes

```php
// routes/web.php

// Webhook público (sem auth) — URL por tenant
Route::post('/webhook/{tenantSlug}', [WebhookController::class, 'handle'])
    ->name('webhook');

// Painel web (autenticado)
Route::middleware('auth')->group(function () {

    // Selecionar tenant ativo
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/tenants/{tenant}/selecionar', [TenantController::class, 'selecionar']);

    // Configuração do tenant
    Route::middleware('tenant')->group(function () {
        Route::get('/configuracoes', [ConfiguracaoController::class, 'index']);
        Route::put('/configuracoes', [ConfiguracaoController::class, 'update']);

        // WhatsApp
        Route::get('/whatsapp/qrcode', [WhatsAppController::class, 'qrcode']);
        Route::get('/whatsapp/status', [WhatsAppController::class, 'status']);
        Route::post('/whatsapp/conectar', [WhatsAppController::class, 'conectar']);

        // Recursos
        Route::resource('recursos', RecursoController::class);

        // Horários de funcionamento
        Route::post('recursos/{recurso}/horarios', [HorarioController::class, 'store']);
        Route::put('horarios/{horario}', [HorarioController::class, 'update']);
        Route::delete('horarios/{horario}', [HorarioController::class, 'destroy']);

        // Agendamentos
        Route::get('agendamentos', [AgendamentoController::class, 'index']);
        Route::patch('agendamentos/{agendamento}/cancelar', [AgendamentoController::class, 'cancelar']);
    });
});

// Super admin (gerenciar tenants)
Route::middleware(['auth', 'superadmin'])->prefix('admin')->group(function () {
    Route::resource('tenants', Admin\TenantController::class);
});
```

---

## Fase 8 — Middleware `tenant`

Identifica e injeta o tenant ativo na sessão:

```php
// app/Http/Middleware/EnsureHasTenant.php

public function handle(Request $request, Closure $next): Response
{
    $tenantId = session('tenant_id');

    if (!$tenantId) {
        return redirect()->route('dashboard')->with('erro', 'Selecione um estabelecimento.');
    }

    $tenant = Tenant::find($tenantId);

    if (!$tenant || !$request->user()->tenants->contains($tenant)) {
        abort(403);
    }

    app()->instance('tenant', $tenant);
    View::share('currentTenant', $tenant);

    return $next($request);
}
```

---

## Fase 9 — Frontend React + TypeScript

### 9.1 Tipos base

```typescript
// resources/js/types/index.d.ts

export type TipoServico = 'barbeiro' | 'quadra' | 'estetica' | 'personalizado';
export type StatusWhatsApp = 'open' | 'close' | 'desconhecido';

export interface Tenant {
  id: number;
  nome: string;
  slug: string;
  tipo_servico: TipoServico;
  whatsapp_conectado: boolean;
}

export interface Recurso {
  id: number;
  nome: string;
  descricao: string | null;
  valor_hora: number;
  duracao_padrao_minutos: number;
  ativo: boolean;
  horarios_funcionamento: HorarioFuncionamento[];
}

export interface HorarioFuncionamento {
  id: number;
  dia_semana: number; // 0-6
  abertura: string;
  fechamento: string;
}

export interface Agendamento {
  id: number;
  recurso: Recurso;
  cliente_nome: string;
  cliente_telefone: string;
  inicio: string;
  fim: string;
  status: 'confirmado' | 'cancelado' | 'concluido';
  valor_total: number | null;
}
```

### 9.2 Páginas

```
resources/js/Pages/
├── Dashboard.tsx
│   └── Lista de tenants do usuário + botão criar novo
│
├── Configuracoes/
│   └── Index.tsx
│       ├── Aba: Dados gerais (nome, tipo de serviço)
│       ├── Aba: WhatsApp (QR Code, status da conexão)
│       └── Aba: Recursos (lista + CRUD inline)
│
└── Agendamentos/
    └── Index.tsx
        ├── Tabela com filtro por data e status
        └── Ação de cancelar
```

### 9.3 Componente `WhatsAppConector`

```tsx
// resources/js/Components/WhatsAppConector.tsx

interface Props {
  status: StatusWhatsApp;
  onConectar: () => void;
}

// Comportamento:
// - Se status === 'open': exibe badge verde "Conectado" + botão "Desconectar"
// - Se status === 'close': exibe botão "Conectar WhatsApp"
//   → ao clicar: GET /whatsapp/qrcode → exibe imagem do QR Code em modal
//   → polling a cada 3s em GET /whatsapp/status até status === 'open'
//   → fecha modal e atualiza estado
```

### 9.4 Componente `RecursoForm`

```tsx
// Formulário de criação/edição de recurso
// Campos: nome, descrição, valor/hora, duração padrão
// + seção de horários de funcionamento por dia da semana (toggle + abertura/fechamento)
// Ao salvar, usa router.post ou router.put do Inertia
```

### 9.5 Componente `AgendamentosTable`

```tsx
// Tabela responsiva com:
// - Filtro de data (date picker)
// - Colunas: cliente, recurso, horário, status, valor, ação
// - Ação de cancelar com confirmação
// - Badge de status colorido
```

---

## Fase 10 — Seeders

```php
// DatabaseSeeder.php

// 1. Super admin
$superAdmin = User::create([
    'name'     => 'Super Admin',
    'email'    => 'admin@agendou.com',
    'password' => Hash::make('password'),
    'is_super_admin' => true,
]);

// 2. Tenant: Barbearia
$barbearia = Tenant::create([
    'nome'              => 'Barbearia do João',
    'slug'              => 'barbearia-do-joao',
    'tipo_servico'      => 'barbeiro',
    'evolution_instance' => 'barbearia-joao',
]);
$barbearia->users()->attach($superAdmin->id, ['papel' => 'admin']);

// Recursos da barbearia
$barbeiros = ['João', 'Pedro', 'Lucas'];
foreach ($barbeiros as $nome) {
    $recurso = $barbearia->recursos()->create([
        'nome' => "Barbeiro {$nome}",
        'valor_hora' => 60,
        'duracao_padrao_minutos' => 30,
    ]);
    // Seg-Sab, 09:00-19:00
    for ($dia = 1; $dia <= 6; $dia++) {
        $recurso->horariosFuncionamento()->create([
            'dia_semana' => $dia,
            'abertura'   => '09:00',
            'fechamento' => '19:00',
        ]);
    }
}

// 3. Tenant: Quadras
$quadras = Tenant::create([
    'nome'         => 'Arena Sports',
    'slug'         => 'arena-sports',
    'tipo_servico' => 'quadra',
    'evolution_instance' => 'arena-sports',
]);

foreach (['Quadra de Futsal', 'Beach Tennis', 'Padel'] as $nome) {
    $recurso = $quadras->recursos()->create([
        'nome' => $nome,
        'valor_hora' => 120,
        'duracao_padrao_minutos' => 60,
    ]);
    // Todos os dias, 07:00-22:00
    for ($dia = 0; $dia <= 6; $dia++) {
        $recurso->horariosFuncionamento()->create([
            'dia_semana' => $dia,
            'abertura'   => '07:00',
            'fechamento' => '22:00',
        ]);
    }
}
```

---

## Fase 11 — Configurações dos Services

`config/services.php`:
```php
'claude' => [
    'key'   => env('CLAUDE_API_KEY'),
    'model' => env('CLAUDE_MODEL', 'claude-haiku-4-5-20251001'),
],

'evolution' => [
    'url' => env('EVOLUTION_API_URL'),
    'key' => env('EVOLUTION_API_KEY'),
],
```

`AppServiceProvider::register()`:
```php
$this->app->singleton(ClaudeService::class);
$this->app->singleton(EvolutionApiService::class);
$this->app->singleton(BotService::class);
$this->app->singleton(AgendamentoService::class);
```

---

## Fase 12 — Fluxo de Onboarding de um Novo Tenant

1. Dono se cadastra no Agendou (auth normal)
2. Cria um tenant: informa **nome** e **tipo de serviço**
3. Sistema cria automaticamente a instância na Evolution API (`{slug}-instance`)
4. Configura automaticamente o webhook: `POST /webhook/{slug}`
5. Dono acessa a aba WhatsApp → escaneia QR Code
6. Cadastra seus recursos (barbeiros / quadras / etc.) com horários
7. Pronto — clientes já podem agendar pelo WhatsApp

---

## Fase 13 — Testes

```
tests/Feature/
├── BotFluxoTest.php
│   ├── test_bot_conduz_agendamento_completo_barbearia
│   ├── test_bot_conduz_agendamento_completo_quadra
│   ├── test_bot_avanca_etapas_com_mensagem_completa
│   └── test_bot_rejeita_horario_ocupado
│
├── AgendamentoTest.php
│   ├── test_cria_agendamento_com_sucesso
│   ├── test_rejeita_double_booking
│   └── test_cancela_agendamento
│
└── TenantTest.php
    ├── test_usuario_acessa_apenas_proprio_tenant
    └── test_webhook_identifica_tenant_correto
```

---

## Checklist de Entrega

### Infraestrutura
- [ ] Migrations rodando com constraint de exclusão mútua
- [ ] Queue worker configurado (`php artisan queue:work`)
- [ ] Evolution API container acessível

### Backend
- [ ] Multi-tenant com isolamento por sessão
- [ ] Webhook recebendo e processando mensagens
- [ ] Claude API integrada com system prompt dinâmico por tenant
- [ ] Fluxo conversacional completo (idle → concluído)
- [ ] Agendamento criado com proteção de double-booking
- [ ] Instância Evolution criada e webhook configurado automaticamente

### Painel Web
- [ ] Onboarding: criar tenant + tipo de serviço
- [ ] Conexão WhatsApp via QR Code com polling de status
- [ ] CRUD de recursos com horários de funcionamento
- [ ] Listagem de agendamentos com filtro e cancelamento

### Testes
- [ ] Fluxo completo de bot passando
- [ ] Double-booking rejeitado
- [ ] Isolamento de tenant validado

---

## Notas Finais

- **Custo Claude API estimado**: com Haiku 4.5 a $1/MTok input e $5/MTok output, uma conversa completa de ~800 tokens custa ~$0,003. Para 1.000 agendamentos/mês por tenant: ~$3/mês.
- **Escalabilidade**: para múltiplos tenants com alto volume, usar `prompt caching` no system prompt (mesmo prompt por tenant = 90% de desconto nas leituras repetidas).
- **Segurança**: nunca expor `CLAUDE_API_KEY` nem `EVOLUTION_API_KEY` no frontend. Toda comunicação via backend.
- **Timezone**: armazenar tudo em UTC no banco (`timestamptz`), converter para o timezone do tenant apenas na exibição.
- **Expiração de conversa**: criar um scheduled job que reseta conversas sem atividade há mais de 30 minutos (`etapa = 'idle'`), evitando estados corrompidos.
