<?php
require_once '../includes/config/config.php';
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa Flash Messages

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}

// SEGURANÇA: CSRF CHECK
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        set_flash_message('danger', 'Token de segurança inválido.');
        header('Location: ../pages/lembrete.php');
        exit;
    }

    $conn = getTenantConnection();
    $usuario_id = $_SESSION['usuario_id'];

    $id        = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $titulo    = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $data      = $_POST['data'];
    $hora      = $_POST['hora'];
    $cor       = $_POST['cor'];
    $visibilidade = $_POST['tipo_visibilidade'];
    $email_notif  = trim($_POST['email_notificacao']); 
    if(empty($email_notif)) $email_notif = null;

    try {
        if ($id) {
            // Atualizar (Garante que o ID pertence ao usuário OU é visível, mas a edição geralmente só autor faz)
            // Ajuste: Permitir editar apenas se for o autor
            $sql = "UPDATE lembretes 
                    SET titulo=?, descricao=?, data_lembrete=?, hora_lembrete=?, cor=?, tipo_visibilidade=?, email_notificacao=?
                    WHERE id=? AND usuario_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssii", $titulo, $descricao, $data, $hora, $cor, $visibilidade, $email_notif, $id, $usuario_id);
        } else {
            // Inserir
            $sql = "INSERT INTO lembretes (usuario_id, titulo, descricao, data_lembrete, hora_lembrete, cor, tipo_visibilidade, email_notificacao)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssss", $usuario_id, $titulo, $descricao, $data, $hora, $cor, $visibilidade, $email_notif);
        }
        
        if ($stmt->execute()) {
            set_flash_message('success', 'Lembrete salvo com sucesso!');
        } else {
            set_flash_message('danger', 'Erro ao salvar no banco.');
        }

    } catch (Exception $e) {
        set_flash_message('danger', "Erro: " . $e->getMessage());
    }
}

header('Location: ../pages/lembrete.php');
?>