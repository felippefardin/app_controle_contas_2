<?php
session_start();
require_once '../database.php';
require_once '../includes/utils.php';

if (!isset($_SESSION['usuario_logado']) || !isset($_SESSION['tenant_id'])) {
    header("Location: ../pages/login.php");
    exit;
}

$connMaster = getMasterConnection();
$connTenant = getTenantConnection();

if (!$connMaster || !$connTenant) {
    set_flash_message('danger', "Erro de conexão com o banco de dados.");
    header("Location: ../pages/minha_assinatura.php");
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$qtd_remover = (int)($_POST['qtd_remover'] ?? 1);

try {
    $stmt = $connMaster->prepare("SELECT plano_atual, usuarios_extras FROM tenants WHERE tenant_id = ?");
    $stmt->bind_param("s", $tenant_id);
    $stmt->execute();
    $dados = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $extras_atuais = (int)($dados['usuarios_extras'] ?? 0);
    $plano_atual = $dados['plano_atual'] ?? 'basico';

    if ($extras_atuais < $qtd_remover) {
        throw new Exception("Você não possui usuários extras suficientes para remover.");
    }

    $mapa_planos = ['basico' => 3, 'plus' => 6, 'essencial' => 16];
    $limite_base = $mapa_planos[$plano_atual] ?? 3;
    $novo_limite_total = $limite_base + ($extras_atuais - $qtd_remover);

    $resultCount = $connTenant->query("SELECT COUNT(*) as total FROM usuarios WHERE status = 'ativo' AND deleted_at IS NULL");
    $rowC = $resultCount->fetch_assoc();
    $usuarios_ativos = (int)$rowC['total'];

    if ($usuarios_ativos > $novo_limite_total) {
        throw new Exception("Não é possível reduzir. Você tem <b>$usuarios_ativos ativos</b>, mas o novo limite seria <b>$novo_limite_total</b>. Inative usuários antes.");
    }

    $stmtUpdate = $connMaster->prepare("UPDATE tenants SET usuarios_extras = usuarios_extras - ? WHERE tenant_id = ?");
    $stmtUpdate->bind_param("is", $qtd_remover, $tenant_id);
    
    if ($stmtUpdate->execute()) {
        set_flash_message('success', "Extra removido. Novo limite: $novo_limite_total.");
    } else {
        throw new Exception("Erro ao atualizar o banco de dados.");
    }
    $stmtUpdate->close();

} catch (Exception $e) {
    set_flash_message('danger', $e->getMessage());
}

$connMaster->close();
$connTenant->close();

header("Location: ../pages/minha_assinatura.php");
exit;
?>
