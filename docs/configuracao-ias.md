# Configuração das IAs do Agendou

O Laravel é o único orquestrador. Os providers apenas recebem o prompt, devolvem
texto ou solicitações de ferramentas e nunca acessam diretamente banco, agenda ou
Evolution API.

## Ordem padrão de produção

O fluxo padrão foi separado em rotas distintas para permitir dois modelos Groq
usando a mesma API key:

1. `groq_qwen` — Groq / `qwen/qwen3.8-27b`
2. `groq_gpt_oss` — Groq / `openai/gpt-oss-20b`
3. `cloudflare` — Cloudflare Workers AI
4. `gemini` — Gemini
5. `openrouter` — OpenRouter Free

```env
AI_PROVIDER=groq_qwen
AI_FALLBACK_PROVIDERS=groq_gpt_oss,cloudflare,gemini,openrouter
AI_TIMEOUT_SECONDS=30
```

Configure as chaves dos providers que poderão ser usados:

```env
GROQ_API_KEY=gsk_...
GROQ_QWEN_MODEL=qwen/qwen3.8-27b
GROQ_GPT_OSS_MODEL=openai/gpt-oss-20b

CLOUDFLARE_ACCOUNT_ID=...
CLOUDFLARE_API_TOKEN=...
CLOUDFLARE_MODEL=@cf/openai/gpt-oss-20b

GEMINI_API_KEY=...
GEMINI_MODEL=gemini-3.6-flash

OPENROUTER_API_KEY=sk-or-v1-...
OPENROUTER_MODEL=openrouter/free
```

O Cloudflare usa o endpoint OpenAI-compatible de Workers AI. Por padrão, o sistema
monta a base URL com `CLOUDFLARE_ACCOUNT_ID`; `CLOUDFLARE_BASE_URL` pode sobrescrever
a URL quando necessário.

`groq` e `GROQ_MODEL` continuam disponíveis apenas para compatibilidade com tenants
antigos que tenham essa configuração persistida.

Providers sem chave são ignorados. Para Cloudflare, tanto o token quanto a base URL
resolvida precisam estar configurados. Se o principal não estiver disponível, o
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
    "provider": "groq_qwen",
    "fallback_providers": ["groq_gpt_oss", "cloudflare", "gemini", "openrouter"],
    "models": {
      "groq_qwen": "qwen/qwen3.8-27b",
      "groq_gpt_oss": "openai/gpt-oss-20b",
      "cloudflare": "@cf/openai/gpt-oss-20b"
    },
    "monthly_token_limit": 500000,
    "monthly_cost_limit_usd": 5
  }
}
```

Configurações do tenant têm precedência sobre os defaults do `.env`.

## Quando ocorre fallback

O fallback é permitido para falhas de conexão e HTTP `400`, `404`, `408`, `409`,
`425`, `429`, `500`, `502`, `503`, `504` e `529`. O `400` é aceito porque providers
diferentes podem ter diferenças de schema/serialização de function calling; uma
rejeição em um provider não significa que o payload seja inválido para os demais.
Erros de autenticação e permissão continuam sem fallback quando o provider os marca
como não recuperáveis.

## Custos

Cada chamada registra provider, modelo, tokens, custo estimado, latência e request
ID em `token_usages`. Os preços dos modelos padrão ficam em `config/ai.php`, em
dólares por milhão de tokens. Antes de produção e sempre que trocar de modelo,
confira os preços vigentes; modelos desconhecidos ficam com custo zero para evitar
estimativas incorretas.

Depois de alterar o `.env`:

```bash
php artisan config:clear
php artisan queue:restart
```
