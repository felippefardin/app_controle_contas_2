<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_tenant_admin();
require_once '../includes/utils.php'; // Importa utils

/* ============================================================
   1. VERIFICA LOGIN
   ============================================================ */
if (empty($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

/* ============================================================
   2. VARIÁVEIS GERAIS
   ============================================================ */
$nivel       = $_SESSION['nivel_acesso'] ?? 'padrao';
$id_usuario  = $_SESSION['usuario_id'];
$tenant_id   = $_SESSION['tenant_id'] ?? null;
$is_admin    = in_array($nivel, ['admin', 'master', 'proprietario']);

/* ============================================================
   3. CONEXÕES
   ============================================================ */
$connTenant = getTenantConnection();
$connMaster = getMasterConnection();

if (!$connTenant || !$connMaster) {
    die("Erro de conexão com o banco de dados.");
}

/* ============================================================
   4. LÓGICA DE LIMITE DO PLANO
   ============================================================ */
$pode_adicionar = false;
$total_ativos   = 0;
$limite_total   = 0;

if ($is_admin && $tenant_id) {
    $stmtPlan = $connMaster->prepare("SELECT plano_atual, usuarios_extras FROM tenants WHERE tenant_id = ?");
    $stmtPlan->bind_param("s", $tenant_id);
    $stmtPlan->execute();
    $dadosPlano = $stmtPlan->get_result()->fetch_assoc();
    $stmtPlan->close();

    $planoAtual  = $dadosPlano['plano_atual'] ?? 'basico';
    $extras      = intval($dadosPlano['usuarios_extras'] ?? 0);

    $limites = ['basico' => 3, 'plus' => 6, 'essencial' => 16];
    $limiteBase = $limites[$planoAtual] ?? 3;
    $limite_total = $limiteBase + $extras;

    $resCount = $connTenant->query("SELECT COUNT(*) AS total FROM usuarios WHERE status = 'ativo' AND deleted_at IS NULL");
    $total_ativos = intval($resCount->fetch_assoc()['total']);
    $pode_adicionar = ($total_ativos < $limite_total);
}

/* ============================================================
   5. CONSULTA DE USUÁRIOS
   ============================================================ */
if ($is_admin) {
    $stmt = $connTenant->prepare("SELECT id, nome, email, cpf, telefone, status, perfil, nivel_acesso FROM usuarios WHERE deleted_at IS NULL ORDER BY nome ASC");
} else {
    $stmt = $connTenant->prepare("SELECT id, nome, email, cpf, telefone, status, perfil, nivel_acesso FROM usuarios WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $id_usuario);
}

$stmt->execute();
$result = $stmt->get_result();

include('../includes/header.php');

// --- EXIBE O FLASH CARD CENTRALIZADO ---
display_flash_message();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciar Usuários</title>

<style>
/* ========================
   ESTILOS GERAIS
   ======================== */
body { margin: 0; padding: 0; background: #121212; font-family: Arial, sans-serif; color: #ddd; }

/* MODO FULL DESKTOP: width 100% e max-width 98% */
.container-usuarios { 
    width: 100%;
    max-width: 98%; 
    margin: 30px auto; 
    padding: 25px; 
    background: #1e1e1e; 
    border-radius: 8px; 
    box-shadow: 0 4px 10px rgba(0,0,0,0.3); 
    box-sizing: border-box;
}

.page-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }

.btn-novo { background: linear-gradient(135deg, #28a745, #218838); padding: 10px 18px; color: #fff; border-radius: 6px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 4px 10px rgba(40,167,69,0.3); white-space: nowrap; }
.btn-novo:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(40,167,69,0.5); }
.btn-bloqueado { background: #333; color: #aaa; padding: 10px 18px; border: 1px solid #444; border-radius: 6px; white-space: nowrap; }

/* TABELA RESPONSIVA */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; /* Scroll suave no iOS */
}

.custom-table { width: 100%; border-collapse: collapse; background: #252525; border-radius: 6px; overflow: hidden; min-width: 600px; /* Garante que a tabela não esmague demais no mobile */ }
.custom-table th { background: #2c2c2c; color: #00bfff; padding: 14px; text-transform: uppercase; font-size: 0.85rem; text-align: left; }
.custom-table td { padding: 14px; border-bottom: 1px solid #333; vertical-align: middle; }

.status-badge { padding: 6px 12px; border-radius: 12px; font-size: 0.78rem; font-weight: bold; display: inline-block; }
.status-ativo { border: 1px solid #2ecc71; color: #2ecc71; background: rgba(46,204,113,0.15); }
.status-inativo { border: 1px solid #e74c3c; color: #e74c3c; background: rgba(231,76,60,0.15); }

.action-btn { width: 32px; height: 32px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; margin-left: 5px; text-decoration: none; border: none; cursor: pointer; transition: opacity 0.2s; }
.action-btn:hover { opacity: 0.8; }
.btn-edit { background: #ffc107; color: black; }
.btn-toggle-on { background: #28a745; }
.btn-toggle-off { border: 1px solid #dc3545; color: #dc3545; }
.btn-delete { background: #dc3545; }

/* ========================
   MEDIA QUERIES (RESPONSIVIDADE)
   ======================== */
@media (max-width: 768px) {
    .container-usuarios {
        padding: 15px;
        margin: 10px auto;
        width: 100%;
        max-width: 100%;
        border-radius: 0;
    }

    .page-header {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }

    .page-header h2 {
        margin: 0;
        font-size: 1.5rem;
    }

    /* Botão Novo ocupa largura total no mobile */
    .btn-novo, .btn-bloqueado {
        justify-content: center;
        width: 100%;
        box-sizing: border-box;
    }

    /* Container de botões no header */
    .page-header > div {
        display: flex;
        flex-direction: column;
        width: 100%;
        gap: 10px;
    }

    /* Ajuste na tabela para texto não quebrar em lugares ruins */
    .custom-table th, .custom-table td {
        padding: 10px;
        white-space: nowrap;
    }
}
</style>
</head>

<body>

<div class="container-usuarios">

    <div class="page-header">
        <h2><i class="fas fa-users"></i> Gerenciar Usuários</h2>

        <?php if ($is_admin): ?>
            <a href="lixeira.php" class="btn-novo" style="background:#6c757d"><i class="fas fa-trash-restore"></i> Lixeira</a>
            <?php if ($pode_adicionar): ?>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="text-muted"><i class="fas fa-chart-pie"></i> Uso: <b><?= $total_ativos ?>/<?= $limite_total ?></b></span>
                    <a href="add_usuario.php" class="btn-novo"><i class="fas fa-plus"></i> Novo Usuário</a>
                </div>
            <?php else: ?>
                <div>
                    <button class="btn-bloqueado" disabled><i class="fas fa-lock"></i> Limite Atingido (<?= $total_ativos ?>/<?= $limite_total ?>)</button>
                    <a href="minha_assinatura.php" style="color:#ffc107;font-weight:bold;margin-left:10px; text-align:center; display:block; margin-top:5px;">Aumentar Limite</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nome / Perfil</th>
                    <th>Email / Contato</th>
                    <th>Status</th>
                    <th style="text-align:right;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($u = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($u['nome']) ?></strong><br>
                            <span class="text-muted"><?= ucfirst($u['nivel_acesso']) ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($u['email']) ?><br>
                            <span class="text-muted"><?= htmlspecialchars($u['telefone'] ?? '') ?></span>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'ativo'): ?>
                                <span class="status-badge status-ativo">Ativo</span>
                            <?php else: ?>
                                <span class="status-badge status-inativo">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="editar_usuario.php?id=<?= $u['id'] ?>" class="action-btn btn-edit" title="Editar"><i class="fas fa-pen"></i></a>
                            
                            <?php if ($is_admin && $u['id'] != $id_usuario): ?>
                                <?php if ($u['status'] === 'ativo'): ?>
                                    <form action="../actions/toggle_status.php" method="post" style="display:inline" onsubmit="return confirm('Inativar este usuário?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button type="submit" class="action-btn btn-toggle-off" title="Inativar"><i class="fas fa-ban"></i></button></form>
                                <?php else: ?>
                                    <?php if ($pode_adicionar): ?>
                                        <form action="../actions/toggle_status.php" method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button type="submit" class="action-btn btn-toggle-on" title="Reativar"><i class="fas fa-check"></i></button></form>
                                    <?php else: ?>
                                        <button class="action-btn btn-toggle-off" disabled style="opacity:.5;"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <button class="action-btn btn-delete" onclick="abrirModal(<?= $u['id'] ?>, '<?= addslashes($u['nome']) ?>')" title="Excluir"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center;padding:25px;">Nenhum usuário encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalExclusao" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:9999;justify-content:center;align-items:center;">
    <div style="background:#252525;padding:25px;border-radius:8px;max-width:400px;width:90%;text-align:center;border:1px solid #444;">
        <h3 style="color:#dc3545;"><i class="fas fa-trash-alt"></i> Confirmar Exclusão</h3>
        <p>Deseja realmente excluir o usuário:</p>
        <p><strong id="nomeDel" style="font-size:1.1rem;"></strong></p>
        <p style="color:#aaa;font-size:0.9rem;">Essa ação é irreversível.</p>
        
        <form action="../actions/excluir_usuario.php" method="POST" style="margin-top:15px;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="idUsuarioDel">
            
            <div style="display:flex;justify-content:center;gap:10px;">
                <button type="button" onclick="fecharModal()" style="padding:10px 18px;background:#444;border:none;border-radius:6px;color:#fff;cursor:pointer;">Cancelar</button>
                <button type="submit" style="padding:10px 18px;background:#dc3545;border:none;border-radius:6px;color:#fff;font-weight:bold;cursor:pointer;">Excluir</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal(id, nome) {
    document.getElementById("nomeDel").innerText = nome;
    document.getElementById("idUsuarioDel").value = id;
    document.getElementById("modalExclusao").style.display = "flex";
}
function fecharModal() {
    document.getElementById("modalExclusao").style.display = "none";
}
window.onclick = function(e) {
    if (e.target.id === "modalExclusao") fecharModal();
}
</script>

</body>
</html>

<?php
$stmt->close();
$connTenant->close();
$connMaster->close();
include('../includes/footer.php');
?>
