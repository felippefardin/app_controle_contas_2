<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// 1. VERIFICA LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

$conn = getTenantConnection();
if (!$conn) {
    $_SESSION['error_message'] = "Erro de conexÃ£o com o banco.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

// 2. CAPTURA DADOS
$usuario_id = get_data_owner_id();
$id_conta = isset($_POST['id_conta']) ? (int)$_POST['id_conta'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$data_baixa = isset($_POST['data_baixa']) ? $_POST['data_baixa'] : date('Y-m-d');
$forma_pagamento = isset($_POST['forma_pagamento']) ? $_POST['forma_pagamento'] : 'outros';

if ($id_conta <= 0) {
    $_SESSION['error_message'] = "Conta invÃ¡lida.";
    header('Location: ../pages/contas_pagar.php');
    exit;
}

// 3. PROCESSAMENTO DO ARQUIVO COM SEGURANÃ‡A REFORÃ‡ADA
$caminho_comprovante = null;

if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../comprovantes/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // A) ValidaÃ§Ã£o por ExtensÃ£o
    $extensao = strtolower(pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION));
    
    // Lista de tipos permitidos (ExtensÃ£o => MIME Type)
    $tiposPermitidos = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf'
    ];

    if (!array_key_exists($extensao, $tiposPermitidos)) {
        $_SESSION['error_message'] = "ExtensÃ£o de arquivo invÃ¡lida.";
        header('Location: ../pages/contas_pagar_baixadas.php'); 
        exit;
    }

    // B) ValidaÃ§Ã£o por ConteÃºdo Real (MIME Type) - SEGURANÃ‡A CRÃTICA
    // Isso impede que alguÃ©m renomeie 'virus.php' para 'foto.jpg'
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($_FILES['comprovante']['tmp_name']);

    // Verifica se o MIME real bate com o esperado para aquela extensÃ£o
    // Nota: jpg e jpeg compartilham o mesmo mime, entÃ£o verificamos se estÃ¡ na lista de valores vÃ¡lidos
    if (!in_array($mimeReal, $tiposPermitidos)) {
        $_SESSION['error_message'] = "O arquivo Ã© invÃ¡lido ou corrompido (MIME Type incorreto).";
        header('Location: ../pages/contas_pagar_baixadas.php');
        exit;
    }

    // C) Renomear o arquivo (SanitizaÃ§Ã£o)
    // O uso de uniqid que vocÃª jÃ¡ fazia Ã© Ã³timo. Mantivemos.
    $novoNome = uniqid('comp_') . '_' . $id_conta . '.' . $extensao;
    $destino = $uploadDir . $novoNome;

    if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $destino)) {
        $caminho_comprovante = 'comprovantes/' . $novoNome;
    } else {
        $_SESSION['error_message'] = "Erro ao salvar o arquivo no servidor.";
    }
}

// 4. ATUALIZA A TABELA
if ($caminho_comprovante) {
    $sql = "UPDATE contas_pagar SET status='baixada', data_baixa=?, baixado_por=?, forma_pagamento=?, comprovante=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissi", $data_baixa, $usuario_id, $forma_pagamento, $caminho_comprovante, $id_conta);
} else {
    $sql = "UPDATE contas_pagar SET status='baixada', data_baixa=?, baixado_por=?, forma_pagamento=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisi", $data_baixa, $usuario_id, $forma_pagamento, $id_conta);
}

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Conta baixada com sucesso!";
} else {
    $_SESSION['error_message'] = "Erro no banco: " . $stmt->error;
}

$stmt->close();
header('Location: ../pages/contas_pagar_baixadas.php');
exit;
?>
