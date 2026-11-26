<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_super_admin();
require_csrf();

// Verifica se é Super Admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../pages/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $connMaster = getMasterConnection();

    if ($id > 0) {
        // Muda o status para 'contatado' (Verde/Feito)
        $stmt = $connMaster->prepare("UPDATE solicitacoes_suporte_inicial SET status = 'contatado' WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['msg_suporte'] = "Status alterado para Contatado (Verde)!";
        } else {
            $_SESSION['msg_suporte'] = "Erro ao atualizar status.";
        }
        $stmt->close();
    }
    $connMaster->close();
}

// Retorna ao Dashboard
header('Location: ../pages/admin/dashboard.php');
exit;
?>
