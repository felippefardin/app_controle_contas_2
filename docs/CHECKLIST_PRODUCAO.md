# Checklist de homologação e produção

O projeto deve permanecer em `APP_ENV=local` ou `APP_ENV=testing` enquanto estiver em testes. Este documento não publica o sistema e não ativa cobranças ou emissão fiscal real.

## Durante a fase de testes

1. Mantenha `MERCADOPAGO_MODE=sandbox`.
2. Mantenha `MP_ALLOW_UNSIGNED_SANDBOX=false`.
3. Mantenha `FISCAL_ALLOW_PRODUCTION=false`.
4. Use apenas usuários e cartões de teste do Mercado Pago.
5. Execute antes de cada rodada:

```powershell
C:\xampp\php\php.exe scripts\migrate.php --status
C:\xampp\php\php.exe tests\run.php
C:\xampp\php\php.exe scripts\preflight.php --target=test
```

6. Teste cadastro, login, usuários extras, vendas, estoque, contas, suporte, cupons, exclusão/restauração e renovação de assinatura.
7. Faça backup do banco antes de testar migrações que alterem dados existentes.

## Quando decidir publicar

1. Contratar servidor com PHP 8.2+, MySQL/MariaDB, HTTPS e tarefas agendadas.
2. Criar bancos e usuários MySQL exclusivos, sem utilizar `root`.
3. Definir `APP_ENV=production` e `APP_DEBUG=false`.
4. Atualizar `APP_URL` e URLs de retorno para HTTPS.
5. Gerar credenciais novas e exclusivas para produção.
6. Configurar SMTP real com SPF, DKIM e DMARC.
7. Configurar credenciais de produção do Mercado Pago e assinatura secreta do webhook.
8. Cadastrar no Mercado Pago a URL pública de `pages/webhook_mercadopago.php`.
9. Manter `MP_ALLOW_UNSIGNED_SANDBOX=false`.
10. Validar política de privacidade, termos, LGPD e prazos de retenção.
11. Configurar backups automáticos fora do servidor e testar uma restauração.
12. Configurar monitoramento de erros, disponibilidade, filas e webhooks.
13. Validar certificado digital e credenciamento fiscal antes de habilitar `FISCAL_ALLOW_PRODUCTION`.
14. Executar:

```powershell
C:\xampp\php\php.exe scripts\preflight.php --target=production
```

Não publique enquanto essa verificação apresentar qualquer item como `ERRO`.

## Plano de retorno

- Preserve backup do banco e a versão anterior do código.
- Migrações destrutivas exigem procedimento de retorno específico e teste em cópia.
- Se houver falha em pagamentos, mantenha o webhook disponível e suspenda apenas novos checkouts.
- Se houver falha fiscal, desative a emissão real sem interromper vendas e romaneios de teste.

