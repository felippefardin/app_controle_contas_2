param([string]$Destination = "")

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
if ([string]::IsNullOrWhiteSpace($Destination)) {
    $Destination = Join-Path (Split-Path -Parent $projectRoot) "app_controle_contas_2-production-$stamp"
}

$destinationPath = [System.IO.Path]::GetFullPath($Destination)
$projectPath = [System.IO.Path]::GetFullPath($projectRoot)
if ($destinationPath.StartsWith($projectPath, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'O destino deve ficar fora da pasta do projeto.'
}
if (Test-Path -LiteralPath $destinationPath) { throw "O destino ja existe: $destinationPath" }

$excludedDirectories = @('.git', '.github', '.agents', '.codex', 'tests', 'docs', 'backup-codex-*', '.codex-backup-*', 'logs', 'tmp')
$excludedFiles = @('.env', '.env.testing', '*.log', '*.sql', '*.sql.err', '*.bak', '*.bak-*', '*.backup', '*.backup-*', 'phpunit.xml', 'webhook_log.txt', 'banco-dados', 'how *')

New-Item -ItemType Directory -Path $destinationPath | Out-Null
$arguments = @($projectPath, $destinationPath, '/E', '/COPY:DAT', '/DCOPY:DAT', '/R:1', '/W:1', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/XD')
$arguments += $excludedDirectories
$arguments += '/XF'
$arguments += $excludedFiles
& robocopy @arguments | Out-Null
if ($LASTEXITCODE -ge 8) { throw "Falha ao montar o pacote (robocopy: $LASTEXITCODE)." }

$required = @('index.php', '.htaccess', 'composer.json', 'composer.lock', 'vendor/autoload.php')
foreach ($item in $required) {
    if (-not (Test-Path -LiteralPath (Join-Path $destinationPath $item))) { throw "Pacote incompleto: $item nao foi encontrado." }
}
$forbidden = Get-ChildItem -LiteralPath $destinationPath -Recurse -Force -File | Where-Object {
    $_.Name -eq '.env' -or $_.Name -match '\.(?:sql|log)(?:\.|$)' -or $_.Name -match '\.(?:bak|backup)(?:[-.]|$)'
}
if ($forbidden) { throw "O pacote contem arquivos proibidos: $($forbidden.FullName -join ', ')" }

Write-Output "Pacote de producao criado em: $destinationPath"
Write-Output 'Crie o .env diretamente no servidor usando .env.production.example como referencia.'
