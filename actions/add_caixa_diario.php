<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/caixa_service.php';

if (empty($_SESSION['usuario_logado']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    set_flash_message('danger', 'Sessão expirada. Atualize a página e tente novamente.');
    header('Location: ../pages/lancamento_caixa.php');
    exit;
}

try {
    $conn = getTenantConnection();
    $data = date('Y-m-d');
    $valorTexto = str_replace(['R$', ' ', '.'], '', (string)($_POST['valor'] ?? ''));
    $valor = (float)str_replace(',', '.', $valorTexto);
    $tipo = ($_POST['tipo_movimento'] ?? '') === 'saida' ? 'manual_saida' : 'manual_entrada';
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $ownerId = get_data_owner_id();
    $operatorId = (int)($_SESSION['usuario_id'] ?? 0);

    if ($valor <= 0) throw new InvalidArgumentException('Informe um valor maior que zero.');
    if ($descricao === '') throw new InvalidArgumentException('Informe a descrição do lançamento.');
    $sessao = caixa_buscar_sessao($conn, $ownerId, $data);
    if (!$sessao || $sessao['status'] !== 'aberto') {
        throw new RuntimeException('Abra o caixa de hoje antes de adicionar um lançamento.');
    }

    $forma = 'dinheiro';
    $aberturaId = (int)$sessao['id'];
    $stmt = $conn->prepare('INSERT INTO caixa_diario (data,valor,tipo,descricao,usuario_id,forma_pagamento,abertura_id,criado_por) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->bind_param('sdssisii', $data, $valor, $tipo, $descricao, $ownerId, $forma, $aberturaId, $operatorId);
    $stmt->execute();
    $stmt->close();
    set_flash_message('success', 'Lançamento incluído no caixa de hoje.');
} catch (Throwable $e) {
    set_flash_message('danger', $e->getMessage());
}

header('Location: ../pages/lancamento_caixa.php');
exit;
