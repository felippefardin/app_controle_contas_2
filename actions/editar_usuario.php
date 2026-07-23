<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; 

$nivel_logado = $_SESSION['nivel_acesso'] ?? 'padrao';
$is_admin = in_array($nivel_logado, ['admin', 'master', 'proprietario']);
require_tenant_admin();
require_csrf();

if (!isset($_POST['id'])) { 
    header('Location: ../pages/usuarios.php'); 
    exit; 
}

$id = intval($_POST['id']);
$ownerId = get_data_owner_id();
if ($id === $ownerId) {
    set_flash_message('danger', 'O usuário proprietário não pode ser rebaixado ou alterado por esta tela.');
    header('Location: ../pages/usuarios.php');
    exit;
}
$nome = trim($_POST['nome']);
$email = trim($_POST['email']);
$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$senha = $_POST['senha'];
$novo_nivel = $_POST['nivel'] ?? 'padrao';

$conn = getTenantConnection();

// 1. Buscar o e-mail atual antes da mudança (necessário para localizar no Master)
$emailAntigo = '';
$stmtGetOld = $conn->prepare("SELECT email FROM usuarios WHERE id = ? AND deleted_at IS NULL");
$stmtGetOld->bind_param("i", $id);
$stmtGetOld->execute();
$stmtGetOld->bind_result($emailAntigo);
$stmtGetOld->fetch();
$stmtGetOld->close();

// 2. Preparar Permissões (JSON)
$json_permissoes = null;
if ($novo_nivel === 'padrao' && isset($_POST['permissoes']) && is_array($_POST['permissoes'])) {
    $json_permissoes = json_encode($_POST['permissoes']);
}

// 3. Atualizar Dados no banco do Tenant
if (!empty($senha)) {
    // Atualização com nova senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    $sql = "UPDATE usuarios SET nome=?, email=?, cpf=?, nivel_acesso=?, senha=?, permissoes=? WHERE id=? AND deleted_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $nome, $email, $cpf, $novo_nivel, $senha_hash, $json_permissoes, $id);
} else {
    // Atualização sem alterar a senha
    $sql = "UPDATE usuarios SET nome=?, email=?, cpf=?, nivel_acesso=?, permissoes=? WHERE id=? AND deleted_at IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $nome, $email, $cpf, $novo_nivel, $json_permissoes, $id);
}

if ($stmt->execute()) {
    // 4. Sincronização com o Banco MASTER
    try {
        $connMaster = getMasterConnection();
        if ($connMaster && !$connMaster->connect_error) {
            if (!empty($senha)) {
                $sqlMaster = "UPDATE usuarios SET nome=?, email=?, senha=? WHERE email=? AND tenant_id=?";
                $stmtM = $connMaster->prepare($sqlMaster);
                $tenantId = (string)($_SESSION['tenant_id'] ?? '');
                $stmtM->bind_param("sssss", $nome, $email, $senha_hash, $emailAntigo, $tenantId);
            } else {
                $sqlMaster = "UPDATE usuarios SET nome=?, email=? WHERE email=? AND tenant_id=?";
                $stmtM = $connMaster->prepare($sqlMaster);
                $tenantId = (string)($_SESSION['tenant_id'] ?? '');
                $stmtM->bind_param("ssss", $nome, $email, $emailAntigo, $tenantId);
            }
            $stmtM->execute();
            $stmtM->close();
            $connMaster->close();
        }
    } catch (Exception $e) {
        error_log("Erro de sincronização Master: " . $e->getMessage());
    }

    set_flash_message('success', "Usuário <b>$nome</b> e suas permissões foram atualizados!");
} else {
    set_flash_message('danger', 'Erro ao atualizar no banco: ' . $conn->error);
}

$stmt->close();
$conn->close();
header("Location: ../pages/usuarios.php");
exit;
