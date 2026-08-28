# Checklist de liberação — 28/08/2026

Este checklist reúne as validações que dependem do ambiente real e, portanto, não
podem ser simuladas apenas pelos testes automatizados do repositório.

## Bloqueadores antes de abrir o cadastro

- [ ] Configurar SMTP real e confirmar cadastro, reenvio e expiração do link de verificação.
- [ ] Preencher `LEGAL_ENTITY_NAME`, `LEGAL_ENTITY_DOCUMENT` e `LEGAL_CONTACT_EMAIL` com os dados do controlador.
- [ ] Revisar os Termos de Uso e a Política de Privacidade com assessoria jurídica brasileira.
- [ ] Confirmar `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` HTTPS e cookies seguros.
- [ ] Rodar `php artisan migrate --force` e verificar `/health` e `/health/ready`.

## Integrações em homologação

- [ ] Evolution: criar instância após verificar e-mail, ler QR Code, receber mensagem e responder.
- [ ] Asaas sandbox: assinar, receber webhook, ficar inadimplente e cancelar; conferir estado local e remoto.
- [ ] Google Calendar: autorizar, criar, reagendar e cancelar evento; conferir o timezone do estabelecimento.
- [ ] IA: validar cada provedor configurado, fallback, limites de custo e ausência de segredos nos logs.

## Operação e recuperação

- [ ] Confirmar heartbeat dos workers e do scheduler em `/health/ready`.
- [ ] Executar backup e uma restauração completa do PostgreSQL em ambiente isolado.
- [ ] Simular rollback do deploy e confirmar que a versão anterior volta saudável.
- [ ] Verificar alertas de exceção, falha de fila, expiração de trial e cobrança.
- [ ] Confirmar retenção: 30 dias no Starter, 90 no Pro e sem remoção automática no Business.

## Aceitação final

- [ ] Percorrer cadastro e painel em Chrome/Firefox desktop e Chrome/Safari mobile.
- [ ] Validar isolamento entre dois estabelecimentos e permissões de proprietário, gerente e atendente.
- [ ] Confirmar limites: profissionais (3/10/ilimitado) e relatórios avançados (Pro/Business).
- [ ] Registrar responsável, data, ambiente, evidências e decisão de liberação.

O CI cobre regressões de backend, frontend, segurança de dependências e build. Os itens
acima continuam obrigatórios porque usam credenciais, provedores e infraestrutura reais.
