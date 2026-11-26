# Testes automatizados

Execute a suíte completa:

```powershell
C:\xampp\php\php.exe tests\run.php
```

Ou execute um grupo:

```powershell
C:\xampp\php\php.exe tests\run.php unit
C:\xampp\php\php.exe tests\run.php integration
```

Também estão disponíveis os comandos do Composer:

```powershell
composer test
composer test:unit
composer test:integration
```

Os testes de integração usam transações e executam `ROLLBACK`, portanto os produtos, auditorias e faturas fictícias não permanecem no banco. A execução é bloqueada quando `APP_ENV` estiver definido como `production` ou `producao`.

Cobertura atual:

- CSRF, perfis administrativos e permissões de páginas.
- Compartilhamento de dados do proprietário com usuários extras.
- Assinatura criptográfica dos webhooks do Mercado Pago.
- Remoção de credenciais dos registros de auditoria.
- Situação das migrações em todos os bancos.
- Exclusão lógica, isolamento por proprietário, auditoria e restauração.
- Restrição de transações duplicadas do Mercado Pago.
