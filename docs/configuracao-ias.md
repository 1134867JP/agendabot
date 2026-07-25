# Configuração das IAs do Agendou

O Laravel é o único orquestrador. Os providers apenas recebem o prompt, devolvem
texto ou solicitações de ferramentas e nunca acessam diretamente banco, agenda ou
Evolution API.

## Variáveis obrigatórias

Escolha o provider principal e a ordem de fallback:

```env
AI_PROVIDER=claude
AI_FALLBACK_PROVIDERS=gemini,groq,openrouter
AI_TIMEOUT_SECONDS=30
```

Configure somente as chaves dos providers que poderão ser usados:

```env
CLAUDE_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-haiku-4-5-20251001

GEMINI_API_KEY=...
GEMINI_MODEL=gemini-2.5-flash

GROQ_API_KEY=gsk_...
GROQ_MODEL=openai/gpt-oss-120b

OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=anthropic/claude-haiku-4.5
```

Providers sem chave são ignorados. Se o principal não estiver configurado, o
orquestrador tenta o próximo fallback.

## Limites globais

Os limites são mensais e opcionais:

```env
AI_MONTHLY_TOKEN_LIMIT=2000000
AI_MONTHLY_COST_LIMIT_USD=20
```

Quando qualquer limite é atingido, o bot não chama outra IA e transfere a conversa
para atendimento humano.

## Configuração por tenant

As chaves nunca são gravadas por tenant. Apenas preferências e limites podem ser
salvos em `tenants.configuracoes.ai`:

```json
{
  "ai": {
    "provider": "gemini",
    "fallback_providers": ["groq", "claude"],
    "models": {
      "gemini": "gemini-2.5-flash",
      "groq": "openai/gpt-oss-120b"
    },
    "monthly_token_limit": 500000,
    "monthly_cost_limit_usd": 5
  }
}
```

Configurações do tenant têm precedência sobre os defaults do `.env`.

## Quando ocorre fallback

O fallback é permitido para falhas de conexão e HTTP `408`, `409`, `425`, `429`,
`500`, `502`, `503`, `504` e `529`. Erros de autenticação, permissão ou payload
inválido não fazem fallback, pois normalmente exigem correção de configuração ou
código.

## Custos

Cada chamada registra provider, modelo, tokens, custo estimado, latência e request
ID em `token_usages`. Os preços dos modelos padrão ficam em `config/ai.php`, em
dólares por milhão de tokens. Antes de produção e sempre que trocar de modelo,
confira os preços vigentes; modelos desconhecidos ficam com custo zero para evitar
estimativas incorretas.

Depois de alterar o `.env`:

```bash
php artisan migrate
php artisan config:clear
php artisan queue:restart
```
