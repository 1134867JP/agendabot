# Agendou v2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Evoluir o Agendou do esquema v1 (recursos + horarios_funcionamento) para o v2 genérico (profissionais + serviços + clientes + mensagens + bot configurável com transfer-to-human).

**Architecture:** Additive migrations preservam o que existe. Novos models coexistem com os antigos. BotService é reescrito para usar a nova estrutura de conversas com tabela `mensagens` separada. ClaudeService é substituído pelo ClaudeAgentService com system prompt dinâmico por tenant.

**Tech Stack:** Laravel 11, PHP 8.3, PostgreSQL, React + TypeScript + Inertia.js, Tailwind CSS, Claude Haiku 4.5

## Global Constraints

- Modelo Claude: `claude-haiku-4-5-20251001`
- Timeout de chamadas Claude: 30s, retry em 529
- Jobs: `$tries = 3`, `$backoff = [30, 60, 120]`
- Bot não processa mensagem se `conversas.status` = `aguardando_humano` ou `em_atendimento_humano`
- Nunca expor `CLAUDE_API_KEY` ou `EVOLUTION_API_KEY` no frontend
- `evolution_message_id` deve ser verificado para evitar duplicatas
- Todas as migrations são aditivas — não dropar tabelas existentes

## O Que Já Existe (NÃO TOCAR)

- Setup Laravel 11 + Breeze/React/TypeScript
- Auth (login/register/logout/Breeze)
- Middleware: `tenant`, `subscription`, `superadmin`
- `AsaasService` + `AsaasWebhookController`
- `EvolutionApiService` (manter, só adicionar método `desconectar`)
- `CreateEvolutionInstanceJob`
- Super Admin panel completo (SuperAdmin/*)
- Landing page (Home.tsx, Precos.tsx)
- `EnviarLembretesJob` (será substituído por nova versão)

---

## Task 1: Migrations — Novos campos em tenants

**Files:**
- Create: `database/migrations/2026_06_22_100001_add_bot_config_to_tenants_table.php`

**Interfaces:**
- Produz: colunas `ramo_negocio`, `descricao_negocio`, `cidade`, `endereco`, `horarios_funcionamento` (json), `nome_agente`, `tom_voz` (enum), `instrucoes_extras`, `bot_ativo`

- [ ] **Step 1: Criar migration**

```php
<?php
// database/migrations/2026_06_22_100001_add_bot_config_to_tenants_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('ramo_negocio')->nullable()->after('slug');
            $table->text('descricao_negocio')->nullable()->after('ramo_negocio');
            $table->string('cidade')->nullable()->after('descricao_negocio');
            $table->string('endereco')->nullable()->after('cidade');
            $table->json('horarios_funcionamento')->nullable()->after('endereco');
            $table->string('nome_agente')->default('Bia')->after('horarios_funcionamento');
            $table->enum('tom_voz', ['formal', 'semiformal', 'descontraido'])->default('semiformal')->after('nome_agente');
            $table->text('instrucoes_extras')->nullable()->after('tom_voz');
            $table->boolean('bot_ativo')->default(true)->after('instrucoes_extras');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'ramo_negocio', 'descricao_negocio', 'cidade', 'endereco',
                'horarios_funcionamento', 'nome_agente', 'tom_voz', 'instrucoes_extras', 'bot_ativo',
            ]);
        });
    }
};
```

- [ ] **Step 2: Rodar migration**

```bash
php artisan migrate
```

Esperado: `Migrating: 2026_06_22_100001_add_bot_config_to_tenants_table` → `Migrated`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_22_100001_add_bot_config_to_tenants_table.php
git commit -m "feat: add bot config columns to tenants table"
```

---

## Task 2: Migrations — Novas tabelas (profissionais, serviços, extras, clientes, mensagens)

**Files:**
- Create: `database/migrations/2026_06_22_100002_create_profissionais_table.php`
- Create: `database/migrations/2026_06_22_100003_create_horarios_profissional_table.php`
- Create: `database/migrations/2026_06_22_100004_create_servicos_table.php`
- Create: `database/migrations/2026_06_22_100005_create_opcoes_extras_table.php`
- Create: `database/migrations/2026_06_22_100006_create_clientes_table.php`
- Create: `database/migrations/2026_06_22_100007_create_mensagens_table.php`

**Interfaces:**
- Produz: tabelas `profissionais`, `horarios_profissional`, `servicos`, `opcoes_extras`, `clientes`, `mensagens`

- [ ] **Step 1: Migration profissionais**

```php
<?php
// database/migrations/2026_06_22_100002_create_profissionais_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profissionais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->json('especialidades')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('profissionais'); }
};
```

- [ ] **Step 2: Migration horarios_profissional**

```php
<?php
// database/migrations/2026_06_22_100003_create_horarios_profissional_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('horarios_profissional', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profissional_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('dia_semana'); // 1=seg..6=sab, 0=dom
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->integer('duracao_slot')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('horarios_profissional'); }
};
```

- [ ] **Step 3: Migration servicos**

```php
<?php
// database/migrations/2026_06_22_100004_create_servicos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('valor_min', 8, 2)->nullable();
            $table->decimal('valor_max', 8, 2)->nullable();
            $table->integer('duracao_minutos')->default(30);
            $table->boolean('requer_avaliacao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('servicos'); }
};
```

- [ ] **Step 4: Migration opcoes_extras**

```php
<?php
// database/migrations/2026_06_22_100005_create_opcoes_extras_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opcoes_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['convenio', 'pagamento', 'outro']);
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('opcoes_extras'); }
};
```

- [ ] **Step 5: Migration clientes**

```php
<?php
// database/migrations/2026_06_22_100006_create_clientes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('telefone', 30);
            $table->string('cpf', 14)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'telefone']);
            $table->index(['tenant_id', 'telefone']);
        });
    }

    public function down(): void { Schema::dropIfExists('clientes'); }
};
```

- [ ] **Step 6: Migration mensagens**

```php
<?php
// database/migrations/2026_06_22_100007_create_mensagens_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversa_id')->constrained()->cascadeOnDelete();
            $table->enum('remetente', ['cliente', 'bot', 'humano']);
            $table->text('conteudo');
            $table->string('evolution_message_id')->nullable()->unique();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'enviada_em']);
        });
    }

    public function down(): void { Schema::dropIfExists('mensagens'); }
};
```

- [ ] **Step 7: Rodar todas as migrations**

```bash
php artisan migrate
```

Esperado: 6 novas tabelas criadas sem erros.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_22_10000[2-7]_*.php
git commit -m "feat: create profissionais, servicos, clientes, mensagens tables"
```

---

## Task 3: Migration — Refatorar conversas e agendamentos para v2

**Files:**
- Create: `database/migrations/2026_06_22_100008_refactor_conversas_v2.php`
- Create: `database/migrations/2026_06_22_100009_refactor_agendamentos_v2.php`

**Interfaces:**
- `conversas`: adiciona `cliente_id`, `status` (enum), `ultima_mensagem_em`
- `agendamentos`: adiciona `cliente_id`, `profissional_id`, `servico_id`, `duracao_minutos`, `opcao_extra`, `data_hora`

- [ ] **Step 1: Migration refactor conversas**

```php
<?php
// database/migrations/2026_06_22_100008_refactor_conversas_v2.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('tenant_id')
                ->constrained('clientes')->nullOnDelete();
            $table->enum('status_v2', ['ativa', 'aguardando_humano', 'em_atendimento_humano', 'encerrada'])
                ->default('ativa')->after('telefone_cliente');
            $table->timestamp('ultima_mensagem_em')->nullable()->after('status_v2');

            $table->index(['tenant_id', 'status_v2']);
        });
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropColumn(['status_v2', 'ultima_mensagem_em']);
        });
    }
};
```

- [ ] **Step 2: Migration refactor agendamentos**

```php
<?php
// database/migrations/2026_06_22_100009_refactor_agendamentos_v2.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('tenant_id')
                ->constrained('clientes')->nullOnDelete();
            $table->foreignId('profissional_id')->nullable()->after('cliente_id')
                ->constrained('profissionais')->nullOnDelete();
            $table->foreignId('servico_id')->nullable()->after('profissional_id')
                ->constrained('servicos')->nullOnDelete();
            $table->integer('duracao_minutos')->default(30)->after('servico_id');
            $table->string('opcao_extra')->nullable()->after('duracao_minutos');
            $table->timestamp('data_hora')->nullable()->after('opcao_extra');

            $table->index(['profissional_id', 'data_hora']);
            $table->index(['tenant_id', 'data_hora', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('agendamentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropConstrainedForeignId('profissional_id');
            $table->dropConstrainedForeignId('servico_id');
            $table->dropColumn(['duracao_minutos', 'opcao_extra', 'data_hora']);
        });
    }
};
```

- [ ] **Step 3: Rodar migrations**

```bash
php artisan migrate
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_22_10000[89]_*.php
git commit -m "feat: add v2 columns to conversas and agendamentos"
```

---

## Task 4: Novos Eloquent Models

**Files:**
- Create: `app/Models/Profissional.php`
- Create: `app/Models/HorarioProfissional.php`
- Create: `app/Models/Servico.php`
- Create: `app/Models/OpcaoExtra.php`
- Create: `app/Models/Cliente.php`
- Create: `app/Models/Mensagem.php`
- Modify: `app/Models/Tenant.php`
- Modify: `app/Models/Conversa.php`
- Modify: `app/Models/Agendamento.php`

**Interfaces:**
- `Profissional::slotsDisponiveis(Carbon $data): array` — retorna slots livres para o dia

- [ ] **Step 1: Model Profissional**

```php
<?php
// app/Models/Profissional.php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profissional extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'especialidades', 'ativo'];
    protected $casts = ['especialidades' => 'array', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function horarios(): HasMany { return $this->hasMany(HorarioProfissional::class); }
    public function agendamentos(): HasMany { return $this->hasMany(Agendamento::class); }

    public function slotsDisponiveis(Carbon $data): array
    {
        $diaSemana = (int) $data->format('N'); // 1=seg..7=dom → usar 1..6 para seg..sab
        $horario = $this->horarios()->where('dia_semana', $diaSemana)->first();

        if (! $horario) {
            return [];
        }

        $slots = [];
        $inicio = Carbon::parse($data->format('Y-m-d') . ' ' . $horario->hora_inicio);
        $fim    = Carbon::parse($data->format('Y-m-d') . ' ' . $horario->hora_fim);
        $duracao = $horario->duracao_slot;

        $agendados = $this->agendamentos()
            ->whereDate('data_hora', $data)
            ->whereNotIn('status', ['cancelado'])
            ->pluck('data_hora')
            ->map(fn ($dt) => Carbon::parse($dt)->format('H:i'))
            ->toArray();

        $cursor = $inicio->copy();
        while ($cursor->copy()->addMinutes($duracao)->lte($fim)) {
            $hora = $cursor->format('H:i');
            $slots[] = ['hora' => $hora, 'disponivel' => ! in_array($hora, $agendados)];
            $cursor->addMinutes($duracao);
        }

        return $slots;
    }
}
```

- [ ] **Step 2: Model HorarioProfissional**

```php
<?php
// app/Models/HorarioProfissional.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorarioProfissional extends Model
{
    protected $table = 'horarios_profissional';
    protected $fillable = ['profissional_id', 'dia_semana', 'hora_inicio', 'hora_fim', 'duracao_slot'];

    public function profissional(): BelongsTo { return $this->belongsTo(Profissional::class); }
}
```

- [ ] **Step 3: Model Servico**

```php
<?php
// app/Models/Servico.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Servico extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'descricao', 'valor_min', 'valor_max', 'duracao_minutos', 'requer_avaliacao', 'ativo'];
    protected $casts = ['requer_avaliacao' => 'boolean', 'ativo' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
```

- [ ] **Step 4: Model OpcaoExtra**

```php
<?php
// app/Models/OpcaoExtra.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcaoExtra extends Model
{
    protected $fillable = ['tenant_id', 'tipo', 'nome', 'ativo'];
    protected $casts = ['ativo' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
```

- [ ] **Step 5: Model Cliente**

```php
<?php
// app/Models/Cliente.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = ['tenant_id', 'nome', 'telefone', 'cpf', 'data_nascimento', 'observacoes'];
    protected $casts = ['data_nascimento' => 'date'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function agendamentos(): HasMany { return $this->hasMany(Agendamento::class); }
    public function conversas(): HasMany { return $this->hasMany(Conversa::class); }
}
```

- [ ] **Step 6: Model Mensagem**

```php
<?php
// app/Models/Mensagem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensagem extends Model
{
    protected $fillable = ['conversa_id', 'remetente', 'conteudo', 'evolution_message_id', 'enviada_em'];
    protected $casts = ['enviada_em' => 'datetime'];

    public function conversa(): BelongsTo { return $this->belongsTo(Conversa::class); }
}
```

- [ ] **Step 7: Atualizar Tenant.php**

Adicionar ao `$fillable`, `$casts` e relacionamentos:

```php
// Em app/Models/Tenant.php — substituir o conteúdo completo:
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'nome', 'slug', 'tipo_servico', 'tipo_servico_personalizado',
        'telefone_whatsapp', 'evolution_instance', 'whatsapp_conectado',
        'configuracoes', 'ativo',
        'subscription_status', 'trial_ends_at', 'subscription_ends_at',
        'asaas_customer_id', 'asaas_subscription_id', 'plano',
        // v2
        'ramo_negocio', 'descricao_negocio', 'cidade', 'endereco',
        'horarios_funcionamento', 'nome_agente', 'tom_voz', 'instrucoes_extras', 'bot_ativo',
    ];

    protected $casts = [
        'configuracoes'          => 'array',
        'horarios_funcionamento' => 'array',
        'whatsapp_conectado'     => 'boolean',
        'ativo'                  => 'boolean',
        'bot_ativo'              => 'boolean',
        'trial_ends_at'          => 'datetime',
        'subscription_ends_at'   => 'datetime',
    ];

    public function recursos(): HasMany { return $this->hasMany(Recurso::class); }
    public function agendamentos(): HasMany { return $this->hasMany(Agendamento::class); }
    public function conversas(): HasMany { return $this->hasMany(Conversa::class); }
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('papel')->withTimestamps();
    }
    // v2
    public function profissionais(): HasMany { return $this->hasMany(Profissional::class); }
    public function servicos(): HasMany { return $this->hasMany(Servico::class); }
    public function opcoes_extras(): HasMany { return $this->hasMany(OpcaoExtra::class); }
    public function clientes(): HasMany { return $this->hasMany(Cliente::class); }
}
```

- [ ] **Step 8: Atualizar Conversa.php**

```php
<?php
// app/Models/Conversa.php — substituir conteúdo completo
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversa extends Model
{
    protected $fillable = [
        'tenant_id', 'cliente_id', 'telefone_cliente',
        'etapa', 'contexto', 'historico_mensagens', 'atualizado_em',
        // v2
        'status_v2', 'ultima_mensagem_em',
    ];

    protected $casts = [
        'contexto'            => 'array',
        'historico_mensagens' => 'array',
        'atualizado_em'       => 'datetime',
        'ultima_mensagem_em'  => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function mensagens(): HasMany { return $this->hasMany(Mensagem::class); }

    // Método legado — mantido para compatibilidade com BotService antigo
    public function adicionarMensagem(string $role, string $content): void
    {
        $historico = $this->historico_mensagens ?? [];
        $historico[] = ['role' => $role, 'content' => $content];
        $this->historico_mensagens = array_slice($historico, -20);
        $this->atualizado_em = now();
        $this->save();
    }

    // v2: salvar mensagem na tabela mensagens
    public function registrarMensagem(string $remetente, string $conteudo, ?string $evolutionId = null): Mensagem
    {
        $mensagem = $this->mensagens()->create([
            'remetente'           => $remetente,
            'conteudo'            => $conteudo,
            'evolution_message_id' => $evolutionId,
            'enviada_em'          => now(),
        ]);

        $this->update(['ultima_mensagem_em' => now()]);

        return $mensagem;
    }
}
```

- [ ] **Step 9: Atualizar Agendamento.php**

```php
<?php
// app/Models/Agendamento.php — substituir conteúdo completo
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Agendamento extends Model
{
    protected $fillable = [
        'tenant_id', 'recurso_id', 'cliente_nome', 'cliente_telefone',
        'inicio', 'fim', 'status', 'origem', 'observacoes', 'valor_total', 'lembrete_enviado',
        // v2
        'cliente_id', 'profissional_id', 'servico_id', 'duracao_minutos', 'opcao_extra', 'data_hora',
    ];

    protected $casts = [
        'inicio'           => 'datetime',
        'fim'              => 'datetime',
        'data_hora'        => 'datetime',
        'valor_total'      => 'decimal:2',
        'lembrete_enviado' => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function recurso(): BelongsTo { return $this->belongsTo(Recurso::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function profissional(): BelongsTo { return $this->belongsTo(Profissional::class); }
    public function servico(): BelongsTo { return $this->belongsTo(Servico::class); }
}
```

- [ ] **Step 10: Commit**

```bash
git add app/Models/
git commit -m "feat: add v2 models (Profissional, Servico, Cliente, Mensagem) and update existing"
```

---

## Task 5: ClaudeAgentService — system prompt dinâmico

**Files:**
- Create: `app/Services/ClaudeAgentService.php`

**Interfaces:**
- Consome: `Tenant $tenant`, `array $mensagens` (últimas 20 da tabela `mensagens`), `array $horariosDisponiveis`
- Produz: `array{acao: string, resposta: string, dados: array}`

- [ ] **Step 1: Criar ClaudeAgentService**

```php
<?php
// app/Services/ClaudeAgentService.php
namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAgentService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = (string) config('services.claude.key');
        $this->model  = (string) config('services.claude.model');
    }

    /**
     * @param array $mensagens [['role'=>'user'|'assistant', 'content'=>string], ...]
     * @param array $horariosDisponiveis resultado de AgendamentoService::buscarHorariosDisponiveis()
     * @return array{acao: string, resposta: string, dados: array}
     */
    public function processar(Tenant $tenant, array $mensagens, array $horariosDisponiveis): array
    {
        $systemPrompt = $this->buildSystemPrompt($tenant, $horariosDisponiveis);

        $response = Http::timeout(30)
            ->retry(2, 1000, fn ($e) => $e->getCode() === 529)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 600,
                'system'     => $systemPrompt,
                'messages'   => $mensagens,
            ]);

        if (! $response->successful()) {
            Log::error('ClaudeAgentService error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['acao' => 'erro', 'resposta' => 'Desculpe, tive um problema técnico. Tente novamente em instantes.', 'dados' => []];
        }

        $content = $response->json('content.0.text', '');

        // Tenta extrair JSON + resposta
        if (preg_match('/\{[\s\S]*"acao"[\s\S]*\}/u', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if (is_array($json) && isset($json['acao'], $json['resposta'])) {
                return [
                    'acao'    => $json['acao'],
                    'resposta' => $json['resposta'],
                    'dados'   => $json,
                ];
            }
        }

        return ['acao' => 'duvida', 'resposta' => $content, 'dados' => []];
    }

    public function buildSystemPrompt(Tenant $tenant, array $horariosDisponiveis): string
    {
        $profissionais = $tenant->profissionais()->where('ativo', true)->get()
            ->map(fn ($p) => "- ID {$p->id}: {$p->nome}" . ($p->especialidades ? ' (' . implode(', ', $p->especialidades) . ')' : ''))
            ->join("\n");

        $servicos = $tenant->servicos()->where('ativo', true)->get()
            ->map(fn ($s) => "- ID {$s->id}: {$s->nome}" .
                ($s->valor_min ? " (R$ {$s->valor_min}" . ($s->valor_max ? "-{$s->valor_max}" : '') . ")" : '') .
                " — {$s->duracao_minutos}min" .
                ($s->requer_avaliacao ? ' [requer avaliação]' : ''))
            ->join("\n");

        $opcoes = $tenant->opcoes_extras()->where('ativo', true)->get()
            ->groupBy('tipo')
            ->map(fn ($grupo, $tipo) => strtoupper($tipo) . ': ' . $grupo->pluck('nome')->join(', '))
            ->join("\n");

        $horarios = $this->formatarHorarios($tenant->horarios_funcionamento ?? []);

        $slotsFormatados = $this->formatarSlots($horariosDisponiveis);

        $tomInstrucao = match ($tenant->tom_voz) {
            'formal'      => 'Linguagem profissional e respeitosa. Sem emojis. Use "Senhor/Senhora".',
            'descontraido' => 'Linguagem leve e simpática. Emojis liberados. Pode usar gírias suaves.',
            default       => 'Linguagem clara e amigável. Emojis com moderação. Tratamento informal mas respeitoso.',
        };

        $instrucoes = $tenant->instrucoes_extras ? "\nINSTRUÇÕES ESPECÍFICAS DO NEGÓCIO:\n{$tenant->instrucoes_extras}" : '';

        return <<<PROMPT
Você é {$tenant->nome_agente}, assistente virtual de {$tenant->nome}.
{$tenant->descricao_negocio}

Ramo: {$tenant->ramo_negocio}
Endereço: {$tenant->endereco}, {$tenant->cidade}
Horários de funcionamento: {$horarios}

TOM DE VOZ: {$tomInstrucao}

PROFISSIONAIS DISPONÍVEIS:
{$profissionais}

SERVIÇOS DISPONÍVEIS:
{$servicos}

{$opcoes}

HORÁRIOS DISPONÍVEIS — PRÓXIMOS 7 DIAS:
{$slotsFormatados}

REGRAS:
- Nunca invente horários — use apenas os fornecidos acima
- Mensagens curtas (WhatsApp, não e-mail)
- Após 2 tentativas sem entender o cliente, transfira para humano
- Não faça diagnósticos ou promessas de resultado
{$instrucoes}

QUANDO TRANSFERIR PARA HUMANO:
- Cliente irritado ou reclamando
- Dúvida fora do seu escopo após 2 tentativas
- Cliente pedir explicitamente para falar com pessoa

QUANDO UMA AÇÃO FOR CONFIRMADA, retorne PRIMEIRO o JSON depois a mensagem:
{"acao":"agendar","cliente_nome":"...","profissional_id":123,"servico_id":456,"data":"YYYY-MM-DD","horario":"HH:MM","opcao_extra":null,"observacoes":"...","resposta":"mensagem para o cliente"}

Para transferência:
{"acao":"transferir","resposta":"mensagem para o cliente"}

Para apenas responder (sem ação):
{"acao":"duvida","resposta":"mensagem para o cliente"}
PROMPT;
    }

    private function formatarHorarios(array $horarios): string
    {
        if (empty($horarios)) return 'Consultar pelo WhatsApp';
        return collect($horarios)->map(fn ($h, $k) => "{$k}: {$h}")->join(' | ');
    }

    private function formatarSlots(array $slots): string
    {
        if (empty($slots)) return 'Nenhum horário disponível nos próximos 7 dias.';

        $linhas = [];
        foreach ($slots as $profissionalId => $diasSlots) {
            foreach ($diasSlots as $data => $horariosDisponiveis) {
                if (! empty($horariosDisponiveis)) {
                    $linhas[] = "Profissional #{$profissionalId} — {$data}: " . implode(', ', $horariosDisponiveis);
                }
            }
        }

        return implode("\n", $linhas) ?: 'Nenhum horário disponível.';
    }
}
```

- [ ] **Step 2: Registrar singleton no AppServiceProvider**

Em `app/Providers/AppServiceProvider.php`, adicionar ao método `register()`:

```php
$this->app->singleton(\App\Services\ClaudeAgentService::class);
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/ClaudeAgentService.php app/Providers/AppServiceProvider.php
git commit -m "feat: add ClaudeAgentService with dynamic system prompt"
```

---

## Task 6: AgendamentoService v2

**Files:**
- Modify: `app/Services/AgendamentoService.php`

**Interfaces:**
- `buscarHorariosDisponiveis(Tenant $tenant, int $dias = 7): array` — `[profissional_id => [data => [horarios]]]`
- `criarAgendamentoV2(Tenant $tenant, array $dados): Agendamento`

- [ ] **Step 1: Adicionar métodos v2 ao AgendamentoService existente**

```php
// Adicionar ao final de app/Services/AgendamentoService.php
// (manter todos os métodos existentes)

use App\Models\Profissional;
use Carbon\Carbon;

public function buscarHorariosDisponiveis(Tenant $tenant, int $dias = 7): array
{
    $profissionais = $tenant->profissionais()->where('ativo', true)->with('horarios')->get();
    $resultado = [];

    foreach ($profissionais as $profissional) {
        $resultado[$profissional->id] = [];

        for ($i = 0; $i < $dias; $i++) {
            $data = Carbon::today()->addDays($i);
            $slots = $profissional->slotsDisponiveis($data);
            $disponiveis = collect($slots)->where('disponivel', true)->pluck('hora')->values()->all();

            if (! empty($disponiveis)) {
                $resultado[$profissional->id][$data->format('Y-m-d')] = $disponiveis;
            }
        }
    }

    return $resultado;
}

public function criarAgendamentoV2(Tenant $tenant, array $dados): Agendamento
{
    return \DB::transaction(function () use ($tenant, $dados) {
        \DB::select('SELECT pg_advisory_xact_lock(?)', [$dados['profissional_id']]);

        $dataHora = Carbon::parse("{$dados['data']} {$dados['horario']}");

        $conflito = Agendamento::where('profissional_id', $dados['profissional_id'])
            ->whereNotIn('status', ['cancelado'])
            ->where('data_hora', $dataHora)
            ->exists();

        if ($conflito) {
            throw new \App\Exceptions\HorarioIndisponivelException('Horário não disponível.');
        }

        $servico = Servico::find($dados['servico_id']);

        return Agendamento::create([
            'tenant_id'       => $tenant->id,
            'cliente_id'      => $dados['cliente_id'],
            'profissional_id' => $dados['profissional_id'],
            'servico_id'      => $dados['servico_id'] ?? null,
            'data_hora'       => $dataHora,
            'duracao_minutos' => $servico?->duracao_minutos ?? 30,
            'status'          => 'agendado',
            'opcao_extra'     => $dados['opcao_extra'] ?? null,
            'observacoes'     => $dados['observacoes'] ?? null,
            'origem'          => $dados['origem'] ?? 'bot',
        ]);
    });
}
```

- [ ] **Step 2: Verificar que `HorarioIndisponivelException` existe**

```bash
# Se não existir:
php artisan make:exception HorarioIndisponivelException
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/AgendamentoService.php
git commit -m "feat: add v2 methods to AgendamentoService"
```

---

## Task 7: BotService v2 — nova orquestração com tabela mensagens

**Files:**
- Create: `app/Jobs/ProcessarMensagemWhatsapp.php`

> Nota: o job atual se chama `ProcessarMensagemJob.php`. O novo tem nome diferente — mantém o antigo intacto.

**Interfaces:**
- Consome: `Tenant $tenant`, `string $telefone`, `string $mensagem`, `?string $evolutionMessageId`
- Usa: `ClaudeAgentService`, `AgendamentoService`, `EvolutionApiService`

- [ ] **Step 1: Criar ProcessarMensagemWhatsapp job**

```php
<?php
// app/Jobs/ProcessarMensagemWhatsapp.php
namespace App\Jobs;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Services\AgendamentoService;
use App\Services\ClaudeAgentService;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessarMensagemWhatsapp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public array $backoff = [30, 60, 120];
    public int $timeout = 45;

    public function __construct(
        private Tenant $tenant,
        private string $telefone,
        private string $mensagem,
        private ?string $evolutionMessageId = null,
    ) {}

    public function handle(
        ClaudeAgentService $claude,
        AgendamentoService $agendamentoService,
        EvolutionApiService $evolution,
    ): void {
        // 1. Evitar duplicata
        if ($this->evolutionMessageId && Mensagem::where('evolution_message_id', $this->evolutionMessageId)->exists()) {
            return;
        }

        // 2. Buscar ou criar cliente
        $cliente = Cliente::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone' => $this->telefone],
            ['nome' => 'Cliente WhatsApp'],
        );

        // 3. Buscar ou criar conversa
        $conversa = Conversa::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'telefone_cliente' => $this->telefone],
            ['status_v2' => 'ativa', 'cliente_id' => $cliente->id, 'etapa' => 'idle'],
        );

        if (! $conversa->cliente_id) {
            $conversa->update(['cliente_id' => $cliente->id]);
        }

        // 4. Se aguardando/em atendimento humano → apenas salva e não processa
        if (in_array($conversa->status_v2, ['aguardando_humano', 'em_atendimento_humano'])) {
            $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);
            return;
        }

        // 5. Salvar mensagem do cliente
        $conversa->registrarMensagem('cliente', $this->mensagem, $this->evolutionMessageId);

        // 6. Buscar histórico (últimas 20 mensagens) para o Claude
        $historico = $conversa->mensagens()
            ->latest('enviada_em')
            ->limit(20)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role'    => $m->remetente === 'cliente' ? 'user' : 'assistant',
                'content' => $m->conteudo,
            ])
            ->values()
            ->all();

        // 7. Buscar slots disponíveis
        $horariosDisponiveis = $agendamentoService->buscarHorariosDisponiveis($this->tenant, 7);

        // 8. Chamar Claude
        $resultado = $claude->processar($this->tenant, $historico, $horariosDisponiveis);

        // 9. Processar ação
        match ($resultado['acao']) {
            'agendar'   => $this->processarAgendamento($resultado['dados'], $cliente, $agendamentoService),
            'transferir' => $this->transferirParaHumano($conversa),
            default     => null,
        };

        // 10. Salvar resposta do bot e enviar
        $conversa->registrarMensagem('bot', $resultado['resposta']);
        $evolution->enviarMensagem($this->tenant->evolution_instance, $this->telefone, $resultado['resposta']);
    }

    private function processarAgendamento(array $dados, Cliente $cliente, AgendamentoService $service): void
    {
        try {
            // Atualizar nome do cliente se identificado
            if (! empty($dados['cliente_nome']) && $dados['cliente_nome'] !== 'Cliente WhatsApp') {
                $cliente->update(['nome' => $dados['cliente_nome']]);
            }

            $service->criarAgendamentoV2($this->tenant, array_merge($dados, [
                'cliente_id' => $cliente->id,
                'origem'     => 'bot',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Falha ao criar agendamento v2', ['error' => $e->getMessage(), 'dados' => $dados]);
        }
    }

    private function transferirParaHumano(Conversa $conversa): void
    {
        $conversa->update(['status_v2' => 'aguardando_humano']);
    }
}
```

- [ ] **Step 2: Atualizar WebhookController para usar novo job**

```php
// Em app/Http/Controllers/WebhookController.php
// Substituir ProcessarMensagemJob::dispatch pela linha abaixo:

$evolutionMessageId = data_get($data, 'data.key.id');
\App\Jobs\ProcessarMensagemWhatsapp::dispatch($tenant, $telefone, $mensagem, $evolutionMessageId);
```

E adicionar verificação de `bot_ativo`:

```php
// Logo após buscar o tenant:
if (! $tenant->bot_ativo) {
    return response('ok');
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/ProcessarMensagemWhatsapp.php app/Http/Controllers/WebhookController.php
git commit -m "feat: add ProcessarMensagemWhatsapp job v2 with mensagens table and transfer-to-human"
```

---

## Task 8: Rotas para novas entidades

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Adicionar rotas de profissionais, serviços, clientes, conversas**

Dentro do grupo `Route::middleware(['tenant', 'subscription'])->prefix('painel')`, adicionar após as rotas de recursos existentes:

```php
// Profissionais
Route::resource('profissionais', Tenant\ProfissionalController::class)->except(['show']);
Route::post('profissionais/{profissional}/horarios', [Tenant\HorarioProfissionalController::class, 'sync'])
    ->name('profissionais.horarios.sync');

// Serviços
Route::resource('servicos', Tenant\ServicoController::class)->except(['show']);

// Opções extras (convênios, pagamentos)
Route::resource('opcoes-extras', Tenant\OpcaoExtraController::class)->except(['show']);

// Clientes
Route::get('clientes', [Tenant\ClienteController::class, 'index'])->name('clientes.index');
Route::get('clientes/{cliente}', [Tenant\ClienteController::class, 'show'])->name('clientes.show');

// Conversas WhatsApp
Route::get('conversas', [Tenant\ConversaController::class, 'index'])->name('conversas.index');
Route::get('conversas/{conversa}/mensagens', [Tenant\ConversaController::class, 'mensagens'])->name('conversas.mensagens');
Route::post('conversas/{conversa}/assumir', [Tenant\ConversaController::class, 'assumir'])->name('conversas.assumir');
Route::post('conversas/{conversa}/devolver', [Tenant\ConversaController::class, 'devolver'])->name('conversas.devolver');
Route::post('conversas/{conversa}/enviar', [Tenant\ConversaController::class, 'enviarMensagem'])->name('conversas.enviar');

// Config bot
Route::put('configuracoes/bot', [Tenant\ConfiguracaoController::class, 'updateBot'])->name('configuracoes.bot');
```

- [ ] **Step 2: Commit**

```bash
git add routes/web.php
git commit -m "feat: add routes for profissionais, servicos, clientes, conversas"
```

---

## Task 9: Controllers para profissionais e serviços

**Files:**
- Create: `app/Http/Controllers/Tenant/ProfissionalController.php`
- Create: `app/Http/Controllers/Tenant/HorarioProfissionalController.php`
- Create: `app/Http/Controllers/Tenant/ServicoController.php`
- Create: `app/Http/Controllers/Tenant/OpcaoExtraController.php`

- [ ] **Step 1: ProfissionalController**

```php
<?php
// app/Http/Controllers/Tenant/ProfissionalController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfissionalController extends Controller
{
    public function index(): Response
    {
        $tenant = app('tenant');
        return Inertia::render('Tenant/Profissionais/Index', [
            'profissionais' => $tenant->profissionais()->with('horarios')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = app('tenant');
        $data = $request->validate([
            'nome'          => 'required|string|max:255',
            'especialidades' => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo'         => 'boolean',
        ]);
        $tenant->profissionais()->create($data);
        return back()->with('success', 'Profissional criado.');
    }

    public function update(Request $request, Profissional $profissional): RedirectResponse
    {
        abort_if($profissional->tenant_id !== app('tenant')->id, 403);
        $data = $request->validate([
            'nome'          => 'required|string|max:255',
            'especialidades' => 'nullable|array',
            'especialidades.*' => 'string|max:100',
            'ativo'         => 'boolean',
        ]);
        $profissional->update($data);
        return back()->with('success', 'Profissional atualizado.');
    }

    public function destroy(Profissional $profissional): RedirectResponse
    {
        abort_if($profissional->tenant_id !== app('tenant')->id, 403);
        $profissional->delete();
        return back()->with('success', 'Profissional removido.');
    }
}
```

- [ ] **Step 2: HorarioProfissionalController**

```php
<?php
// app/Http/Controllers/Tenant/HorarioProfissionalController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Profissional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HorarioProfissionalController extends Controller
{
    public function sync(Request $request, Profissional $profissional): RedirectResponse
    {
        abort_if($profissional->tenant_id !== app('tenant')->id, 403);

        $request->validate([
            'horarios'                  => 'required|array',
            'horarios.*.dia_semana'     => 'required|integer|between:0,6',
            'horarios.*.hora_inicio'    => 'required|date_format:H:i',
            'horarios.*.hora_fim'       => 'required|date_format:H:i',
            'horarios.*.duracao_slot'   => 'integer|min:15|max:240',
            'horarios.*.ativo'          => 'boolean',
        ]);

        $profissional->horarios()->delete();

        collect($request->horarios)
            ->where('ativo', true)
            ->each(fn ($h) => $profissional->horarios()->create($h));

        return back()->with('success', 'Horários salvos.');
    }
}
```

- [ ] **Step 3: ServicoController**

```php
<?php
// app/Http/Controllers/Tenant/ServicoController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServicoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Servicos/Index', [
            'servicos' => app('tenant')->servicos()->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'             => 'required|string|max:255',
            'descricao'        => 'nullable|string',
            'valor_min'        => 'nullable|numeric|min:0',
            'valor_max'        => 'nullable|numeric|gte:valor_min',
            'duracao_minutos'  => 'required|integer|min:5',
            'requer_avaliacao' => 'boolean',
            'ativo'            => 'boolean',
        ]);
        app('tenant')->servicos()->create($data);
        return back()->with('success', 'Serviço criado.');
    }

    public function update(Request $request, Servico $servico): RedirectResponse
    {
        abort_if($servico->tenant_id !== app('tenant')->id, 403);
        $data = $request->validate([
            'nome'             => 'required|string|max:255',
            'descricao'        => 'nullable|string',
            'valor_min'        => 'nullable|numeric|min:0',
            'valor_max'        => 'nullable|numeric|gte:valor_min',
            'duracao_minutos'  => 'required|integer|min:5',
            'requer_avaliacao' => 'boolean',
            'ativo'            => 'boolean',
        ]);
        $servico->update($data);
        return back()->with('success', 'Serviço atualizado.');
    }

    public function destroy(Servico $servico): RedirectResponse
    {
        abort_if($servico->tenant_id !== app('tenant')->id, 403);
        $servico->delete();
        return back()->with('success', 'Serviço removido.');
    }
}
```

- [ ] **Step 4: OpcaoExtraController**

```php
<?php
// app/Http/Controllers/Tenant/OpcaoExtraController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\OpcaoExtra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpcaoExtraController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/OpcaoExtra/Index', [
            'opcoes' => app('tenant')->opcoes_extras()->orderBy('tipo')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => 'required|in:convenio,pagamento,outro',
            'nome' => 'required|string|max:255',
            'ativo' => 'boolean',
        ]);
        app('tenant')->opcoes_extras()->create($data);
        return back()->with('success', 'Opção criada.');
    }

    public function update(Request $request, OpcaoExtra $opcaoExtra): RedirectResponse
    {
        abort_if($opcaoExtra->tenant_id !== app('tenant')->id, 403);
        $opcaoExtra->update($request->validate([
            'tipo' => 'required|in:convenio,pagamento,outro',
            'nome' => 'required|string|max:255',
            'ativo' => 'boolean',
        ]));
        return back()->with('success', 'Opção atualizada.');
    }

    public function destroy(OpcaoExtra $opcaoExtra): RedirectResponse
    {
        abort_if($opcaoExtra->tenant_id !== app('tenant')->id, 403);
        $opcaoExtra->delete();
        return back()->with('success', 'Opção removida.');
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Tenant/ProfissionalController.php \
        app/Http/Controllers/Tenant/HorarioProfissionalController.php \
        app/Http/Controllers/Tenant/ServicoController.php \
        app/Http/Controllers/Tenant/OpcaoExtraController.php
git commit -m "feat: add CRUD controllers for profissionais, servicos, opcoes_extras"
```

---

## Task 10: ConversaController + ClienteController

**Files:**
- Create: `app/Http/Controllers/Tenant/ConversaController.php`
- Create: `app/Http/Controllers/Tenant/ClienteController.php`

- [ ] **Step 1: ConversaController**

```php
<?php
// app/Http/Controllers/Tenant/ConversaController.php
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
    public function index(): Response
    {
        $tenant = app('tenant');
        return Inertia::render('Tenant/Conversas/Index', [
            'conversas' => Conversa::where('tenant_id', $tenant->id)
                ->with('cliente')
                ->orderByDesc('ultima_mensagem_em')
                ->paginate(30),
        ]);
    }

    public function mensagens(Conversa $conversa): JsonResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);
        return response()->json(
            $conversa->mensagens()->orderBy('enviada_em')->get()
        );
    }

    public function assumir(Conversa $conversa): RedirectResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);
        $conversa->update(['status_v2' => 'em_atendimento_humano']);
        return back()->with('success', 'Atendimento assumido.');
    }

    public function devolver(Conversa $conversa): RedirectResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);
        $conversa->update(['status_v2' => 'ativa']);
        return back()->with('success', 'Bot reativado.');
    }

    public function enviarMensagem(Request $request, Conversa $conversa, EvolutionApiService $evolution): RedirectResponse
    {
        abort_if($conversa->tenant_id !== app('tenant')->id, 403);

        $data = $request->validate(['mensagem' => 'required|string|max:4000']);
        $tenant = app('tenant');

        $conversa->registrarMensagem('humano', $data['mensagem']);
        $evolution->enviarMensagem($tenant->evolution_instance, $conversa->telefone_cliente, $data['mensagem']);

        return back();
    }
}
```

- [ ] **Step 2: ClienteController**

```php
<?php
// app/Http/Controllers/Tenant/ClienteController.php
namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = app('tenant');
        $query  = $tenant->clientes()->orderBy('nome');

        if ($busca = $request->busca) {
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'ilike', "%{$busca}%")
                  ->orWhere('telefone', 'like', "%{$busca}%");
            });
        }

        return Inertia::render('Tenant/Clientes/Index', [
            'clientes' => $query->paginate(30)->withQueryString(),
            'filtros'  => $request->only('busca'),
        ]);
    }

    public function show(Cliente $cliente): Response
    {
        abort_if($cliente->tenant_id !== app('tenant')->id, 403);
        return Inertia::render('Tenant/Clientes/Show', [
            'cliente'      => $cliente,
            'agendamentos' => $cliente->agendamentos()->with('profissional', 'servico')->orderByDesc('data_hora')->get(),
            'conversas'    => $cliente->conversas()->orderByDesc('ultima_mensagem_em')->get(),
        ]);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Tenant/ConversaController.php \
        app/Http/Controllers/Tenant/ClienteController.php
git commit -m "feat: add ConversaController and ClienteController"
```

---

## Task 11: Atualizar ConfiguracaoController para bot config

**Files:**
- Modify: `app/Http/Controllers/Tenant/ConfiguracaoController.php`

- [ ] **Step 1: Adicionar método updateBot**

```php
// Adicionar ao final de ConfiguracaoController:

public function updateBot(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
{
    $tenant = app('tenant');

    $data = $request->validate([
        'ramo_negocio'        => 'nullable|string|max:255',
        'descricao_negocio'   => 'nullable|string|max:2000',
        'cidade'              => 'nullable|string|max:100',
        'endereco'            => 'nullable|string|max:255',
        'horarios_funcionamento' => 'nullable|array',
        'nome_agente'         => 'required|string|max:50',
        'tom_voz'             => 'required|in:formal,semiformal,descontraido',
        'instrucoes_extras'   => 'nullable|string|max:3000',
        'bot_ativo'           => 'boolean',
    ]);

    $tenant->update($data);

    return back()->with('success', 'Configurações do bot salvas.');
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Tenant/ConfiguracaoController.php
git commit -m "feat: add bot config endpoint to ConfiguracaoController"
```

---

## Task 12: Frontend — Profissionais/Index.tsx

**Files:**
- Create: `resources/js/Pages/Tenant/Profissionais/Index.tsx`

- [ ] **Step 1: Criar página de profissionais**

```tsx
// resources/js/Pages/Tenant/Profissionais/Index.tsx
import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface HorarioProfissional {
    id?: number;
    dia_semana: number;
    hora_inicio: string;
    hora_fim: string;
    duracao_slot: number;
    ativo: boolean;
}

interface Profissional {
    id: number;
    nome: string;
    especialidades: string[] | null;
    ativo: boolean;
    horarios: HorarioProfissional[];
}

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

function HorariosEditor({ profissional }: { profissional: Profissional }) {
    const [horarios, setHorarios] = useState<HorarioProfissional[]>(
        DIAS.map((_, i) => {
            const h = profissional.horarios?.find(h => h.dia_semana === i);
            return h
                ? { ...h, ativo: true }
                : { dia_semana: i, hora_inicio: '09:00', hora_fim: '18:00', duracao_slot: 30, ativo: false };
        })
    );

    const toggle = (idx: number) => {
        setHorarios(prev => prev.map((h, i) => i === idx ? { ...h, ativo: !h.ativo } : h));
    };

    const update = (idx: number, field: string, value: string | number) => {
        setHorarios(prev => prev.map((h, i) => i === idx ? { ...h, [field]: value } : h));
    };

    const salvar = () => {
        router.post(route('tenant.profissionais.horarios.sync', profissional.id), {
            horarios: horarios,
        }, { preserveScroll: true });
    };

    return (
        <div className="mt-3 space-y-2">
            {horarios.map((h, idx) => (
                <div key={idx} className="flex items-center gap-3 text-sm">
                    <input type="checkbox" checked={h.ativo} onChange={() => toggle(idx)} />
                    <span className="w-8">{DIAS[idx]}</span>
                    <input
                        type="time" value={h.hora_inicio} disabled={!h.ativo}
                        onChange={e => update(idx, 'hora_inicio', e.target.value)}
                        className="border rounded px-2 py-1 disabled:opacity-40"
                    />
                    <span>até</span>
                    <input
                        type="time" value={h.hora_fim} disabled={!h.ativo}
                        onChange={e => update(idx, 'hora_fim', e.target.value)}
                        className="border rounded px-2 py-1 disabled:opacity-40"
                    />
                    <select
                        value={h.duracao_slot} disabled={!h.ativo}
                        onChange={e => update(idx, 'duracao_slot', parseInt(e.target.value))}
                        className="border rounded px-2 py-1 disabled:opacity-40"
                    >
                        {[15,20,30,45,60,90,120].map(m => <option key={m} value={m}>{m}min</option>)}
                    </select>
                </div>
            ))}
            <button onClick={salvar} className="mt-2 bg-blue-600 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-700">
                Salvar horários
            </button>
        </div>
    );
}

export default function ProfissionaisIndex({ profissionais }: { profissionais: Profissional[] }) {
    const [expandido, setExpandido] = useState<number | null>(null);
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, reset } = useForm({
        nome: '',
        especialidades: [] as string[],
        ativo: true,
    });

    const [novaEsp, setNovaEsp] = useState('');

    const addEspecialidade = () => {
        if (novaEsp.trim()) {
            setData('especialidades', [...data.especialidades, novaEsp.trim()]);
            setNovaEsp('');
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.profissionais.store'), {
            onSuccess: () => { reset(); setShowForm(false); },
            preserveScroll: true,
        });
    };

    const excluir = (id: number) => {
        if (confirm('Remover profissional?')) {
            router.delete(route('tenant.profissionais.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout header="Profissionais">
            <div className="max-w-3xl mx-auto py-8 px-4">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-semibold">Profissionais</h1>
                    <button onClick={() => setShowForm(!showForm)}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        + Novo profissional
                    </button>
                </div>

                {showForm && (
                    <form onSubmit={submit} className="bg-white border rounded-xl p-6 mb-6 space-y-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Nome *</label>
                            <input value={data.nome} onChange={e => setData('nome', e.target.value)}
                                className="w-full border rounded px-3 py-2" placeholder="Ex: Dr. João, Barbeiro Carlos" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Especialidades</label>
                            <div className="flex gap-2">
                                <input value={novaEsp} onChange={e => setNovaEsp(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), addEspecialidade())}
                                    className="flex-1 border rounded px-3 py-2" placeholder="Ex: Corte masculino" />
                                <button type="button" onClick={addEspecialidade}
                                    className="bg-gray-100 px-3 py-2 rounded border">+</button>
                            </div>
                            <div className="flex flex-wrap gap-2 mt-2">
                                {data.especialidades.map((esp, i) => (
                                    <span key={i} className="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-sm flex items-center gap-1">
                                        {esp}
                                        <button type="button" onClick={() =>
                                            setData('especialidades', data.especialidades.filter((_, j) => j !== i))
                                        } className="text-blue-600 hover:text-blue-800">×</button>
                                    </span>
                                ))}
                            </div>
                        </div>
                        <div className="flex gap-3">
                            <button type="submit" disabled={processing}
                                className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                                Salvar
                            </button>
                            <button type="button" onClick={() => setShowForm(false)} className="text-gray-600">Cancelar</button>
                        </div>
                    </form>
                )}

                <div className="space-y-4">
                    {profissionais.length === 0 && (
                        <p className="text-gray-500 text-center py-8">Nenhum profissional cadastrado ainda.</p>
                    )}
                    {profissionais.map(p => (
                        <div key={p.id} className="bg-white border rounded-xl p-5">
                            <div className="flex justify-between items-start">
                                <div>
                                    <h3 className="font-semibold text-lg">{p.nome}</h3>
                                    {p.especialidades && (
                                        <div className="flex flex-wrap gap-1 mt-1">
                                            {p.especialidades.map((esp, i) => (
                                                <span key={i} className="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs">{esp}</span>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <button onClick={() => setExpandido(expandido === p.id ? null : p.id)}
                                        className="text-sm text-blue-600 hover:underline">
                                        {expandido === p.id ? 'Fechar horários' : 'Editar horários'}
                                    </button>
                                    <button onClick={() => excluir(p.id)}
                                        className="text-sm text-red-600 hover:underline">Remover</button>
                                </div>
                            </div>
                            {expandido === p.id && <HorariosEditor profissional={p} />}
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Tenant/Profissionais/
git commit -m "feat: add Profissionais/Index page with schedule editor"
```

---

## Task 13: Frontend — Servicos/Index.tsx

**Files:**
- Create: `resources/js/Pages/Tenant/Servicos/Index.tsx`

- [ ] **Step 1: Criar página de serviços**

```tsx
// resources/js/Pages/Tenant/Servicos/Index.tsx
import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface Servico {
    id: number;
    nome: string;
    descricao: string | null;
    valor_min: number | null;
    valor_max: number | null;
    duracao_minutos: number;
    requer_avaliacao: boolean;
    ativo: boolean;
}

export default function ServicosIndex({ servicos }: { servicos: Servico[] }) {
    const [showForm, setShowForm] = useState(false);
    const [editando, setEditando] = useState<Servico | null>(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nome: '',
        descricao: '',
        valor_min: '',
        valor_max: '',
        duracao_minutos: 30,
        requer_avaliacao: false,
        ativo: true,
    });

    const abrirEditar = (s: Servico) => {
        setEditando(s);
        setData({
            nome: s.nome,
            descricao: s.descricao ?? '',
            valor_min: s.valor_min?.toString() ?? '',
            valor_max: s.valor_max?.toString() ?? '',
            duracao_minutos: s.duracao_minutos,
            requer_avaliacao: s.requer_avaliacao,
            ativo: s.ativo,
        });
        setShowForm(true);
    };

    const fechar = () => { setShowForm(false); setEditando(null); reset(); };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const action = editando
            ? put(route('tenant.servicos.update', editando.id), { onSuccess: fechar, preserveScroll: true })
            : post(route('tenant.servicos.store'), { onSuccess: fechar, preserveScroll: true });
    };

    const excluir = (id: number) => {
        if (confirm('Remover serviço?')) router.delete(route('tenant.servicos.destroy', id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header="Serviços">
            <div className="max-w-3xl mx-auto py-8 px-4">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-semibold">Serviços</h1>
                    <button onClick={() => setShowForm(true)}
                        className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        + Novo serviço
                    </button>
                </div>

                {showForm && (
                    <form onSubmit={submit} className="bg-white border rounded-xl p-6 mb-6 space-y-4">
                        <h2 className="font-semibold">{editando ? 'Editar serviço' : 'Novo serviço'}</h2>
                        <div>
                            <label className="block text-sm font-medium mb-1">Nome *</label>
                            <input value={data.nome} onChange={e => setData('nome', e.target.value)}
                                className="w-full border rounded px-3 py-2" placeholder="Ex: Consulta, Corte + Barba" />
                            {errors.nome && <p className="text-red-500 text-xs mt-1">{errors.nome}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Descrição</label>
                            <textarea value={data.descricao} onChange={e => setData('descricao', e.target.value)}
                                rows={2} className="w-full border rounded px-3 py-2" />
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium mb-1">Valor mín (R$)</label>
                                <input type="number" step="0.01" value={data.valor_min}
                                    onChange={e => setData('valor_min', e.target.value)}
                                    className="w-full border rounded px-3 py-2" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Valor máx (R$)</label>
                                <input type="number" step="0.01" value={data.valor_max}
                                    onChange={e => setData('valor_max', e.target.value)}
                                    className="w-full border rounded px-3 py-2" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Duração (min) *</label>
                                <select value={data.duracao_minutos}
                                    onChange={e => setData('duracao_minutos', parseInt(e.target.value))}
                                    className="w-full border rounded px-3 py-2">
                                    {[15,20,30,45,60,90,120].map(m => <option key={m} value={m}>{m} min</option>)}
                                </select>
                            </div>
                        </div>
                        <div className="flex gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.requer_avaliacao}
                                    onChange={e => setData('requer_avaliacao', e.target.checked)} />
                                Requer avaliação prévia
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.ativo}
                                    onChange={e => setData('ativo', e.target.checked)} />
                                Ativo
                            </label>
                        </div>
                        <div className="flex gap-3">
                            <button type="submit" disabled={processing}
                                className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                                Salvar
                            </button>
                            <button type="button" onClick={fechar} className="text-gray-600">Cancelar</button>
                        </div>
                    </form>
                )}

                <div className="space-y-3">
                    {servicos.length === 0 && (
                        <p className="text-gray-500 text-center py-8">Nenhum serviço cadastrado.</p>
                    )}
                    {servicos.map(s => (
                        <div key={s.id} className="bg-white border rounded-xl p-4 flex justify-between items-center">
                            <div>
                                <h3 className="font-medium">{s.nome}</h3>
                                <p className="text-sm text-gray-500">
                                    {s.duracao_minutos}min
                                    {s.valor_min ? ` · R$ ${s.valor_min}${s.valor_max ? `–${s.valor_max}` : ''}` : ''}
                                    {s.requer_avaliacao ? ' · Requer avaliação' : ''}
                                    {!s.ativo ? ' · Inativo' : ''}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <button onClick={() => abrirEditar(s)} className="text-sm text-blue-600 hover:underline">Editar</button>
                                <button onClick={() => excluir(s.id)} className="text-sm text-red-600 hover:underline">Remover</button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Tenant/Servicos/
git commit -m "feat: add Servicos/Index page"
```

---

## Task 14: Frontend — Conversas/Index.tsx com chat

**Files:**
- Create: `resources/js/Pages/Tenant/Conversas/Index.tsx`

- [ ] **Step 1: Criar página de conversas**

```tsx
// resources/js/Pages/Tenant/Conversas/Index.tsx
import { useState, useEffect, useRef } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import axios from 'axios';

interface Cliente { id: number; nome: string; telefone: string; }
interface Conversa {
    id: number;
    cliente: Cliente | null;
    telefone_cliente: string;
    status_v2: 'ativa' | 'aguardando_humano' | 'em_atendimento_humano' | 'encerrada';
    ultima_mensagem_em: string | null;
}
interface Mensagem {
    id: number;
    remetente: 'cliente' | 'bot' | 'humano';
    conteudo: string;
    enviada_em: string;
}

const STATUS_LABEL: Record<string, string> = {
    ativa: 'Ativa',
    aguardando_humano: '⚠️ Aguardando atendimento',
    em_atendimento_humano: '🧑 Em atendimento',
    encerrada: 'Encerrada',
};

const STATUS_COLOR: Record<string, string> = {
    ativa: 'bg-green-100 text-green-700',
    aguardando_humano: 'bg-yellow-100 text-yellow-700',
    em_atendimento_humano: 'bg-blue-100 text-blue-700',
    encerrada: 'bg-gray-100 text-gray-500',
};

export default function ConversasIndex({ conversas }: { conversas: { data: Conversa[] } }) {
    const [selecionada, setSelecionada] = useState<Conversa | null>(null);
    const [mensagens, setMensagens] = useState<Mensagem[]>([]);
    const [carregando, setCarregando] = useState(false);
    const chatRef = useRef<HTMLDivElement>(null);

    const { data, setData, post, processing, reset } = useForm({ mensagem: '' });

    const carregarMensagens = async (conversa: Conversa) => {
        setCarregando(true);
        try {
            const res = await axios.get(route('tenant.conversas.mensagens', conversa.id));
            setMensagens(res.data);
            setTimeout(() => chatRef.current?.scrollTo(0, chatRef.current.scrollHeight), 100);
        } finally {
            setCarregando(false);
        }
    };

    const selecionar = (c: Conversa) => {
        setSelecionada(c);
        carregarMensagens(c);
    };

    const assumir = () => {
        if (!selecionada) return;
        router.post(route('tenant.conversas.assumir', selecionada.id), {}, {
            preserveScroll: true,
            onSuccess: () => setSelecionada(prev => prev ? { ...prev, status_v2: 'em_atendimento_humano' } : null),
        });
    };

    const devolver = () => {
        if (!selecionada) return;
        router.post(route('tenant.conversas.devolver', selecionada.id), {}, {
            preserveScroll: true,
            onSuccess: () => setSelecionada(prev => prev ? { ...prev, status_v2: 'ativa' } : null),
        });
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selecionada || !data.mensagem.trim()) return;
        post(route('tenant.conversas.enviar', selecionada.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset('mensagem');
                carregarMensagens(selecionada);
            },
        });
    };

    return (
        <AuthenticatedLayout header="Conversas WhatsApp">
            <div className="flex h-[calc(100vh-120px)] max-w-6xl mx-auto">
                {/* Lista de conversas */}
                <div className="w-80 border-r bg-white overflow-y-auto flex-shrink-0">
                    {conversas.data.length === 0 && (
                        <p className="text-gray-500 text-center py-8 text-sm">Nenhuma conversa ainda.</p>
                    )}
                    {conversas.data.map(c => (
                        <div
                            key={c.id}
                            onClick={() => selecionar(c)}
                            className={`p-4 border-b cursor-pointer hover:bg-gray-50 ${selecionada?.id === c.id ? 'bg-blue-50' : ''}`}
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className="font-medium text-sm">{c.cliente?.nome ?? c.telefone_cliente}</p>
                                    <p className="text-xs text-gray-400">{c.telefone_cliente}</p>
                                </div>
                                <span className={`text-xs px-2 py-0.5 rounded-full ${STATUS_COLOR[c.status_v2]}`}>
                                    {STATUS_LABEL[c.status_v2]}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Área do chat */}
                {selecionada ? (
                    <div className="flex-1 flex flex-col bg-gray-50">
                        {/* Header */}
                        <div className="bg-white border-b px-4 py-3 flex justify-between items-center">
                            <div>
                                <p className="font-semibold">{selecionada.cliente?.nome ?? selecionada.telefone_cliente}</p>
                                <p className="text-xs text-gray-400">{selecionada.telefone_cliente}</p>
                            </div>
                            <div className="flex gap-2">
                                {selecionada.status_v2 !== 'em_atendimento_humano' && (
                                    <button onClick={assumir}
                                        className="bg-blue-600 text-white text-sm px-3 py-1.5 rounded hover:bg-blue-700">
                                        Assumir atendimento
                                    </button>
                                )}
                                {selecionada.status_v2 === 'em_atendimento_humano' && (
                                    <button onClick={devolver}
                                        className="bg-green-600 text-white text-sm px-3 py-1.5 rounded hover:bg-green-700">
                                        Devolver ao bot
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Mensagens */}
                        <div ref={chatRef} className="flex-1 overflow-y-auto p-4 space-y-2">
                            {carregando && <p className="text-center text-gray-400 text-sm">Carregando...</p>}
                            {mensagens.map(m => (
                                <div key={m.id} className={`flex ${m.remetente === 'cliente' ? 'justify-start' : 'justify-end'}`}>
                                    <div className={`max-w-xs px-3 py-2 rounded-xl text-sm ${
                                        m.remetente === 'cliente' ? 'bg-white border' :
                                        m.remetente === 'bot' ? 'bg-blue-500 text-white' : 'bg-green-500 text-white'
                                    }`}>
                                        {m.remetente !== 'cliente' && (
                                            <p className="text-xs opacity-70 mb-1">{m.remetente === 'bot' ? '🤖 Bot' : '🧑 Você'}</p>
                                        )}
                                        <p style={{ whiteSpace: 'pre-wrap' }}>{m.conteudo}</p>
                                        <p className="text-xs opacity-60 mt-1">
                                            {new Date(m.enviada_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Input — só disponível em atendimento humano */}
                        {selecionada.status_v2 === 'em_atendimento_humano' && (
                            <form onSubmit={enviar} className="border-t bg-white p-3 flex gap-2">
                                <input
                                    value={data.mensagem}
                                    onChange={e => setData('mensagem', e.target.value)}
                                    placeholder="Digite uma mensagem..."
                                    className="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none"
                                />
                                <button type="submit" disabled={processing}
                                    className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                                    Enviar
                                </button>
                            </form>
                        )}
                        {selecionada.status_v2 !== 'em_atendimento_humano' && (
                            <div className="border-t bg-gray-100 p-3 text-center text-xs text-gray-400">
                                Assuma o atendimento para enviar mensagens
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex-1 flex items-center justify-center text-gray-400">
                        Selecione uma conversa
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Tenant/Conversas/
git commit -m "feat: add Conversas/Index page with real-time chat and transfer-to-human UI"
```

---

## Task 15: Atualizar Tenant/Configuracoes.tsx — seção Bot & IA

**Files:**
- Modify: `resources/js/Pages/Tenant/Configuracoes.tsx`

- [ ] **Step 1: Adicionar seção de bot ao formulário de configurações**

No arquivo existente `resources/js/Pages/Tenant/Configuracoes.tsx`, adicionar (ou substituir o conteúdo) para incluir a seção Bot & IA. Encontrar onde o formulário termina e adicionar o seguinte bloco como nova seção ou tab:

```tsx
// Adicionar interface ao tipo Tenant recebido via props:
// nome_agente: string;
// tom_voz: 'formal' | 'semiformal' | 'descontraido';
// instrucoes_extras: string | null;
// bot_ativo: boolean;
// ramo_negocio: string | null;
// descricao_negocio: string | null;

// Adicionar ao componente, após o form de dados gerais:

function BotConfigForm({ tenant }: { tenant: any }) {
    const { data, setData, put, processing, errors } = useForm({
        ramo_negocio: tenant.ramo_negocio ?? '',
        descricao_negocio: tenant.descricao_negocio ?? '',
        cidade: tenant.cidade ?? '',
        endereco: tenant.endereco ?? '',
        nome_agente: tenant.nome_agente ?? 'Bia',
        tom_voz: tenant.tom_voz ?? 'semiformal',
        instrucoes_extras: tenant.instrucoes_extras ?? '',
        bot_ativo: tenant.bot_ativo ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.configuracoes.bot'));
    };

    const TONS = [
        { value: 'formal', label: 'Formal', desc: 'Profissional, sem emojis, "Senhor/Senhora"' },
        { value: 'semiformal', label: 'Semiformal', desc: 'Claro e amigável, emojis moderados' },
        { value: 'descontraido', label: 'Descontraído', desc: 'Leve, emojis liberados, gírias suaves' },
    ];

    return (
        <form onSubmit={submit} className="space-y-6 bg-white border rounded-xl p-6">
            <div className="flex justify-between items-center">
                <h2 className="text-lg font-semibold">Bot & IA</h2>
                <label className="flex items-center gap-2 cursor-pointer">
                    <span className="text-sm text-gray-600">Bot ativo</span>
                    <div className={`relative w-11 h-6 rounded-full transition-colors ${data.bot_ativo ? 'bg-green-500' : 'bg-gray-300'}`}
                        onClick={() => setData('bot_ativo', !data.bot_ativo)}>
                        <div className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${data.bot_ativo ? 'translate-x-5' : ''}`} />
                    </div>
                </label>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium mb-1">Nome do agente</label>
                    <input value={data.nome_agente} onChange={e => setData('nome_agente', e.target.value)}
                        className="w-full border rounded px-3 py-2" placeholder="Ex: Bia, Max, Duda" />
                </div>
                <div>
                    <label className="block text-sm font-medium mb-1">Ramo do negócio</label>
                    <input value={data.ramo_negocio} onChange={e => setData('ramo_negocio', e.target.value)}
                        className="w-full border rounded px-3 py-2" placeholder="Ex: Clínica odontológica, Barbearia" />
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium mb-1">Descrição do negócio</label>
                <textarea value={data.descricao_negocio} onChange={e => setData('descricao_negocio', e.target.value)}
                    rows={2} className="w-full border rounded px-3 py-2"
                    placeholder="Ex: Clínica especializada em odontologia estética e preventiva, atendendo famílias há 15 anos." />
                <p className="text-xs text-gray-400 mt-1">Essa descrição é incluída no prompt do bot.</p>
            </div>

            <div>
                <label className="block text-sm font-medium mb-3">Tom de voz</label>
                <div className="grid grid-cols-3 gap-3">
                    {TONS.map(ton => (
                        <div key={ton.value}
                            onClick={() => setData('tom_voz', ton.value as any)}
                            className={`border-2 rounded-xl p-3 cursor-pointer transition-colors ${
                                data.tom_voz === ton.value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'
                            }`}>
                            <p className="font-medium text-sm">{ton.label}</p>
                            <p className="text-xs text-gray-500 mt-0.5">{ton.desc}</p>
                        </div>
                    ))}
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium mb-1">Instruções extras</label>
                <textarea value={data.instrucoes_extras} onChange={e => setData('instrucoes_extras', e.target.value)}
                    rows={4} className="w-full border rounded px-3 py-2"
                    placeholder="Ex: Não agendar segunda de manhã. Sempre perguntar se é retorno ou primeira consulta. Aceitar apenas Unimed e Bradesco Saúde." />
                <p className="text-xs text-gray-400 mt-1">{(data.instrucoes_extras || '').length}/3000 caracteres</p>
            </div>

            <button type="submit" disabled={processing}
                className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:opacity-50">
                Salvar configurações do bot
            </button>
        </form>
    );
}
```

Adicionar `<BotConfigForm tenant={tenant} />` abaixo do form existente na página.

- [ ] **Step 2: Commit**

```bash
git add resources/js/Pages/Tenant/Configuracoes.tsx
git commit -m "feat: add Bot & IA config section to Configuracoes page"
```

---

## Task 16: Jobs automáticos — Lembretes v2 e verificação de trial

**Files:**
- Create: `app/Jobs/EnviarLembreteConsultaV2.php`
- Create: `app/Jobs/VerificarTrialExpiradoJob.php`
- Modify: `app/Console/Kernel.php` (se existir) ou `routes/console.php`

- [ ] **Step 1: Job de lembretes v2**

```php
<?php
// app/Jobs/EnviarLembreteConsultaV2.php
namespace App\Jobs;

use App\Models\Agendamento;
use App\Services\EvolutionApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarLembreteConsultaV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function handle(EvolutionApiService $evolution): void
    {
        $amanha = Carbon::tomorrow();

        $agendamentos = Agendamento::with(['tenant', 'profissional', 'servico', 'cliente'])
            ->whereDate('data_hora', $amanha)
            ->whereIn('status', ['agendado', 'confirmado'])
            ->where('lembrete_enviado', false)
            ->whereHas('tenant', fn ($q) => $q->where('ativo', true)->where('bot_ativo', true))
            ->get();

        foreach ($agendamentos as $ag) {
            $tenant   = $ag->tenant;
            $telefone = $ag->cliente?->telefone ?? $ag->cliente_telefone;

            if (! $telefone) continue;

            $horario      = Carbon::parse($ag->data_hora)->format('H:i');
            $profissional = $ag->profissional?->nome ?? '';
            $servico      = $ag->servico?->nome ?? '';
            $nomeCliente  = $ag->cliente?->nome ?? $ag->cliente_nome ?? 'cliente';
            $nomeNegocio  = $tenant->nome;

            $mensagem = "Olá {$nomeCliente}! 😊\n\n"
                . "Lembrando que você tem " . ($servico ? "*{$servico}*" : "um agendamento") . " amanhã"
                . ($horario ? " às *{$horario}*" : '')
                . ($profissional ? " com *{$profissional}*" : '')
                . " na *{$nomeNegocio}*.\n\n"
                . "Confirma sua presença? Responda *SIM* para confirmar ou nos avise caso precise remarcar. Até amanhã! 👋";

            $evolution->enviarMensagem($tenant->evolution_instance, $telefone, $mensagem);
            $ag->update(['lembrete_enviado' => true]);
        }
    }
}
```

- [ ] **Step 2: Job de verificação de trial**

```php
<?php
// app/Jobs/VerificarTrialExpiradoJob.php
namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class VerificarTrialExpiradoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Tenant::where('subscription_status', 'trial')
            ->where('trial_ends_at', '<', now())
            ->each(function (Tenant $tenant) {
                $tenant->update(['subscription_status' => 'blocked']);

                // Notificar o owner por e-mail
                $owner = $tenant->users()->wherePivot('papel', 'admin')->first();
                if ($owner) {
                    // \Mail::to($owner->email)->send(new \App\Mail\TrialExpiradoMail($tenant));
                    \Illuminate\Support\Facades\Log::info("Trial expirado: tenant #{$tenant->id} {$tenant->nome}");
                }
            });
    }
}
```

- [ ] **Step 3: Registrar schedules**

Verificar se existe `app/Console/Kernel.php`. Se não existir (Laravel 11 usa `routes/console.php`):

```php
// Adicionar em routes/console.php:
use App\Jobs\EnviarLembreteConsultaV2;
use App\Jobs\VerificarTrialExpiradoJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new EnviarLembreteConsultaV2)->dailyAt('09:00');
Schedule::job(new VerificarTrialExpiradoJob)->dailyAt('00:00');
```

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/EnviarLembreteConsultaV2.php app/Jobs/VerificarTrialExpiradoJob.php routes/console.php
git commit -m "feat: add v2 reminder job and trial expiry checker with schedule"
```

---

## Task 17: Atualizar Onboarding — wizard 4 passos (opcional mas recomendado)

> Este task é opcional se o onboarding de 2 passos atual for suficiente para MVP.

**Files:**
- Modify: `app/Http/Controllers/OnboardingController.php`
- Modify: `resources/js/Pages/Onboarding/Step1.tsx`

**Nota:** O onboarding atual já cria tenant + usuário (Step1) e oferece planos (Step2). Para transformar em wizard de 4 passos sem quebrar o que existe, manter o Step1 e Step2 existentes e adicionar passos pós-cadastro dentro do dashboard.

A abordagem recomendada é:
- Step1: dados do usuário + negócio (já existe)
- Step2: escolha de plano (já existe)
- Pós-cadastro (dentro do dashboard): wizard de "Configure seu bot" que aparece enquanto as seções de Profissionais, Serviços e WhatsApp não estiverem preenchidas

- [ ] **Step 1: Adicionar indicador de setup no DashboardController**

```php
// Em app/Http/Controllers/Tenant/DashboardController.php, adicionar ao array de retorno:

'setup_completo' => [
    'profissionais' => $tenant->profissionais()->where('ativo', true)->exists(),
    'servicos'      => $tenant->servicos()->where('ativo', true)->exists(),
    'whatsapp'      => $tenant->whatsapp_conectado,
    'bot_config'    => ! empty($tenant->ramo_negocio),
],
```

- [ ] **Step 2: Adicionar banner de setup no Dashboard.tsx**

```tsx
// No componente Tenant/Dashboard.tsx, adicionar antes dos cards:

{!setup_completo.profissionais || !setup_completo.servicos || !setup_completo.whatsapp ? (
    <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
        <h3 className="font-semibold text-yellow-800 mb-2">Complete a configuração do seu negócio</h3>
        <div className="space-y-1">
            {!setup_completo.bot_config && (
                <a href={route('tenant.configuracoes.index')} className="flex items-center gap-2 text-sm text-yellow-700 hover:underline">
                    <span>{!setup_completo.bot_config ? '⬜' : '✅'}</span> Configure o bot (nome, tom de voz, descrição)
                </a>
            )}
            {!setup_completo.profissionais && (
                <a href={route('tenant.profissionais.index')} className="flex items-center gap-2 text-sm text-yellow-700 hover:underline">
                    <span>⬜</span> Adicione profissionais com horários
                </a>
            )}
            {!setup_completo.servicos && (
                <a href={route('tenant.servicos.index')} className="flex items-center gap-2 text-sm text-yellow-700 hover:underline">
                    <span>⬜</span> Cadastre seus serviços
                </a>
            )}
            {!setup_completo.whatsapp && (
                <a href={route('tenant.whatsapp')} className="flex items-center gap-2 text-sm text-yellow-700 hover:underline">
                    <span>⬜</span> Conecte o WhatsApp
                </a>
            )}
        </div>
    </div>
) : (
    <div className="bg-green-50 border border-green-200 rounded-xl p-3 mb-6 text-sm text-green-700">
        ✅ Tudo configurado! O bot está pronto para receber agendamentos.
    </div>
)}
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Tenant/DashboardController.php resources/js/Pages/Tenant/Dashboard.tsx
git commit -m "feat: add setup progress banner to tenant dashboard"
```

---

## Task 18: Seeders v2 — dados de exemplo com nova estrutura

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Atualizar seeder com nova estrutura**

```php
// database/seeders/DatabaseSeeder.php
// Substituir conteúdo do método run():

public function run(): void
{
    $superAdmin = \App\Models\User::firstOrCreate(
        ['email' => 'admin@agendou.com'],
        [
            'name'           => 'Super Admin',
            'password'       => \Hash::make('password'),
            'is_super_admin' => true,
        ]
    );

    // Tenant: Clínica Dental
    $clinica = \App\Models\Tenant::firstOrCreate(
        ['slug' => 'clinica-dental-demo'],
        [
            'nome'               => 'Clínica Dental Demo',
            'ramo_negocio'       => 'Clínica odontológica',
            'descricao_negocio'  => 'Clínica especializada em odontologia estética e preventiva. Atendemos com carinho e modernidade.',
            'cidade'             => 'Porto Alegre',
            'endereco'           => 'Rua das Flores, 123',
            'nome_agente'        => 'Bia',
            'tom_voz'            => 'semiformal',
            'instrucoes_extras'  => 'Sempre perguntar se é retorno ou primeira consulta. Aceitar Unimed e particular.',
            'evolution_instance' => 'clinica-dental-demo',
            'subscription_status' => 'trial',
            'trial_ends_at'      => now()->addDays(14),
            'ativo'              => true,
            'bot_ativo'          => true,
            'horarios_funcionamento' => ['seg_sex' => '08:00-18:00', 'sab' => '08:00-12:00'],
        ]
    );

    $clinica->users()->syncWithoutDetaching([$superAdmin->id => ['papel' => 'admin']]);

    // Profissionais
    $joao = $clinica->profissionais()->firstOrCreate(['nome' => 'Dr. João Silva'], [
        'especialidades' => ['Clínica Geral', 'Estética Dental'],
        'ativo' => true,
    ]);
    $maria = $clinica->profissionais()->firstOrCreate(['nome' => 'Dra. Maria Souza'], [
        'especialidades' => ['Ortodontia', 'Implantes'],
        'ativo' => true,
    ]);

    // Horários (Seg-Sex, 08:00-18:00, slots de 30min)
    foreach ([$joao, $maria] as $prof) {
        for ($dia = 1; $dia <= 5; $dia++) {
            $prof->horarios()->firstOrCreate(['dia_semana' => $dia], [
                'hora_inicio' => '08:00',
                'hora_fim'    => '18:00',
                'duracao_slot' => 30,
            ]);
        }
    }

    // Serviços
    $clinica->servicos()->firstOrCreate(['nome' => 'Consulta'], ['descricao' => 'Consulta geral', 'valor_min' => 150, 'duracao_minutos' => 30, 'ativo' => true]);
    $clinica->servicos()->firstOrCreate(['nome' => 'Limpeza'], ['valor_min' => 120, 'duracao_minutos' => 60, 'ativo' => true]);
    $clinica->servicos()->firstOrCreate(['nome' => 'Clareamento'], ['valor_min' => 500, 'valor_max' => 800, 'duracao_minutos' => 90, 'ativo' => true]);

    // Opções extras
    $clinica->opcoes_extras()->firstOrCreate(['nome' => 'Unimed'], ['tipo' => 'convenio', 'ativo' => true]);
    $clinica->opcoes_extras()->firstOrCreate(['nome' => 'Particular'], ['tipo' => 'pagamento', 'ativo' => true]);
    $clinica->opcoes_extras()->firstOrCreate(['nome' => 'PIX'], ['tipo' => 'pagamento', 'ativo' => true]);
}
```

- [ ] **Step 2: Rodar seeder**

```bash
php artisan db:seed
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: update DatabaseSeeder with v2 structure (profissionais, servicos, opcoes)"
```

---

## Self-Review — Cobertura do Spec

### Spec coverage check:

| Requisito do spec | Task que implementa |
|---|---|
| `tenants` com campos bot config | Task 1 |
| `profissionais` table | Task 2 |
| `horarios_profissional` table | Task 2 |
| `servicos` table | Task 2 |
| `opcoes_extras` table | Task 2 |
| `clientes` table | Task 2 |
| `mensagens` table | Task 2 |
| `conversas` com status e cliente_id | Task 3 |
| `agendamentos` v2 com profissional_id/servico_id | Task 3 |
| Todos os Models novos | Task 4 |
| `Profissional::slotsDisponiveis()` | Task 4 |
| ClaudeAgentService com prompt dinâmico | Task 5 |
| Tom de voz no prompt | Task 5 |
| Instruções extras no prompt | Task 5 |
| AgendamentoService v2 | Task 6 |
| ProcessarMensagemWhatsapp job v2 | Task 7 |
| Verificação de duplicata por evolution_message_id | Task 7 |
| Transfer-to-human via `acao=transferir` | Task 7 |
| Rotas para novas entidades | Task 8 |
| CRUD de profissionais | Task 9 |
| CRUD de serviços | Task 9 |
| CRUD de opções extras | Task 9 |
| ConversaController (assumir/devolver/enviar) | Task 10 |
| Config bot (nome_agente, tom_voz, instrucoes) | Task 11 |
| Page Profissionais com editor de horários | Task 12 |
| Page Serviços | Task 13 |
| Page Conversas com chat real-time | Task 14 |
| Config Bot na UI | Task 15 |
| Job lembretes v2 | Task 16 |
| Job verificação trial | Task 16 |
| Schedules | Task 16 |
| Setup progress no dashboard | Task 17 |
| Seeders v2 | Task 18 |

### Gaps identificados:
- **OpcaoExtra/Index.tsx** — não foi incluída a página frontend (pode usar pattern similar ao Servicos)
- **Tenant/Clientes/Index.tsx** e **Show.tsx** — não foram incluídas (simples, podem ser criadas com pattern da AgendamentosTable)
- **VerificarAssinaturaAsaas job** — omitido por já existir lógica similar no AsaasWebhookController
- **EnviarLembreteConsultaV2 precisa substituir EnviarLembretesJob** no schedule — verificar `routes/console.php`

### Tipos consistentes:
- `Profissional::slotsDisponiveis()` retorna `array{hora: string, disponivel: bool}[]` → usado em `AgendamentoService::buscarHorariosDisponiveis()`
- `ClaudeAgentService::processar()` retorna `array{acao: string, resposta: string, dados: array}` → consumido em `ProcessarMensagemWhatsapp::handle()`
- `Conversa::registrarMensagem()` retorna `Mensagem` → usado em ProcessarMensagemWhatsapp
