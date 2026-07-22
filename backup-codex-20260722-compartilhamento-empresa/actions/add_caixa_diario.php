<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils para Flash Messages

// 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. VERIFICA SE O MÉTODO É POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header("Location: ../pages/lancamento_caixa.php");
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        set_flash_message('danger', 'Erro de conexão com o banco de dados.');
        header("Location: ../pages/lancamento_caixa.php");
        exit;
    }

    $data = $_POST['data'];
    $valor = $_POST['valor'];
    
    // ✅ CORREÇÃO DO ERRO: Usar $_SESSION['usuario_id'] em vez de ['usuario_logado']['id']
    $usuarioId = $_SESSION['usuario_id']; 

    // 3. LÓGICA PARA INSERIR OU ATUALIZAR O CAIXA
    $sql = "INSERT INTO caixa_diario (data, valor, usuario_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = valor + VALUES(valor)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        set_flash_message('danger', 'Erro ao preparar consulta.');
        header("Location: ../pages/lancamento_caixa.php");
        exit;
    }

    $stmt->bind_param("sdi", $data, $valor, $usuarioId);

    if ($stmt->execute()) {
        set_flash_message('success', 'Lançamento salvo com sucesso!');
    } else {
        set_flash_message('danger', 'Erro ao salvar lançamento: ' . $stmt->error);
    }

    $stmt->close();
    header("Location: ../pages/lancamento_caixa.php");
    exit;
} else {
    header('Location: ../pages/lancamento_caixa.php');
    exit;
}
?>