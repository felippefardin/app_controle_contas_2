<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_tenant_admin();
require_csrf();
require_once '../includes/utils.php'; // Importa utils

// 1. Verifica Sessão
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. Verifica Permissão
$nivel = $_SESSION['nivel_acesso'] ?? 'padrao';
if ($nivel !== 'admin' && $nivel !== 'master' && $nivel !== 'proprietario') {
    set_flash_message('danger', 'Permissão negada para excluir usuários.');
    header('Location: ../pages/usuarios.php');
    exit;
}

// 3. Verifica Método POST (Segurança)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('danger', 'Método inválido.');
    header('Location: ../pages/usuarios.php');
    exit;
}

// 4. Verifica CSRF (Opcional, se implementado no form)
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    set_flash_message('danger', 'Token de segurança inválido.');
    header('Location: ../pages/usuarios.php');
    exit;
}


$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id_proprio = $_SESSION['usuario_id'];
$ownerId = get_data_owner_id();

if ($id && $id != $id_proprio && $id != $ownerId) {
    $conn = getTenantConnection();
    if (!$conn) {
        set_flash_message('danger', 'Erro de conexão com o banco.');
        header('Location: ../pages/usuarios.php');
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        set_flash_message('success', 'Usuário excluído com sucesso!');
    } else {
        set_flash_message('danger', 'Erro ao excluir usuário.');
    }
} else {
    set_flash_message('danger', 'ID inválido ou tentativa de excluir o próprio usuário.');
}

header('Location: ../pages/usuarios.php');
exit;
?>
