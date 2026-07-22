# Migrações do banco de dados

As alterações de estrutura devem ser adicionadas somente nestas pastas:

- `master/`: banco principal, que contém tenants, assinaturas e administração.
- `tenant/`: banco individual de cada empresa assinante.

Cada arquivo retorna `id`, `description` e uma função `up`. O identificador e o arquivo aplicado nunca devem ser alterados depois da execução; crie uma nova migração para qualquer ajuste posterior.

Comandos:

```powershell
C:\xampp\php\php.exe scripts\migrate.php --status
C:\xampp\php\php.exe scripts\migrate.php --dry-run
C:\xampp\php\php.exe scripts\migrate.php
C:\xampp\php\php.exe scripts\migrate.php --scope=master
C:\xampp\php\php.exe scripts\migrate.php --scope=tenants --tenant=tenant_db_5
```

O executor registra cada aplicação em `schema_migrations`, confere o checksum e usa um bloqueio do MySQL para impedir duas execuções simultâneas no mesmo banco.

Antes de uma migração destrutiva ou que transforme dados, faça backup e teste em uma cópia. Os arquivos SQL antigos são estruturas ou reparos legados; não substituem o executor de migrações.
