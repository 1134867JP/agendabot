# Agendou

Plataforma SaaS multi-tenant de agendamento via WhatsApp com bot de IA. Estabelecimentos conectam seu WhatsApp Business e passam a receber agendamentos automaticamente — sem que o cliente precise instalar nada.

## Sumário

- [Visão geral](#visão-geral)
- [Stack](#stack)
- [Arquitetura](#arquitetura)
- [Banco de dados](#banco-de-dados)
- [Services](#services)
- [Jobs e filas](#jobs-e-filas)
- [Rotas](#rotas)
- [Middleware](#middleware)
- [Bot de IA](#bot-de-ia)
- [Planos e cobrança](#planos-e-cobrança)
- [Frontend](#frontend)
- [Configuração](#configuração)
- [Deploy](#deploy)
- [Testes](#testes)

---

## Visão geral

- **Cliente final** agenda pelo WhatsApp do estabelecimento em linguagem natural — sem instalar nada
- **Dono do estabelecimento** configura profissionais, serviços e horários pelo painel web
- **Bot** usa Claude API (Haiku 4.5) com tool use para conduzir a conversa e criar o agendamento
- **Atendente humano** pode assumir qualquer conversa a qualquer momento; ao enviar uma mensagem pelo painel, o takeover acontece automaticamente
- **Super admin** gerencia todos os tenants, visualiza logs em tempo real e monitora custo de IA por empresa

---

## Stack

| Camada | Tecnologia |
|--------|------------|
| Backend | Laravel 13 (PHP 8.3) |
| Frontend | React 18 + TypeScript + Inertia.js |
| Banco | PostgreSQL 16 |
| Fila | Laravel Queue por prioridade (database; Redis/Horizon compatível) |
| WhatsApp | Evolution API (WHATSAPP-BAILEYS) |
| IA | Anthropic Claude API — `claude-haiku-4-5-20251001` |
| Build | Vite + Tailwind CSS |
| Deploy | Docker Compose + GitHub Actions |
| Pagamentos | Asaas (gateway brasileiro) |

---

## Arquitetura

```
Cliente WhatsApp
      │  mensagem
      ▼
Evolution API ──── webhook ────► POST /webhook/{tenant-slug}
                                          │
                                          ▼
                                WebhookController
                                (identifica tenant, filtra fromMe)
                                          │
                                          ▼
                               ProcessarMensagemWhatsapp (Job)
                               tries=3, backoff=30/60/120s
                                          │
                     ┌────────────────────┼──────────────────────┐
                     ▼                    ▼                       ▼
             ClaudeAgentService    AgendamentoService    EvolutionApiService
             (tool use + caching)  (criar/cancelar/      (enviar resposta
                     │              reagendar)             WhatsApp)
                     ▼
               TokenUsage
             (log de custo por chamada)
```

**Fluxo de uma mensagem:**

1. Evolution API recebe mensagem e envia ao webhook `/webhook/{slug}`
2. `WebhookController` identifica o tenant pelo slug, filtra mensagens `fromMe`, e despacha `ProcessarMensagemWhatsapp`
3. O job verifica duplicatas por `evolution_message_id`, cria/atualiza `Cliente` e `Conversa`
4. Se a conversa está em atendimento humano, salva a mensagem e não aciona o Claude
5. O job verifica o limite de agendamentos via bot do plano e envia aviso ao dono caso necessário
6. Monta histórico das últimas 12 mensagens (ajustado para garantir que começa com `role=user`)
7. `ClaudeAgentService` processa via tool use (até 6 rodadas de agentic loop)
8. Registra `TokenUsage` e envia a resposta ao cliente via Evolution API

---

## Banco de dados

### Tabelas principais

| Tabela | Descrição |
|--------|-----------|
| `tenants` | Estabelecimentos (donos da conta SaaS) |
| `tenant_users` | Relação usuário ↔ tenant com papel (admin/operador) |
| `profissionais` | Barbeiros, fisioterapeutas, professores, etc. |
| `horarios_profissional` | Expediente por dia da semana (hora_inicio/hora_fim) |
| `servicos` | Corte, massagem, aula, etc. — com duração e faixa de preço |
| `opcoes_extras` | Convênios, formas de pagamento, produtos adicionais |
| `clientes` | Clientes finais (identificados pelo telefone WhatsApp) |
| `agendamentos` | Registros de agendamento com status e origem |
| `conversas` | Estado da conversa WhatsApp por cliente |
| `mensagens` | Histórico de mensagens por conversa |
| `token_usages` | Custo da Claude API por chamada e por tenant |
| `cobrancas_bot` | Registro de cobranças variáveis por agendamento via bot |
| `subscription_events` | Eventos de assinatura vindos do Asaas |
| `recursos` | Legado v1 — slots de tempo agendáveis genéricos |
| `horarios_funcionamento` | Legado v1 — horários de funcionamento por recurso |

### Campos relevantes de `tenants`

```
nome, slug, tipo_servico, evolution_instance, whatsapp_conectado
subscription_status, trial_ends_at, subscription_ends_at, plano
ramo_negocio, descricao_negocio, cidade, endereco, horarios_funcionamento
nome_agente, tom_voz, instrucoes_extras, bot_ativo
taxa_agendamento_bot, isento_cobranca
```

### Anti double-booking

Cada profissional usa `pg_advisory_xact_lock` na transação de criação/reagendamento para serializar escritas concorrentes. O conflito é verificado via range overlap:

```sql
-- Detecta agendamentos sobrepostos ao slot [inicio, fim)
WHERE profissional_id = ?
  AND status != 'cancelado'
  AND data_hora < :fim
  AND (data_hora + (duracao_minutos * INTERVAL '1 minute')) > :inicio
```

Em produção também existe uma constraint PostgreSQL `btree_gist` herdada da v1:
```sql
EXCLUDE USING gist (
    recurso_id WITH =,
    tstzrange(inicio, fim) WITH &&
) WHERE (status != 'cancelado')
```

---

## Services

### `ClaudeAgentService`

Cérebro do bot. Constrói o system prompt com dados reais do tenant (profissionais, serviços, opções extras, horários, tom de voz, instruções específicas) e executa um loop agentic de até 6 rodadas com as ferramentas abaixo.

**Ferramentas (tool use):**

| Ferramenta | O que faz |
|-----------|-----------|
| `buscar_slots` | Retorna horários disponíveis nos próximos N dias (máx 7) por profissional |
| `criar_agendamento` | Registra o agendamento — obrigatório antes de confirmar ao cliente |
| `confirmar_agendamento` | Confirma agendamento no status `agendado` → `confirmado` |
| `cancelar_agendamento` | Cancela o agendamento pendente do cliente |
| `listar_agendamentos_cliente` | Lista os próximos agendamentos ativos do cliente |
| `reagendar_agendamento` | Altera data/hora (e opcionalmente profissional/serviço) de um agendamento existente |
| `transferir_para_humano` | Muda `status_v2` da conversa para `aguardando_humano` |

**Prompt caching:** o bloco estático do system prompt (profissionais, serviços, regras) é marcado com `cache_control: ephemeral`, gerando ~70% de economia em cache hits.

**Regras críticas do prompt:**
- Jamais invente horários — use apenas os retornados por `buscar_slots`
- Nunca confirme agendamento sem chamar `criar_agendamento` com sucesso
- Mensagens curtas (máx 3 linhas)
- Após 2 tentativas sem entender ou cliente irritado → `transferir_para_humano`

### `AgendamentoService`

Responsável por criar, cancelar e reagendar agendamentos com isolamento de tenant e proteção de concorrência.

- `criarAgendamentoV2`: valida pertencimento do profissional e serviço ao tenant, verifica expediente do profissional no dia da semana, trava advisory, verifica conflito por range overlap e persiste
- `reagendar`: mesma lógica de lock e overlap, exclui o próprio agendamento da verificação
- `buscarHorariosDisponiveis`: itera profissionais ativos do tenant e agrega slots livres dos próximos N dias

### `EvolutionApiService`

Wrapper da Evolution API v2:

| Método | Endpoint |
|--------|----------|
| `enviarMensagem` | `POST /message/sendText/{instance}` |
| `criarInstancia` | `POST /instance/create` |
| `obterQrCode` | `GET /instance/connect/{instance}` |
| `statusInstancia` | `GET /instance/fetchInstances` |
| `configurarWebhook` | `POST /webhook/set/{instance}` |
| `desconectarInstancia` | `DELETE /instance/logout/{instance}` |

### `IntencaoService`

Pré-filtro leve que detecta respostas simples de confirmação ("sim", "ok", "confirma") ou cancelamento ("não", "cancela", "desisto") antes de chamar o Claude, reduzindo custo de API para casos triviais.

### `AsaasService`

Integração com gateway de pagamentos Asaas para criar clientes, cobranças e assinaturas.

---

## Jobs e filas

| Job | Responsabilidade |
|----|-----------------|
| `ProcessarMensagemWhatsapp` | Orquestra todo o fluxo do bot (tries=3, backoff 30/60/120s, timeout 90s) |
| `CreateEvolutionInstanceJob` | Cria instância na Evolution API após onboarding |
| `ExpirarConversasInativasJob` | Reseta conversas sem atividade há mais de 30 min para evitar estados corrompidos |
| `EnviarLembretesJob` | Envia lembrete de agendamento via WhatsApp (D-1) |
| `EnviarLembreteConsultaV2` | Versão v2 dos lembretes com template customizável |
| `BackupELimparHistoricoJob` | Limpa histórico antigo de mensagens para controlar espaço em disco |
| `SincronizarConversasWhatsappJob` | Sincroniza conversas e nomes via API do Evolution (com lock para evitar duplicatas) |
| `VerificarTrialExpiradoJob` | Verifica trials vencidos e envia e-mail de aviso |
| `GerarCobrancaBotJob` | Gera cobranças variáveis mensais por agendamento via bot |

Fila configurada com `QUEUE_CONNECTION=database`. As prioridades são `messages`, `notifications`,
`financial`, `sync`, `maintenance` e `default`. O worker de produção consome nessa ordem. Para
escalar, altere `QUEUE_CONNECTION=redis`, configure o Redis e adicione Horizon sem mudar os jobs.

## Confiabilidade, privacidade e integrações

- `/health` verifica banco e falhas recentes da fila; o deploy usa esse endpoint e restaura a imagem anterior em caso de falha.
- Pushes na branch `develop` podem publicar em homologação usando os secrets `STAGING_HOST`, `STAGING_USER` e `STAGING_SSH_KEY`.
- Contextos de log passam por mascaramento central de telefone, e-mail, nomes e conteúdo de mensagens.
- Clientes podem ter seus dados exportados e anonimizados, preservando somente registros financeiros sem identificação pessoal.
- Eventos operacionais medem tempo de resposta, falhas de Claude/Evolution/Google e receita originada pelo bot.
- Lista de espera, cobrança de sinal PIX, confirmação antecipada, no-show e sincronização Google Calendar fazem parte do domínio comercial.

### Google Calendar

Configure `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` e cadastre a URL de callback
`/painel/integracoes/google-calendar/callback` no Google Cloud. Tokens são criptografados no banco.

---

## Rotas

### Site público

```
GET  /          Landing page
GET  /precos    Página de preços
```

### Onboarding

```
GET  /cadastro              Passo 1: criar conta
POST /cadastro
GET  /cadastro/plano        Passo 2: escolher plano
POST /cadastro/checkout     Iniciar pagamento Asaas
GET  /cadastro/personalizar Passo 3: configurar tenant
POST /cadastro/personalizar
GET  /cadastro/sucesso      Tela de boas-vindas
POST /cadastro/pular        Pular pagamento (trial)
```

### Painel do estabelecimento (`/painel/*`, middleware: `auth`, `tenant`, `subscription`)

```
GET    /painel                       Dashboard (agendamentos do dia)
GET    /painel/agenda                Agenda visual por profissional
GET    /painel/agenda/disponibilidade Disponibilidade em JSON
GET    /painel/agendamentos          Lista com filtros
POST   /painel/agendamentos          Criar manual
PUT    /painel/agendamentos/{id}     Editar
PATCH  /painel/agendamentos/{id}/cancelar
PATCH  /painel/agendamentos/{id}/concluir
DELETE /painel/agendamentos/{id}
GET    /painel/agendamentos/exportar  CSV
GET    /painel/analytics             Relatórios
GET    /painel/profissionais         CRUD profissionais
POST   /painel/profissionais/{id}/horarios  Sincronizar expediente
GET    /painel/servicos              CRUD serviços
GET    /painel/opcoes-extras         CRUD opções extras
GET    /painel/clientes              Lista de clientes
GET    /painel/clientes/{id}         Perfil + histórico
GET    /painel/conversas             Chat (estilo WhatsApp Web)
POST   /painel/conversas/{id}/assumir       Atendente assume
POST   /painel/conversas/{id}/devolver      Devolver ao bot
POST   /painel/conversas/{id}/enviar        Enviar mensagem
GET    /painel/conversas/notificacoes       Notificações não lidas
GET    /painel/whatsapp              Status da conexão WhatsApp
GET    /painel/whatsapp/qrcode       QR Code em base64
GET    /painel/whatsapp/status       Status em JSON
POST   /painel/whatsapp/desconectar  Logout da instância
GET    /painel/configuracoes         Dados do negócio
PUT    /painel/configuracoes
PUT    /painel/configuracoes/bot     Configurar bot (nome, tom, instruções)
GET    /painel/cobranca/resumo       Custo variável do bot no mês
GET    /painel/equipe                Gerenciar membros da equipe
```

### Super admin (`/superadmin/*`, middleware: `auth`, `superadmin`)

```
GET  /superadmin              Dashboard geral
GET  /superadmin/tenants      Lista tenants (CRUD)
POST /superadmin/tenants/{id}/impersonar   Assumir conta para suporte
DELETE /superadmin/impersonar              Parar impersonar
PATCH  /superadmin/tenants/{id}/toggle-ativo
PATCH  /superadmin/tenants/{id}/toggle-isento
GET  /superadmin/agendamentos  Todos os agendamentos
GET  /superadmin/financeiro    Visão financeira
GET  /superadmin/logs          Logs em tempo real
GET  /superadmin/logs/json     Logs em JSON (paginado, filtro por nível)
GET  /superadmin/jobs          Fila de jobs (falhos)
POST /superadmin/jobs/{id}/retry
POST /superadmin/jobs/retry-all
GET  /superadmin/tokens        Custo Claude API por empresa
```

### Webhooks

```
POST /webhook/{tenantSlug}         WhatsApp (Evolution API)
POST /webhook/asaas                Asaas (pagamentos)
```

---

## Middleware

| Middleware | Função |
|-----------|--------|
| `EnsureHasTenant` | Verifica se há `tenant_id` na sessão, injeta `app('tenant')` e compartilha `$currentTenant` com todas as views |
| `CheckSubscription` | Bloqueia acesso ao painel se a assinatura está vencida — redireciona para `/renovar` |
| `EnsureSuperAdmin` | Verifica flag `is_super_admin` no usuário |
| `HandleInertiaRequests` | Compartilha dados globais com o frontend via Inertia (usuário, tenant, plano, notificações) |

---

## Bot de IA

### Fluxo para novo agendamento

```
Cliente: "quero marcar um horário"
Bot: chama buscar_slots → apresenta opções reais
Cliente: "quarta às 10h com o João"
Bot: "Ok! Corte com João em 01/07 às 10:00, certo?"
Cliente: "sim"
Bot: chama criar_agendamento → sucesso → "Agendado! ✅"
```

### Fluxo para reagendamento

```
Cliente: "preciso mudar meu horário"
Bot: chama listar_agendamentos_cliente → exibe lista
Cliente: "quero mudar o de quarta"
Bot: chama buscar_slots → exibe novos horários disponíveis
Cliente: "pode ser sexta às 14h"
Bot: chama reagendar_agendamento com agendamento_id + nova data/hora → sucesso
```

### Fluxo de transferência para humano

```
Cliente: [mensagem incompreensível x2] ou "quero falar com atendente"
Bot: chama transferir_para_humano
     → conversa.status_v2 = 'aguardando_humano'
     → próximas mensagens do cliente são salvas sem acionamento do Claude
Atendente: abre conversa no painel, envia mensagem
     → conversa.status_v2 = 'em_atendimento_humano'
Atendente: clica "Devolver ao bot"
     → conversa.status_v2 = 'ativa'
```

### Configuração do bot por tenant

No painel em `/painel/configuracoes/bot`:

| Campo | Descrição |
|-------|-----------|
| `nome_agente` | Nome do assistente (ex: "Assistente da Barbearia do João") |
| `tom_voz` | `neutro` \| `formal` \| `descontraido` |
| `instrucoes_extras` | Texto livre com regras específicas do negócio |
| `bot_ativo` | Liga/desliga o bot (se desligado, mensagens são ignoradas) |

---

## Planos e cobrança

### Planos disponíveis

| Plano | Mensalidade | Profissionais | Limite bot/mês | Taxa/agendamento bot |
|-------|------------|---------------|---------------|---------------------|
| Starter | R$ 49,90 | 3 | 100 | R$ 0,40 |
| Pro | R$ 99,90 | 10 | 350 | R$ 0,30 |
| Business | R$ 179,90 | Ilimitado | Ilimitado | R$ 0,20 |

- **Trial:** novos tenants entram em trial por N dias (configurável)
- **Aviso de 80%:** quando o tenant atinge 80% do limite mensal, o dono recebe aviso via WhatsApp
- **Pausa do bot:** ao atingir o limite, o bot responde que o sistema está pausado e pede contato direto
- **Isentos:** tenants com `isento_cobranca=true` não têm limite e não são cobrados pela taxa variável

### Custo da Claude API

Com Haiku 4.5 e prompt caching (~70% de cache hit rate):

| Tipo de token | Preço |
|--------------|-------|
| Input | US$ 1,00 / MTok |
| Output | US$ 5,00 / MTok |
| Cache write | US$ 1,25 / MTok |
| Cache read | US$ 0,10 / MTok |

Custo estimado: **~US$ 0,0004 por mensagem**. O painel `/superadmin/tokens` exibe custo real por empresa, input/output tokens, cache hits e economia gerada pelo cache.

---

## Frontend

### Páginas públicas

- `Home.tsx` — landing page com features, depoimentos e CTA
- `Precos.tsx` — tabela de planos com destaque no Pro

### Onboarding

- Fluxo de 3 passos: criar conta → escolher plano → personalizar tenant

### Painel do estabelecimento (`resources/js/Pages/Tenant/`)

| Página | Descrição |
|--------|-----------|
| `Dashboard.tsx` | Agendamentos do dia + métricas rápidas |
| `Agenda.tsx` | Calendário semanal por profissional |
| `Agendamentos/Index.tsx` | Tabela com filtros de data/status, cancelar, concluir, criar manual |
| `Analytics.tsx` | Relatórios de volume e receita |
| `Profissionais/` | CRUD com expediente por dia da semana |
| `Servicos/` | CRUD com duração, faixa de preço, flag de avaliação prévia |
| `OpcaoExtra/` | CRUD de convênios/pagamentos |
| `Clientes/` | Lista e perfil com histórico |
| `Conversas/Index.tsx` | Chat estilo WhatsApp Web com busca, badge de não lidas, assumir/devolver |
| `WhatsApp.tsx` | QR Code com polling de status, botão desconectar |
| `Configuracoes/Index.tsx` | Dados do negócio + configurações do bot |

### Painel super admin (`resources/js/Pages/SuperAdmin/`)

| Página | Descrição |
|--------|-----------|
| `Dashboard.tsx` | Visão geral de tenants e agendamentos |
| `Logs.tsx` | Logs em tempo real com filtro por nível (ERROR/WARNING/INFO/DEBUG) |
| `TokenUsage.tsx` | Custo da IA por empresa: tokens, cache hit rate e economia |
| `Tenants/` | CRUD completo + toggle ativo/isento + impersonar |
| `Jobs/Index.tsx` | Fila de jobs falhos com retry individual ou em massa |

### Componentes reutilizáveis

| Componente | Função |
|-----------|--------|
| `WhatsAppConector.tsx` | Badge de status + QR Code com polling automático |
| `AgendamentosTable.tsx` | Tabela responsiva com filtros, badges de status e ações |
| `RecursoForm.tsx` | Formulário de recurso legado v1 com horários de funcionamento |
| `NotificacoesBell.tsx` | Sino com dropdown das últimas 5 mensagens não lidas |
| `ToastNovaMensagem.tsx` | Toast quando chega mensagem nova em conversa ativa |
| `TipoServicoSelector.tsx` | Selector visual de tipo de serviço no onboarding |
| `ConfirmModal.tsx` | Modal de confirmação genérico |
| `ThemeProvider.tsx` | Suporte a tema dark/light |

---

## Configuração

### Pré-requisitos

- Docker e Docker Compose
- Instância da [Evolution API](https://github.com/EvolutionAPI/evolution-api) acessível
- Chave da [Anthropic API](https://console.anthropic.com/)
- Conta no [Asaas](https://asaas.com/) (opcional — apenas para cobrança)

### Variáveis de ambiente

Copie `.env.example` para `.env` e preencha:

```env
APP_NAME=Agendou
APP_URL=https://seu-dominio.com

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=agendabot
DB_USERNAME=postgres
DB_PASSWORD=senha-segura

QUEUE_CONNECTION=database

EVOLUTION_API_URL=http://evolution-api:8080
EVOLUTION_API_KEY=sua-chave-global

CLAUDE_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-haiku-4-5-20251001

ASAAS_API_KEY=
ASAAS_SANDBOX=true

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_FROM_ADDRESS=noreply@agendabot.com
```

### Subir com Docker

```bash
docker compose up -d

# Primeira vez: migrar banco e criar dados de exemplo
docker exec -it agendabot-app php artisan migrate --seed
```

O seeder cria:
- **Super admin:** `admin@agendabot.com` / `password`
- Dois tenants de exemplo (Barbearia do João e Arena Sports) com profissionais e horários

### Desenvolvimento local

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed

# Inicia Laravel, queue worker, log viewer e Vite simultaneamente
composer run dev
```

---

## Deploy

O arquivo `.github/workflows/deploy.yml` faz deploy automático via SSH no push para `master`.

**Secrets necessários no GitHub:** `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`

O script `deploy.sh` executa no servidor:
1. `git pull`
2. Build da imagem Docker sem cache
3. Recria o container `app` com `--force-recreate`
4. Aguarda o app subir (health check com `php artisan --version`)
5. Roda migrações pendentes
6. Recria worker e scheduler com a nova imagem
7. Limpa imagens antigas

---

## Estrutura de arquivos

```
app/
├── Console/Commands/
│   └── ReconfigureWebhooks.php      # Reconfigura webhooks de todos os tenants
├── Exceptions/
│   └── HorarioIndisponivelException.php
├── Http/
│   ├── Controllers/
│   │   ├── Tenant/                  # Todos os controllers do painel do dono
│   │   │   ├── AgendaController.php
│   │   │   ├── AgendamentoController.php
│   │   │   ├── AnalyticsController.php
│   │   │   ├── ClienteController.php
│   │   │   ├── CobrancaController.php
│   │   │   ├── ConfiguracaoController.php
│   │   │   ├── ConversaController.php
│   │   │   ├── EquipeController.php
│   │   │   ├── HorarioController.php
│   │   │   ├── HorarioProfissionalController.php
│   │   │   ├── OpcaoExtraController.php
│   │   │   ├── ProfissionalController.php
│   │   │   ├── RecursoController.php
│   │   │   ├── ServicoController.php
│   │   │   └── WhatsAppController.php
│   │   ├── SuperAdmin/              # Painel super admin
│   │   │   ├── AgendamentoController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── FinanceiroController.php
│   │   │   ├── JobsController.php
│   │   │   ├── LogController.php
│   │   │   ├── TenantController.php
│   │   │   └── TokenUsageController.php
│   │   ├── AsaasWebhookController.php
│   │   ├── DashboardController.php
│   │   ├── LandingController.php
│   │   ├── OnboardingController.php
│   │   ├── SubscriptionController.php
│   │   ├── TenantController.php
│   │   └── WebhookController.php    # Entrada do bot WhatsApp
│   └── Middleware/
│       ├── CheckSubscription.php
│       ├── EnsureHasTenant.php
│       └── EnsureSuperAdmin.php
├── Jobs/
│   ├── ProcessarMensagemWhatsapp.php
│   ├── CreateEvolutionInstanceJob.php
│   ├── ExpirarConversasInativasJob.php
│   ├── EnviarLembretesJob.php
│   ├── EnviarLembreteConsultaV2.php
│   ├── BackupELimparHistoricoJob.php
│   ├── SincronizarConversasWhatsappJob.php
│   ├── VerificarTrialExpiradoJob.php
│   └── GerarCobrancaBotJob.php
├── Models/
│   ├── Tenant.php
│   ├── Profissional.php
│   ├── HorarioProfissional.php
│   ├── Servico.php
│   ├── OpcaoExtra.php
│   ├── Cliente.php
│   ├── Agendamento.php
│   ├── Conversa.php
│   ├── Mensagem.php
│   ├── TokenUsage.php
│   ├── CobrancaBot.php
│   ├── SubscriptionEvent.php
│   └── Recurso.php (legado v1)
└── Services/
    ├── ClaudeAgentService.php       # IA + tool use + prompt caching
    ├── AgendamentoService.php       # Criar/cancelar/reagendar com anti double-booking
    ├── EvolutionApiService.php      # WhatsApp via Evolution API
    ├── IntencaoService.php          # Pré-filtro de confirmações simples
    └── AsaasService.php             # Gateway de pagamentos

resources/js/
├── Pages/
│   ├── Home.tsx, Precos.tsx         # Site público
│   ├── Onboarding/                  # Fluxo de cadastro
│   ├── Tenant/                      # Painel do estabelecimento
│   │   ├── Dashboard.tsx
│   │   ├── Agenda.tsx
│   │   ├── Conversas/Index.tsx
│   │   ├── Profissionais/, Servicos/, Agendamentos/
│   │   └── WhatsApp.tsx
│   └── SuperAdmin/
│       ├── Dashboard.tsx
│       ├── Logs.tsx
│       ├── TokenUsage.tsx
│       └── Jobs/Index.tsx
└── Components/
    ├── WhatsAppConector.tsx
    ├── AgendamentosTable.tsx
    ├── NotificacoesBell.tsx
    └── ToastNovaMensagem.tsx

config/
├── plans.php     # Definição de planos Starter/Pro/Business
└── services.php  # Chaves Claude API e Evolution API

routes/
├── web.php       # Todas as rotas web
├── webhooks.php  # Webhooks WhatsApp e Asaas
└── auth.php      # Rotas de autenticação (Breeze)
```

---

## Testes

```bash
php artisan test
```

```
tests/Feature/
├── AgendamentoTest.php    # Criação, double-booking, cancelamento
├── WebhookTest.php        # Recepção e roteamento de mensagens
├── Auth/                  # Testes de autenticação (Breeze)
└── ProfileTest.php
```
