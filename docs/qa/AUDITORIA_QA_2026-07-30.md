# Auditoria exploratória e correções — Agendou

Data: 30/07/2026
Repositório: `1134867JP/agendabot`
Branch e commit de origem: `master` / `fa1e9ed`
Ambiente: Docker, PHP 8.3, PostgreSQL 16, Node 20, Chrome 151 e Windows
Conta GitHub/Chrome confirmada: `1134867JP`
Perfis efetivamente usados: visitante, administrador do tenant, operador e superadministrador

Este relatório registra o comportamento do commit original antes das correções, as alterações realizadas e o resultado dos retestes. Nenhuma alteração foi enviada ao GitHub.

## 1. Resumo executivo

O commit original não estava pronto para produção. Foram encontrados 12 defeitos: 8 de alta severidade, 3 de média e 1 de baixa. Entre os impactos estavam dois módulos inteiros retornando HTTP 500, impossibilidade de criar reservas no modelo atual de profissionais, exposição visual do painel ao voltar após logout, falha técnica na criação de tenant quando a Evolution API estava indisponível, risco de exposição de segredos nos logs e build Docker não reproduzível.

Todos os 12 achados foram corrigidos no checkout local e retestados de forma proporcional ao risco. O estado final passou em:

- 176 testes automatizados, com 704 asserções;
- Laravel Pint nos 14 arquivos PHP alterados;
- análise TypeScript;
- build Vite de produção;
- build completo da imagem Docker;
- `git diff --check`;
- retestes funcionais no Chrome da conta `1134867JP`.

O lint PHP global ainda falha em 84 arquivos preexistentes e fora do escopo das correções. Integrações externas reais, carga volumétrica, navegadores adicionais e cenários de infraestrutura não puderam ser certificados.

Decisão: o commit original não deve ser liberado. O checkout corrigido pode seguir para staging/homologação, mas ainda não deve receber liberação irrestrita para produção sem validar Evolution, Asaas, IA, e-mail, workers/scheduler, carga e navegadores adicionais.

## 2. Telas e fluxos testados

### Público e autenticação

- Página inicial e preços;
- cadastro/onboarding por inspeção de rotas e cobertura automatizada;
- login positivo, campos vazios, e-mail/senha inválidos e mensagem genérica;
- recuperação, redefinição e alteração de senha pela suíte automatizada;
- confirmação de senha sensível;
- logout, múltiplas abas, recarregamento e botão Voltar;
- acesso direto a URLs protegidas e redirecionamentos.

### Painel do tenant

- dashboard/Hoje;
- agenda em calendário e lista;
- criação manual, filtros, validações, conflitos e regras de horário;
- clientes: lista, busca, cadastro, detalhe e início de agendamento;
- conversas, notificações e simulador do bot;
- analytics/desempenho;
- configurações do estabelecimento;
- espaços e recursos;
- profissionais e horários;
- serviços;
- opções extras;
- equipe;
- regras de agendamento;
- triagem;
- WhatsApp.

### Superadmin

- dashboard e métricas;
- tenants: lista, criação, edição por cobertura, ativação/desativação por cobertura e impersonação por cobertura;
- agenda global;
- financeiro;
- logs;
- jobs e fila;
- uso de tokens de IA;
- confirmação de senha antes de operações sensíveis.

### APIs, integrações e segurança

- isolamento multitenant e tentativas de IDOR;
- webhook autenticado, sem token, token inválido, replay e rate limit;
- concorrência de reservas;
- cobrança, assinatura, trial e eventos duplicados;
- falhas simuladas da Evolution API;
- proteção CSRF, mass assignment e permissões por rota;
- mascaramento de segredos em contexto de log;
- health check de banco, fila e jobs falhos.

## 3. Matriz de testes executados

| ID | Módulo/tela | Perfil | Cenário e pré-condição/passos | Esperado | Obtido final | Status | Evidência | Gravidade/prioridade |
|---|---|---|---|---|---|---|---|---|
| T-001 | Home | Visitante | Abrir em 320×568, 375×667, 390×844, 768×1024, 1366×768 e 1920×1080 | Conteúdo legível e sem overflow global | Layout estável nas seis resoluções | Aprovado | Chrome/snapshots | — |
| T-002 | Login | Visitante | Enviar vazio, e-mail inexistente e senha incorreta | Bloquear vazio e não revelar qual credencial falhou | Validação e mensagem genérica corretas | Aprovado | Chrome | — |
| T-003 | Login | Admin tenant | Entrar com credenciais válidas | Redirecionar ao painel correto | `/painel` | Aprovado | Chrome | — |
| T-004 | Senha | Usuário | Recuperar, redefinir, alterar e confirmar senha | Tokens, validações e redirects corretos | Cobertura automatizada verde | Aprovado automatizado | suíte PHP | — |
| T-005 | Sessão | Admin tenant | Duas abas; sair em uma e recarregar a outra | Segunda aba não acessa painel | Redirecionada ao login | Aprovado | Chrome | — |
| T-006 | Sessão | Admin tenant | Sair e usar Voltar | Não reapresentar dados protegidos | Login exibido; painel não reaparece | Aprovado após correção | Chrome | Alta/P1 |
| T-007 | Rotas protegidas | Visitante/operador | Acessar painel/configuração diretamente | 302 para login ou 403 conforme perfil | Comportamento correto | Aprovado | Chrome + suíte | — |
| T-008 | Multitenancy | Vários | Alterar IDs de clientes, serviços, profissionais, reservas e conversas | Nenhum acesso cruzado | Bloqueado por escopo/403/404 | Aprovado automatizado | testes de isolamento | — |
| T-009 | Perfis | Operador | Abrir clientes e tentar configuração diretamente | Operação permitida; configuração negada | Clientes 200; configuração 403 | Aprovado | Chrome | — |
| T-010 | Opções extras | Admin tenant | Abrir, listar e usar rota de CRUD | Módulo renderiza e respeita tenant | Tela funcional com quatro opções seed | Aprovado após correção | Chrome + regressão | Alta/P1 |
| T-011 | Agenda global | Superadmin | Abrir rota e filtros com base vazia | Página funcional e estado vazio explicativo | Página renderizada com filtros | Aprovado após correção | Chrome + regressão | Alta/P1 |
| T-012 | Reserva manual | Admin/operador | Tenant com profissionais/serviços e sem recurso legado | Permitir profissional/recurso e serviço | Dois profissionais e seis serviços disponíveis | Aprovado após correção | Chrome + suíte | Alta/P1 |
| T-013 | Cliente → reserva | Admin tenant | Criar cliente e clicar Novo agendamento | Nome e telefone previamente preenchidos | Dados preenchidos e isolados por tenant | Aprovado após correção | Chrome + regressão | Média/P2 |
| T-014 | Reserva inválida | Operador | Telefone `123` e envio completo | Erro claro junto ao campo | Erro de telefone visível; campos nomeados | Aprovado após correção | Chrome | Média/P2 |
| T-015 | Regras de agenda | Vários | Passado, fora do expediente, intervalo, bloqueio e cruzamento | Rejeitar horários inválidos | Rejeições cobertas e verdes | Aprovado automatizado | suíte PHP | — |
| T-016 | Concorrência | Vários | Duas reservas para o mesmo horário | Apenas uma reserva persiste | Constraint/serviço impedem duplicidade | Aprovado automatizado | suíte PHP/PostgreSQL | — |
| T-017 | Serviços/profissionais | Admin tenant | CRUD, associação, duração e incompatibilidade | Regras e escopo preservados | Cobertura existente verde | Aprovado automatizado e smoke UI | suíte + Chrome | — |
| T-018 | Clientes | Admin tenant | Telefone curto e válido, busca e detalhe | Validar e fornecer feedback | Erro no curto; válido criado | Aprovado | Chrome | — |
| T-019 | Conversas/WhatsApp | Admin tenant | Abrir lista, notificações e falha ao conectar | UI permanece funcional e erro é operacional | Mensagem segura sem stack trace | Aprovado após correção | Chrome + regressão | Alta/P1 |
| T-020 | Bot | Admin tenant | Simulador, triagem, debounce, contexto e falhas | Isolamento e regras preservados | Suíte verde | Aprovado automatizado/smoke | suíte + Chrome | — |
| T-021 | Cobrança | Vários | Trial, ativa, vencida, renovação, eventos e webhooks repetidos | Estado e idempotência corretos | Suíte verde | Aprovado automatizado | suíte PHP | — |
| T-022 | Tenant sensível | Superadmin | Abrir criação sem confirmação recente | Confirmar antes de preencher | Redireciona primeiro para confirmação | Aprovado após correção | Chrome + regressão | Média/P2 |
| T-023 | Criação de tenant | Superadmin | Evolution ausente e fila em banco | Criar localmente, enfileirar setup e não gerar 500 | Tenant criado; job aguardando; zero falhos | Aprovado após correção | Chrome + regressão | Alta/P1 |
| T-024 | Logs | Superadmin | Contextos aninhados com token/senha/cookie | Segredos completamente redigidos | `[REDACTED]` antes da resposta JSON | Aprovado após correção | testes unitários | Alta/P1 |
| T-025 | Jobs | Superadmin | Inspecionar fila após criar tenant | Job visível, sem falha síncrona | `CreateEvolutionInstanceJob` aguardando | Aprovado | Chrome | — |
| T-026 | Polling | Admin tenant | Observar dashboard e conversas por múltiplos ciclos | Uma requisição por intervalo | Uma chamada a cada ~5 s | Aprovado após correção | access log | Baixa/P3 |
| T-027 | Docker | Sistema | Build com `vendor`/`node_modules` locais | Contexto enxuto e build reproduzível | Contexto 58 kB incremental; imagem concluída | Aprovado após correção | Docker BuildKit | Alta/P1 |
| T-028 | PHP | Sistema | Suíte completa em PostgreSQL 16 | Zero falhas | 176 testes/704 asserções | Aprovado | Artisan | — |
| T-029 | PHP alterado | Sistema | Pint nos 14 arquivos PHP alterados | Zero divergências | Passou | Aprovado | Pint | — |
| T-030 | PHP global | Sistema | Pint em 262 arquivos | Zero divergências | 84 arquivos preexistentes fora do padrão | Falhou — dívida técnica | Pint | Baixa/P3 |
| T-031 | Frontend | Sistema | TypeScript e Vite de produção | Zero erro de tipo/build | Build concluído | Aprovado | `npm run build` | — |
| T-032 | Integridade do diff | Sistema | Verificar whitespace e conflitos | Nenhum erro | `git diff --check` limpo | Aprovado | Git | — |

## 4. Bugs encontrados, por severidade

### Alta / P1

#### BUG-001 — Opções extras retornava HTTP 500

- Ambiente: `/painel/opcoes-extras`, Chrome 151, admin tenant, commit `fa1e9ed`.
- Pré-condição: tenant ativo.
- Passos: autenticar e abrir a URL.
- Atual: `ViteException`; o componente Inertia não existia.
- Esperado: listar e administrar opções extras.
- Evidência: erro de manifesto para `Tenant/OpcaoExtra/Index.tsx`.
- Impacto: módulo inteiro indisponível e possível stack trace em debug.
- Causa: controller/rota sem componente correspondente.
- Correção: página completa criada e adicionada ao hub de configurações.
- Regressão: teste de escopo por tenant e reteste no Chrome.
- Estado: corrigido.

#### BUG-002 — Agenda global do superadmin retornava HTTP 500

- Ambiente: `/superadmin/agendamentos`, superadmin.
- Passos: autenticar e abrir a rota.
- Atual: componente `SuperAdmin/Agendamentos.tsx` ausente.
- Esperado: tabela paginada e filtrável.
- Impacto: auditoria global de reservas indisponível.
- Causa: rota/controller entregues sem frontend.
- Correção: página, filtros, paginação, relações e item de menu.
- Regressão: resposta Inertia automatizada e Chrome.
- Estado: corrigido.

#### BUG-003 — Reserva manual ignorava profissionais e serviços

- Ambiente: tenant Odonto Excellence, dois profissionais, seis serviços e nenhum recurso legado.
- Passos: abrir `/painel/agendamentos?novo=1`.
- Atual: seletor obrigatório de recurso vazio; fluxo impossível.
- Esperado: escolher profissional ou recurso e serviço compatível.
- Impacto: reserva manual bloqueada no modelo atual.
- Causa: frontend ainda usava o modelo legado.
- Correção: controller fornece profissionais/serviços; modal aceita ambos, filtra serviços e usa a duração configurada.
- Regressão: cenários de criação válida, incompatibilidade, horário e duração.
- Estado: corrigido.

#### BUG-004 — Voltar após logout restaurava painel protegido

- Ambiente: Chrome 151, sessão tenant.
- Passos: entrar, sair e pressionar Voltar.
- Atual: painel reaparecia pelo histórico/BFCache; recarregar redirecionava ao login.
- Esperado: nenhuma tela protegida visível.
- Impacto: exposição visual em dispositivo compartilhado.
- Causa: restauração Inertia/BFCache sem revalidação de sessão.
- Correção: limpeza do histórico Inertia no logout, revalidação em `pageshow` e reload de entradas protegidas em `popstate`.
- Regressão: logout/Voltar no Chrome e teste de sessão.
- Estado: corrigido; Voltar termina em `/login`.

#### BUG-005 — Falha da Evolution gerava HTTP 500 na criação de tenant

- Ambiente: superadmin, Evolution URL ausente/indisponível.
- Passos: confirmar senha e criar tenant.
- Atual: `ConnectionException`, rollback e stack trace técnico.
- Esperado: operação local consistente e falha externa assíncrona/operacional.
- Impacto: cadastro bloqueado pela disponibilidade de terceiro.
- Causa: chamada externa síncrona dentro da transação e respostas HTTP não verificadas.
- Correção: criação transacional local e `CreateEvolutionInstanceJob`; erro de domínio específico; validação de configuração/resposta.
- Regressão: fila fake, HTTP fake 500 e criação real no ambiente QA.
- Estado: corrigido.

#### BUG-006 — QR Code do WhatsApp expunha falha de integração

- Ambiente: `/painel/whatsapp`, Evolution não configurada.
- Passos: clicar Conectar WhatsApp.
- Atual: exceção de conexão podia propagar como 500.
- Esperado: HTTP 503 e mensagem operacional.
- Impacto: tela quebrada e detalhes internos em debug.
- Causa: exceções externas sem fronteira de domínio.
- Correção: tratamento de `EvolutionApiException`/`ConnectionException`, log seguro e resposta 503.
- Regressão: teste JSON e reteste do botão no Chrome.
- Estado: corrigido.

#### BUG-007 — Contextos de log podiam revelar credenciais

- Ambiente: painel de logs do superadmin.
- Passos: registrar contexto com token/senha/cookie aninhados e abrir JSON.
- Atual: contexto era devolvido sem mascaramento suficiente.
- Esperado: credenciais totalmente redigidas.
- Impacto: exposição de tokens e segredos a operadores de log.
- Causa: `LogController` não aplicava `DataMasker`; lista de chaves era incompleta.
- Correção: mascaramento recursivo, redação completa e remoção de PII de mensagem textual conhecida.
- Regressão: teste unitário de chaves aninhadas.
- Estado: corrigido.

#### BUG-008 — Build Docker incluía dependências locais

- Ambiente: `docker build .` após instalar dependências no host.
- Passos: construir imagem.
- Atual: contexto acima de 200 MB e erro em `node_modules/.bin`.
- Esperado: somente fontes/manifests no contexto.
- Impacto: CI e build local não reproduzíveis.
- Causa: ausência de `.dockerignore`.
- Correção: exclusão de `.git`, `.env`, dependências, builds, caches, logs, testes e artefatos QA.
- Regressão: imagem de produção construída com sucesso.
- Estado: corrigido.

### Média / P2

#### BUG-009 — Cliente era perdido ao iniciar reserva

- Ambiente: detalhe de cliente → Novo agendamento.
- Atual: URL continha telefone, mas modal abria vazio.
- Esperado: nome e telefone preenchidos, sem cruzar tenants.
- Impacto: retrabalho e risco de digitação.
- Causa: query não era resolvida no controller/modal.
- Correção: lookup limitado ao tenant e props iniciais.
- Regressão: casos positivo e cross-tenant, mais Chrome.
- Estado: corrigido.

#### BUG-010 — Confirmação de senha descartava formulário de tenant

- Ambiente: primeira operação sensível do superadmin.
- Passos: preencher cadastro e enviar sem confirmação recente.
- Atual: redirecionava depois do preenchimento e perdia dados.
- Esperado: confirmar antes de mostrar o formulário.
- Causa: middleware apenas no POST.
- Correção: `password.confirm` também nos GETs de criação/edição.
- Regressão: rota redireciona antes do formulário; depois da confirmação abre vazia e pronta.
- Estado: corrigido.

#### BUG-011 — Labels e erros não eram associados aos campos

- Ambiente: clientes, conversas, serviços, equipe, tenant e reserva manual.
- Atual: snapshots expunham textboxes sem nome; erros de telefone/início/fim da reserva não apareciam junto aos campos.
- Esperado: `label`/`id` associados e erro claro próximo ao campo.
- Impacto: leitores de tela, voz e usuário não identificavam o problema.
- Causa: labels sem `htmlFor` e ausência de renderização de erros no modal.
- Correção: IDs, `htmlFor` e mensagens de validação.
- Regressão: telefone `123` mostra erro e Data/Início/Telefone têm nome acessível no Chrome.
- Estado: corrigido.

### Baixa / P3

#### BUG-012 — Polling duplicado de notificações

- Ambiente: qualquer painel do tenant.
- Passos: observar access log por ciclos de cinco segundos.
- Atual: layout e sidebar instanciavam o mesmo hook, gerando duas requisições idênticas; Conversas podia adicionar uma terceira.
- Esperado: uma consulta por ciclo.
- Impacto: carga desnecessária multiplicada por sessões ativas.
- Causa: `useNotificacoes` duplicado.
- Correção: estado produzido no layout e repassado à sidebar; na tela de Conversas, somente a própria tela atualiza.
- Regressão: logs mostram uma chamada por ciclo no dashboard e em Conversas.
- Estado: corrigido.

## 5. Vulnerabilidades encontradas

- Exposição de dados protegidos pelo histórico após logout: corrigida.
- Possível vazamento de credenciais no painel de logs: corrigido.
- Stack trace técnico em falhas externas quando `APP_DEBUG=true`: os caminhos encontrados foram tratados; produção continua obrigada a usar `APP_DEBUG=false`.
- Nenhum IDOR confirmado nos recursos cobertos. Testes automatizados verificaram isolamento de tenants, existência limitada por tenant e permissões diretas.
- Webhooks cobertos rejeitam ausência/token inválido, repetição/replay e excesso de requisições.
- Nenhuma evidência de SQL injection, XSS armazenado, CSRF, mass assignment ou SSRF foi confirmada na cobertura executada.
- Auditorias online completas de CVEs de Composer/npm não foram executadas; portanto, não há certificação de ausência de vulnerabilidades em dependências.

## 6. Interface e responsividade

- Home, dashboard tenant e lista de tenants foram exercitados nas seis resoluções solicitadas.
- Não foi observado overflow horizontal global. Tabelas largas do superadmin usam rolagem interna.
- Navegação móvel, bottom navigation, ações rápidas, cards e modais permaneceram utilizáveis.
- Estados vazios de agenda, clientes, agenda global e jobs fornecem explicação.
- Feedback de criação de cliente e tenant foi validado.
- Labels dos formulários encontrados foram corrigidos.
- Não foi feita medição instrumental WCAG de contraste, navegação completa somente por teclado ou leitura com NVDA/JAWS/VoiceOver; esses itens permanecem parciais.

## 7. Performance

- Polling duplicado de notificações foi removido; access log final registra uma chamada por intervalo de aproximadamente cinco segundos.
- Build Docker final: JS principal 348,59 kB bruto/114,12 kB gzip; CSS 71,02 kB/13,52 kB gzip; Agenda 41,32 kB/9,29 kB gzip; AppLayout 31,24 kB/8,93 kB gzip.
- Agenda global agora usa eager loading de tenant, profissional e serviço, reduzindo risco de N+1.
- Listas relevantes usam paginação no backend.
- O health check respondeu 200 com banco e fila em estado `ok`.
- Não houve teste com milhares de tenants, reservas ou conversas, nem medição de p95/p99, CPU, memória ou plano de queries. Performance de escala não está aprovada.

## 8. Testes não executados ou apenas parciais

- Edge, Firefox e Safari: não disponíveis na sessão solicitada; somente Chrome `1134867JP` foi autorizado/usado.
- Evolution API real: não havia endpoint/credencial segura; foram testadas configuração ausente, HTTP 500 e UI de falha.
- Asaas/PIX/cartão reais: sem credenciais e sem autorização para transações financeiras; somente mocks e regras automatizadas.
- Provedor de IA real e prompt injection contra modelo externo: sem chave operacional; somente fluxo/simulador e testes isolados.
- Google Calendar e envio real de e-mail: sem contas/credenciais conectadas.
- Upload real de imagem, áudio e documento pelo WhatsApp: sem instância externa conectada.
- Worker reiniciado, scheduler de longo prazo, jobs presos, timeout real e falha de rede/banco durante commit: exigem infraestrutura controlada.
- Sessão por expiração temporal real: cobertura automatizada e invalidação foram usadas; não houve espera de todo o TTL.
- Responsável, profissional e atendente como papéis de acesso: esses papéis não existem no modelo atual; apenas `admin` e `operador`. Profissional é entidade de agenda, não usuário/perfil.
- Usuário desativado: não existe estado de ativação no modelo de usuário; tenant inativo é coberto.
- Fuso horário múltiplo, horário de verão, virada anual e ano bissexto completos: regras de Carbon foram cobertas parcialmente, sem matriz temporal externa.
- Carga, soak, caos, memória, CPU e banco com grandes volumes: sem dataset/infra de performance.
- Concorrência manual distribuída entre processos: substituída por testes transacionais/constraint em PostgreSQL.
- Auditoria online de dependências: não executada para não transmitir metadados do projeto a serviços externos sem autorização específica.

## 9. Correções realizadas

- Implementação completa de Opções extras.
- Implementação da agenda global do superadmin.
- Reserva manual alinhada a profissionais, recursos e serviços.
- Preenchimento do cliente de origem com isolamento por tenant.
- Validação visível e acessível no modal de reserva.
- Limpeza/revalidação do histórico protegido após logout.
- Confirmação de senha antecipada para criação/edição de tenant.
- Criação local de tenant desacoplada da Evolution via job.
- Erro de domínio para Evolution e retorno seguro 503 no QR Code.
- Mascaramento recursivo de tokens, senhas, cookies, autorizações e chaves.
- Redução de PII em log de trial.
- Associações acessíveis em formulários.
- Eliminação de polling duplicado.
- `.dockerignore` para build reproduzível.

## 10. Arquivos alterados

### Backend

- `app/Exceptions/EvolutionApiException.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/SuperAdmin/AgendamentoController.php`
- `app/Http/Controllers/SuperAdmin/LogController.php`
- `app/Http/Controllers/SuperAdmin/TenantController.php`
- `app/Http/Controllers/Tenant/AgendamentoController.php`
- `app/Http/Controllers/Tenant/WhatsAppController.php`
- `app/Jobs/CreateEvolutionInstanceJob.php`
- `app/Jobs/VerificarTrialExpiradoJob.php`
- `app/Services/EvolutionApiService.php`
- `app/Support/DataMasker.php`
- `routes/web.php`

### Frontend

- `resources/js/app.tsx`
- `resources/js/Components/Layout/Sidebar.tsx`
- `resources/js/Layouts/AppLayout.tsx`
- `resources/js/lib/configTabs.ts`
- `resources/js/Pages/SuperAdmin/Agendamentos.tsx`
- `resources/js/Pages/SuperAdmin/Tenants/Create.tsx`
- `resources/js/Pages/Tenant/Agendamentos/Index.tsx`
- `resources/js/Pages/Tenant/Clientes/Index.tsx`
- `resources/js/Pages/Tenant/Conversas/Index.tsx`
- `resources/js/Pages/Tenant/Equipe.tsx`
- `resources/js/Pages/Tenant/OpcaoExtra/Index.tsx`
- `resources/js/Pages/Tenant/Servicos/Index.tsx`

### Qualidade e build

- `.dockerignore`
- `tests/Feature/QaExploratoryRegressionTest.php`
- `tests/Unit/DataMaskerTest.php`
- `docs/qa/AUDITORIA_QA_2026-07-30.md`

## 11. Testes automatizados criados

`QaExploratoryRegressionTest` adiciona nove casos:

1. Opções extras renderizam e permanecem isoladas por tenant.
2. Agenda global do superadmin renderiza.
3. Reserva manual recebe profissionais, serviços e cliente inicial.
4. Cliente de outro tenant não pode ser usado no preenchimento.
5. Confirmação de senha ocorre antes do formulário sensível.
6. Criação de tenant enfileira preparação do WhatsApp.
7. QR Code retorna 503 quando Evolution não está configurada.
8. Falha HTTP da Evolution vira exceção de domínio.
9. Logout solicita limpeza do histórico Inertia.

`DataMaskerTest` adiciona cobertura de redação completa para credenciais aninhadas.

Além dos novos testes, a suíte existente cobriu reservas válidas, duração do serviço, conflitos, regras de funcionamento, bloqueios, concorrência, isolamento, webhooks, cobrança, bot, clientes, profissionais, serviços e jobs.

## 12. Resultado final de testes, lint, tipos e build

| Verificação | Resultado |
|---|---|
| Suíte PHP completa | **PASS — 176 testes, 704 asserções** |
| Regressões exploratórias novas | **PASS — 9 testes, 75 asserções** |
| Pint nos 14 PHP alterados | **PASS** |
| Pint global | **FAIL — 84 arquivos preexistentes com estilo divergente** |
| TypeScript | **PASS** |
| Vite produção | **PASS — 1.049 módulos** |
| Docker produção | **PASS** |
| Health check | **PASS — banco/fila ok, 0 jobs falhos** |
| `git diff --check` | **PASS** |
| Chrome funcional | **PASS nos cenários retestados** |

O lint global não foi corrigido em massa para evitar um diff mecânico amplo, risco de conflitos e mistura de dívida histórica com as correções funcionais.

## 13. Riscos remanescentes

- Integrações externas reais continuam sem homologação.
- Não há certificação cross-browser.
- Escala e consumo de infraestrutura não foram medidos.
- A dívida de estilo em 84 arquivos dificulta gates futuros.
- O modelo de permissões possui apenas admin/operador e não atende à matriz de papéis solicitada sem desenvolvimento adicional.
- Fila e scheduler precisam de monitoramento/heartbeat em ambiente real; no QA, o painel mostrou worker sem heartbeat porque nenhum worker foi iniciado.
- A fila de criação da Evolution está nomeada `sync`, embora use conexão de banco no ambiente validado; o nome pode confundir operação e deve ser padronizado.
- `APP_DEBUG` deve permanecer falso em qualquer ambiente acessível externamente.
- A ausência de auditoria online de dependências mantém risco residual de CVEs.

## 14. Recomendação de produção

**Não liberar o commit original `fa1e9ed`.**

**Liberar o checkout corrigido apenas para staging/homologação.** A promoção para produção deve exigir:

1. revisão de código e commit/PR das alterações;
2. pipeline repetindo testes, Pint dos arquivos alterados, TypeScript, Vite e Docker;
3. `APP_DEBUG=false` e segredos gerenciados fora do repositório;
4. homologação real de Evolution, Asaas, IA e e-mail;
5. worker/scheduler ativos com heartbeat, backoff e alertas;
6. smoke em Edge/Firefox e Safari;
7. teste de carga mínimo para agenda, conversas e polling;
8. plano separado para quitar o lint global e padronizar a fila `sync`.

Cumpridos esses gates, não há bloqueador funcional conhecido nas áreas efetivamente testadas.
