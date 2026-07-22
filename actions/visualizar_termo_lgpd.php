<?php
require_once '../includes/session_init.php';
require_once '../database.php';

$isSuperAdmin = !empty($_SESSION['super_admin']);
$isAdmin = ($_SESSION['perfil'] ?? '') === 'admin'
    || in_array($_SESSION['nivel_acesso'] ?? '', ['admin', 'master'], true);
if (!$isSuperAdmin && !$isAdmin) {
    http_response_code(403);
    exit('Acesso negado.');
}

$id = (int)($_GET['id'] ?? 0);
$conn = getMasterConnection();
$stmt = $conn->prepare('SELECT caminho_arquivo FROM termos_consentimento WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$registro = $stmt->get_result()->fetch_assoc();
if (!$registro) {
    http_response_code(404);
    exit('Documento não encontrado no cadastro.');
}

$raizProjeto = realpath(__DIR__ . '/..');
$pastaContratos = realpath($raizProjeto . '/assets/uploads/contratos_lgpd');
$caminhoSalvo = str_replace('\\', '/', trim((string)$registro['caminho_arquivo']));
$relativo = ltrim($caminhoSalvo, '/');
$candidatos = [
    $raizProjeto . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo),
    $pastaContratos . DIRECTORY_SEPARATOR . basename($caminhoSalvo),
];

$arquivo = false;
foreach ($candidatos as $candidato) {
    $real = realpath($candidato);
    if ($real && is_file($real) && str_starts_with($real, $raizProjeto . DIRECTORY_SEPARATOR) && strtolower(pathinfo($real, PATHINFO_EXTENSION)) === 'pdf') {
        $arquivo = $real;
        break;
    }
}
if (!$arquivo) {
    http_response_code(404);
    exit('O arquivo PDF não foi localizado no servidor.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($arquivo));
header('Content-Disposition: inline; filename="' . basename($arquivo) . '"');
header('X-Content-Type-Options: nosniff');
readfile($arquivo);
