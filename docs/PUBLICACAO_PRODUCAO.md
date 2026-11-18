# Publicacao em producao

## 1. Gerar a entrega limpa

No PowerShell, dentro do projeto:

```powershell
.\scripts\build-production.ps1
```

Envie somente a pasta criada pelo script. A entrega exclui `.env`, Git, testes,
logs, SQL, temporarios e copias antigas.

## 2. Configurar o servidor

- PHP 8.2 ou superior com `mysqli`, `curl`, `mbstring`, `openssl`, `json`,
  `fileinfo` e `soap`.
- Apache com `mod_rewrite` e `mod_headers`, ou regras equivalentes no Nginx.
- MySQL/MariaDB com usuario exclusivo e sem permissao global de administrador.
- HTTPS ativo antes de aceitar cadastros, senhas ou pagamentos.
- `PRIVATE_STORAGE_PATH` fora da pasta publica e gravavel pelo PHP.

Crie o `.env` diretamente no servidor a partir de `.env.production.example`.
Nunca envie o `.env` local.

## 3. Servicos externos

- Configure SMTP de producao e valide SPF, DKIM e DMARC.
- Configure Mercado Pago de producao e o segredo de assinatura do webhook.
- Cadastre a URL HTTPS de `pages/webhook_mercadopago.php`.
- Deixe `FISCAL_ALLOW_PRODUCTION=false` ate concluir certificado e homologacao fiscal.
- Em hospedagem sem Ollama, use `OLLAMA_ENABLED=false`.

## 4. Banco e tarefas agendadas

Importe o banco por um canal administrativo privado e execute as migracoes.
Configure os arquivos necessarios de `cron/` como tarefas CLI. A pasta `cron`
e bloqueada para acesso pelo navegador.

Execute diariamente a tarefa de preservacao e exclusao de contas:

```bash
php cron/processar_cancelamentos.php
```

Ela remove definitivamente somente contas que permaneceram desativadas por um
ano completo. A reativacao feita pela tela de login cancela essa exclusao.

## 5. Validacao final

Antes de gerar o pacote, execute localmente:

```bash
php tests/run.php
composer audit --locked
```

No servidor, execute:

```bash
php scripts/migrate.php --status
php scripts/preflight.php --target=production
composer audit --locked
```

Nao abra o site ao publico enquanto o preflight apresentar `ERRO`.
Depois da publicacao, teste cadastro, login, recuperacao de senha, uploads,
vendas, contas, suporte, LGPD, checkout e webhook.
