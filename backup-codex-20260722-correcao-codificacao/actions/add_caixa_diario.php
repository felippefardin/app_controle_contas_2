<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils para Flash Messages

// 1. VERIFICA SE O USUÃRIO ESTÃ LOGADO
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. VERIFICA SE O MÃ‰TODO Ã‰ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de seguranÃ§a invÃ¡lido.');
        header("Location: ../pages/lancamento_caixa.php");
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', 'Erro de conexÃ£o com o banco de dados.');
        header("Location: ../pages/lancamento_caixa.php");
        exit;
    }

    $data = $_POST['data'];
    $valor = $_POST['valor'];
    
    // âœ… CORREÃ‡ÃƒO DO ERRO: Usar get_data_owner_id() em vez de ['usuario_logado']['id']
    $usuarioId = get_data_owner_id(); 

    // 3. LÃ“GICA PARA INSERIR OU ATUALIZAR O CAIXA
    $sql = "INSERT INTO caixa_diario (data, valor, usuario_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = valor + VALUES(valor)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        set_flash_message('danger', 'Erro ao preparar consulta.');
        header("Location: ../pages/lancamento_caixa.php");
        exit;
    }

    $stmt->bind_param("sdi", $data, $valor, $usuarioId);

    if ($stmt->execute()) {
        set_flash_message('success', 'LanÃ§amento salvo com sucesso!');
    } else {
        set_flash_message('danger', 'Erro ao salvar lanÃ§amento: ' . $stmt->error);
    }

    $stmt->close();
    header("Location: ../pages/lancamento_caixa.php");
    exit;
} else {
    header('Location: ../pages/lancamento_caixa.php');
    exit;
}
?>
