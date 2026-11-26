<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/caixa_service.php';

if (empty($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('Acesso negado.');
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    set_flash_message('danger', 'Sessão expirada. Atualize a página e tente novamente.');
    header('Location: ../pages/vendas.php');
    exit;
}

$valorTexto = str_replace(['R$', ' ', '.'], '', trim((string)($_POST['valor_abertura'] ?? '0')));
$valorTexto = str_replace(',', '.', $valorTexto);
$valor = is_numeric($valorTexto) ? (float)$valorTexto : -1;
$observacao = trim((string)($_POST['observacao'] ?? ''));

try {
    caixa_abrir(
        getTenantConnection(),
        get_data_owner_id(),
        (int)($_SESSION['usuario_id'] ?? 0),
        $valor,
        $observacao !== '' ? mb_substr($observacao, 0, 255) : null
    );
    set_flash_message('success', 'Caixa aberto com sucesso. Saldo inicial: R$ ' . number_format($valor, 2, ',', '.') . '.');
} catch (Throwable $e) {
    set_flash_message('danger', $e->getMessage());
}
header('Location: ../pages/vendas.php');
exit;
