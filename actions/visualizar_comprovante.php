<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/private_storage.php';

if (($_SESSION['usuario_logado'] ?? false) !== true) {
    http_response_code(401);
    exit('Autenticação necessária.');
}

$tipo = (string) ($_GET['tipo'] ?? '');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$tabelas = ['pagar' => 'contas_pagar', 'receber' => 'contas_receber'];

if (!$id || !isset($tabelas[$tipo])) {
    http_response_code(400);
    exit('Solicitação inválida.');
}

$conn = getTenantConnection();
if (!$conn) {
    http_response_code(503);
    exit('Serviço temporariamente indisponível.');
}

$ownerId = get_data_owner_id();
$table = $tabelas[$tipo];
$stmt = $conn->prepare("SELECT comprovante FROM {$table} WHERE id = ? AND usuario_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $ownerId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['comprovante'])) {
    http_response_code(404);
    exit('Comprovante não encontrado.');
}

$savedPath = str_replace('\\', '/', (string) $row['comprovante']);
$fileName = basename($savedPath);
$candidates = [
    private_storage_path('comprovantes/' . $fileName),
    realpath(__DIR__ . '/../comprovantes/' . $fileName) ?: '',
];

$file = null;
foreach ($candidates as $candidate) {
    if ($candidate !== '' && is_file($candidate)) {
        $file = $candidate;
        break;
    }
}

if ($file === null) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
$allowed = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Formato não permitido.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file));
header('Content-Disposition: inline; filename="' . rawurlencode($fileName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($file);
exit;

