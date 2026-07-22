<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // IMPORTANTE: Importar utils para usar set_flash_message

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}

$conn = getTenantConnection();
$usuario_id = $_SESSION['usuario_id'];
$id_conta = isset($_POST['id_conta']) ? (int)$_POST['id_conta'] : 0;
$data_baixa = $_POST['data_baixa'] ?? date('Y-m-d');
$forma_pagamento = $_POST['forma_pagamento'] ?? 'outros';

if ($id_conta <= 0) {
    set_flash_message('error', "Conta inválida."); // Alterado
    header('Location: ../pages/contas_receber.php');
    exit;
}

// Upload do Comprovante
$caminho_comprovante = null;
if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../comprovantes/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = strtolower(pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        $novoNome = uniqid('rec_') . '_' . $id_conta . '.' . $ext;
        if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $uploadDir . $novoNome)) {
            $caminho_comprovante = 'comprovantes/' . $novoNome;
        }
    }
}

// Query dinâmica
$sql = "UPDATE contas_receber SET status='baixada', data_baixa=?, baixado_por=?, forma_pagamento=?";
$params = [$data_baixa, $usuario_id, $forma_pagamento];
$types = "sis";

if ($caminho_comprovante) {
    $sql .= ", comprovante=?";
    $params[] = $caminho_comprovante;
    $types .= "s";
}

$sql .= " WHERE id=?";
$params[] = $id_conta;
$types .= "i";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // SUCESSO: Usa a função para exibir o card fluente com ícone
    set_flash_message('success', "Recebimento registrado com sucesso!");
} else {
    // ERRO
    set_flash_message('error', "Erro ao registrar: " . $stmt->error);
}

$stmt->close();
header('Location: ../pages/contas_receber_baixadas.php');
exit;
?>