# Design: Reagendamento pelo Bot

**Data:** 2026-06-29
**Status:** Aprovado

## Contexto

O bot atual consegue criar e cancelar agendamentos, mas não reagendar. Quando um cliente pede para mudar o horário, o bot não sabe executar a ação. Este spec descreve a adição dessa capacidade via duas novas tools no `ClaudeAgentService`.

## Decisões de design

| Decisão | Escolha |
|---|---|
| O que o cliente pode mudar | Qualquer coisa: profissional, serviço, data, hora |
| O que acontece com o agendamento original | Atualizado no lugar (mesmo ID, sem cancelar) |
| Como escolher entre múltiplos agendamentos | Bot lista os futuros e cliente escolhe |
| Abordagem de implementação | Duas novas tools no ClaudeAgentService |

## Arquitetura

Nenhuma nova camada de estado ou serviço dedicado. Duas tools são adicionadas ao conjunto existente do `ClaudeAgentService`. O Claude orquestra o fluxo conversacional naturalmente com as tools já disponíveis (`buscar_slots`) e as novas.

### Fluxo da conversa

```
Cliente: "quero reagendar"
Bot: chama listar_agendamentos_cliente → lista agendamentos futuros
Cliente: escolhe qual (ex: "o de sexta")
Bot: chama buscar_slots → apresenta horários disponíveis
Cliente: escolhe novo horário
Bot: chama reagendar_agendamento → atualiza no banco
Bot: confirma o novo horário ao cliente
```

## Novas tools

### `listar_agendamentos_cliente`

- **Sem parâmetros** (usa `currentCliente` já disponível no serviço)
- Busca agendamentos do cliente onde `status IN ('confirmado', 'pendente')` e `data_hora > now()`, ordenados por data, limite 5
- Retorna lista formatada: `"ID #42 — Sexta 04/07 às 14:00 — João (Corte + Barba)"`
- Se vazio: `"Nenhum agendamento futuro encontrado."`
- **Instrução ao Claude:** usar esta tool antes de qualquer reagendamento

### `reagendar_agendamento`

**Parâmetros:**
| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `agendamento_id` | integer | sim | ID do agendamento a atualizar |
| `data` | string | sim | Nova data em YYYY-MM-DD |
| `hora` | string | sim | Novo horário em HH:MM |
| `profissional_id` | integer | não | Novo profissional (mantém o atual se omitido) |
| `servico_id` | integer | não | Novo serviço (mantém o atual se omitido) |

**Validações (antes de atualizar):**
1. Agendamento pertence ao `currentCliente` — impede cliente de alterar agendamento de outro
2. Status não é `cancelado` nem `concluido`
3. Novo slot disponível — verificado via `AgendamentoService::reagendar()` com exclusão do próprio ID

**Retorno:**
- Sucesso: `{ "sucesso": true }`
- Conflito: `{ "sucesso": false, "erro": "horario_indisponivel" }`
- Inválido: `{ "sucesso": false, "erro": "agendamento_invalido" }`

## `AgendamentoService::reagendar()`

Novo método público. Executa dentro de `DB::transaction` com `pg_advisory_xact_lock` no `profissional_id` (mesmo padrão do `criar`).

```
reagendar(Agendamento $agendamento, array $dados): Agendamento

1. Calcular $inicio = Carbon::parse("{$dados['data']} {$dados['hora']}")
2. $duracao = $agendamento->duracao_minutos ?? 60
3. $fim = $inicio->addMinutes($duracao)
4. Verificar conflito: WHERE profissional_id = X AND id != $agendamento->id AND status != 'cancelado' AND inicio < $fim AND fim > $inicio
5. Se conflito → throw HorarioIndisponivelException
6. $agendamento->update([profissional_id, servico_id, data_hora, inicio, fim])
7. return $agendamento->fresh()
```

## Mudança no system prompt

Adicionar parágrafo ao `buildStaticPrompt()` na seção de capacidades do bot:

> "Você pode reagendar agendamentos existentes. Quando o cliente pedir para mudar um horário, SEMPRE chame `listar_agendamentos_cliente` primeiro para mostrar as opções. Depois use `buscar_slots` para mostrar os novos horários disponíveis. Só chame `reagendar_agendamento` após o cliente confirmar explicitamente o novo horário."

## Arquivos afetados

| Arquivo | Mudança |
|---|---|
| `app/Services/AgendamentoService.php` | Novo método `reagendar()` |
| `app/Services/ClaudeAgentService.php` | 2 novas tools em `buildTools()`, 2 novos métodos `toolListarAgendamentosCliente()` e `toolReagendarAgendamento()`, despacho no `executeTool()`, instrução no `buildStaticPrompt()` |

## Fora do escopo

- Notificação de confirmação de reagendamento por WhatsApp (o bot já responde na conversa)
- Interface no painel web para ver histórico de reagendamentos
- Limite de quantas vezes um cliente pode reagendar
