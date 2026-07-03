<?php
// ----------------------------------------------
// home.php (DASHBOARD INTUITIVA - OTIMIZADA)
// ----------------------------------------------
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';


// [MANTIDO] 🔒 Lógica de Segurança e Sessão Original
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: ../pages/login.php?erro=nao_logado");
    exit();
}

if (
    isset($_SESSION['is_master_admin']) &&
    $_SESSION['is_master_admin'] === true &&
    !isset($_SESSION['proprietario_id_original']) && 
    !isset($_SESSION['super_admin_original']) 
) {
    header("Location: ../pages/admin/dashboard.php");
    exit();
}

if (!isset($_SESSION['tenant_id'])) {
    session_destroy();
    header("Location: ../pages/login.php?erro=tenant_inexistente");
    exit();
}

// 📌 Pega dados do usuário
$usuario_id    = $_SESSION['usuario_id'];
$tenant_id     = $_SESSION['tenant_id'];
$nome_usuario  = $_SESSION['nome'];
$perfil        = $_SESSION['nivel_acesso']; 

// 🆕 CAPTURA O PLANO ATUAL PARA CONTROLE DE EXIBIÇÃO
$planoAtual = $_SESSION['plano'] ?? 'basico';

// 📌 Conexão do tenant
$conn = getTenantConnection();
if (!$conn) {
    session_destroy();
    header("Location: ../pages/login.php?erro=db_tenant");
    exit();
}

// [MANTIDO] 🔍 LÓGICA DE PERMISSÕES
$permissoes_usuario = [];
if ($perfil !== 'admin' && $perfil !== 'proprietario' && $perfil !== 'master') {
    $stmtPerm = $conn->prepare("SELECT permissoes FROM usuarios WHERE id = ?");
    if ($stmtPerm) {
        $stmtPerm->bind_param("i", $usuario_id);
        $stmtPerm->execute();
        $resPerm = $stmtPerm->get_result();
        if ($rowPerm = $resPerm->fetch_assoc()) {
            $json = $rowPerm['permissoes'];
            if (!empty($json)) {
                $permissoes_usuario = json_decode($json, true);
            }
        }
        $stmtPerm->close();
    }
    if (!is_array($permissoes_usuario)) $permissoes_usuario = [];
}

function temPermissao($arquivo_chave, $permissoes_array, $perfil_atual) {
    if ($perfil_atual === 'admin' || $perfil_atual === 'proprietario' || $perfil_atual === 'master') {
        return true;
    }
    return in_array($arquivo_chave, $permissoes_array);
}

// [MANTIDO] 📊 LÓGICA DO DASHBOARD (KPIs e Listas)
$meses_pt = [
    'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr', 'May' => 'Mai', 'Jun' => 'Jun',
    'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
];
$nome_mes_atual = $meses_pt[date('M')];
$mesAtual = date('Y-m');
$hoje = date('Y-m-d');

// 1. Totais Rápidos (KPIs)
$sqlResumo = "
    SELECT 
        (SELECT COALESCE(SUM(valor), 0) FROM contas_receber WHERE usuario_id = ? AND status = 'pendente' AND DATE_FORMAT(data_vencimento, '%Y-%m') = ?) as receber_mes,
        (SELECT COALESCE(SUM(valor), 0) FROM contas_pagar WHERE usuario_id = ? AND status = 'pendente' AND DATE_FORMAT(data_vencimento, '%Y-%m') = ?) as pagar_mes,
        (SELECT COALESCE(SUM(valor), 0) FROM contas_pagar WHERE usuario_id = ? AND status = 'pendente' AND data_vencimento = ?) as pagar_hoje
";
$stmtDash = $conn->prepare($sqlResumo);
$stmtDash->bind_param("isisis", $usuario_id, $mesAtual, $usuario_id, $mesAtual, $usuario_id, $hoje);
$stmtDash->execute();
$dashData = $stmtDash->get_result()->fetch_assoc();
$stmtDash->close();

// 2. Saldo em Caixa
$sqlSaldo = "
    SELECT 
    (COALESCE((SELECT SUM(valor) FROM contas_receber WHERE usuario_id = ? AND status = 'baixada'),0) + 
     COALESCE((SELECT SUM(valor) FROM caixa_diario WHERE usuario_id = ?),0)) 
    - 
    COALESCE((SELECT SUM(valor) FROM contas_pagar WHERE usuario_id = ? AND status = 'baixada'),0) 
    as saldo_real
";
$stmtSaldo = $conn->prepare($sqlSaldo);
$stmtSaldo->bind_param("iii", $usuario_id, $usuario_id, $usuario_id);
$stmtSaldo->execute();
$saldoCaixa = $stmtSaldo->get_result()->fetch_assoc()['saldo_real'] ?? 0;
$stmtSaldo->close();

// 3. Verifica se é Usuário Novo
$novoUsuario = false;
$checkNew = $conn->query("SELECT id FROM contas_bancarias WHERE id_usuario = $usuario_id LIMIT 1");
if ($checkNew && $checkNew->num_rows == 0) {
    $novoUsuario = true;
}

// 4. Listas de Contas para HOJE
$listReceberHoje = [];
$qRH = $conn->prepare("SELECT descricao, valor FROM contas_receber WHERE usuario_id = ? AND status = 'pendente' AND data_vencimento = ? ORDER BY valor DESC");
$qRH->bind_param("is", $usuario_id, $hoje);
$qRH->execute();
$resRH = $qRH->get_result();
while($row = $resRH->fetch_assoc()) { $listReceberHoje[] = $row; }
$qRH->close();

$listPagarHoje = [];
$qPH = $conn->prepare("SELECT descricao, valor FROM contas_pagar WHERE usuario_id = ? AND status = 'pendente' AND data_vencimento = ? ORDER BY valor DESC");
$qPH->bind_param("is", $usuario_id, $hoje);
$qPH->execute();
$resPH = $qPH->get_result();
while($row = $resPH->fetch_assoc()) { $listPagarHoje[] = $row; }
$qPH->close();

// [MANTIDO] Lógica Master, Assinatura e Chat
$connMaster = getMasterConnection();
$status_assinatura = 'ok';

if ($connMaster) {
    $tenant = getTenantById($tenant_id, $connMaster);
    if ($tenant) {
        // $_SESSION['subscription_status'] = validarStatusAssinatura($tenant);
        $_SESSION['subscription_status'] = 'ok';
        $status_assinatura = $_SESSION['subscription_status'];
    }

    $conviteChat = null;
    try {
        $master_usuario_id = isset($tenant['usuario_id']) ? $tenant['usuario_id'] : 0;
        $sqlChat = "SELECT id FROM chat_sessions WHERE usuario_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1";
        $stmtChat = $connMaster->prepare($sqlChat);
        if ($stmtChat) {
            $stmtChat->bind_param("i", $master_usuario_id);
            $stmtChat->execute();
            $resChat = $stmtChat->get_result();
            $conviteChat = $resChat->fetch_assoc();
            $stmtChat->close();
        }
    } catch (Exception $e) { }
}

// [MANTIDO] Lembretes
$popupLembrete = false;
try {
    if (temPermissao('lembretes.php', $permissoes_usuario, $perfil)) {
        $checkTable = $conn->query("SHOW TABLES LIKE 'lembretes'");
        if($checkTable && $checkTable->num_rows > 0) {
            $sqlLembrete = "SELECT COUNT(*) as total FROM lembretes WHERE usuario_id = ? AND data_lembrete = CURDATE()";
            $stmtL = $conn->prepare($sqlLembrete);
            if ($stmtL) {
                $stmtL->bind_param("i", $usuario_id);
                $stmtL->execute();
                $resL = $stmtL->get_result();
                $rowL = $resL->fetch_assoc();
                if ($rowL['total'] > 0) {
                    $popupLembrete = true;
                }
                $stmtL->close();
            }
        }
    }
} catch (Exception $e) { }

$produtos_estoque_baixo = $_SESSION['produtos_estoque_baixo'] ?? [];
unset($_SESSION['produtos_estoque_baixo']);

include('../includes/header.php');

// [MANTIDO] Promoção (Gift)
$promo_modal = null;
if ($tenant_id && $connMaster) {
    $sqlPromo = "SELECT tp.id, c.descricao, c.valor, c.tipo_desconto FROM tenant_promocoes tp JOIN cupons_desconto c ON tp.cupom_id = c.id WHERE tp.tenant_id = ? AND tp.visualizado = 0 AND tp.ativo = 1 LIMIT 1";
    $stmtP = $connMaster->prepare($sqlPromo);
    if ($stmtP) {
        $tenant_id_numeric = $_SESSION['tenant_id_master'] ?? null;
        if (!$tenant_id_numeric) {
            $t_uuid = $_SESSION['tenant_id'];
            $qT = $connMaster->query("SELECT id FROM tenants WHERE tenant_id = '$t_uuid' LIMIT 1");
            if($qT && $rowT = $qT->fetch_assoc()){ $tenant_id_numeric = $rowT['id']; }
        }
        if ($tenant_id_numeric) {
            $stmtP->bind_param("i", $tenant_id_numeric);
            $stmtP->execute();
            $resPromo = $stmtP->get_result();
            $promo_modal = $resPromo->fetch_assoc();
        }
        $stmtP->close();
    }
    $connMaster->close();
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    /* =========================================
       AJUSTES DE LAYOUT E TEMA DINÂMICO
    ========================================= */
    main {
        max-width: 100% !important;
        margin: 0 !important;
        padding-top: 90px !important; 
        padding-left: 20px;
        padding-right: 20px;
        /* AJUSTE AQUI: Aumentado para 100px para o footer não encavalar nos ícones */
        padding-bottom: 100px !important; 
    }

    body {
        background-color: var(--bg-body, #f4f6f9) !important; 
        color: var(--text-primary, #333) !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        overflow-x: hidden;
    }

    .home-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0;
        animation: fadeIn 0.6s ease;
    }

    /* Cabeçalho Home */
    .home-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color, #ddd);
    }
    .user-info h2 { font-size: 1.5rem; color: var(--text-primary, #333); margin: 0; font-weight: 700; }
    .user-info p { color: var(--text-secondary, #666); margin: 0; font-size: 0.95rem; }
    .date-info { color: var(--text-secondary, #666) !important; font-size: 0.9rem; font-weight: 500; }
    
    /* Ações Rápidas (Quick Actions) */
    .quick-actions { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
    .btn-quick {
        flex: 1; min-width: 140px; padding: 15px; border-radius: 12px; text-align: center; text-decoration: none;
        font-weight: bold; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 8px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .btn-quick i { font-size: 1.6rem; margin-bottom: 2px; }
    .btn-quick:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.15); opacity: 0.95; }
    .btn-quick.receita { background: linear-gradient(135deg, #00b09b, #96c93d); color: white; }
    .btn-quick.despesa { background: linear-gradient(135deg, #ff5f6d, #ffc371); color: white; }
    .btn-quick.venda   { background: linear-gradient(135deg, #36d1dc, #5b86e5); color: white; }

    /* KPIs */
    .dashboard-kpi { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .kpi-card {
        padding: 20px; border-radius: 16px; background: var(--bg-card, #fff); color: var(--text-primary, #333);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--border-color, #eaeaea); border-left: 5px solid transparent;
        display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s; position: relative; overflow: hidden;
    }
    .kpi-card:hover { transform: translateY(-3px); }
    .kpi-content { display: flex; flex-direction: column; z-index: 2; }
    .kpi-title { font-size: 0.85rem; color: var(--text-secondary, #777); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; font-weight: 600; }
    .kpi-value { font-size: 1.6rem; font-weight: 700; }
    .kpi-icon { font-size: 2.5rem; opacity: 0.15; position: absolute; right: 15px; bottom: 10px; z-index: 1; }

    /* Listas de Hoje */
    .today-section { margin-bottom: 30px; }
    .today-card {
        background: var(--bg-card, #fff); border-radius: 16px; overflow: hidden; height: 100%;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid var(--border-color, #eaeaea); color: var(--text-primary, #333);
    }
    .today-header { padding: 15px 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; background: rgba(128, 128, 128, 0.03); border-bottom: 1px solid var(--border-color, #eaeaea); }
    .today-list-container { max-height: 300px; overflow-y: auto; }
    .today-item { padding: 15px 20px; border-bottom: 1px solid var(--border-color, #eaeaea); display: flex; justify-content: space-between; align-items: center; transition: background 0.2s; color: var(--text-primary, #333); }
    .today-item:hover { background: rgba(128, 128, 128, 0.05); }

    /* =======================================================
       NOVO SISTEMA DE ABAS (TABS)
    ======================================================= */
    .tabs-container {
        margin-top: 40px;
    }
    
    .tabs-nav {
        display: flex;
        gap: 10px;
        border-bottom: 1px solid var(--border-color, #ddd);
        padding-bottom: 2px;
        margin-bottom: 20px;
        overflow-x: auto; /* Permite scroll lateral no mobile */
        white-space: nowrap;
    }

    .tab-btn {
        background: transparent;
        border: none;
        padding: 12px 20px;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-secondary, #666);
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-btn:hover {
        color: var(--highlight-color, #0d6efd);
        background-color: rgba(13, 110, 253, 0.05);
        border-radius: 8px 8px 0 0;
    }

    .tab-btn.active {
        color: var(--highlight-color, #0d6efd);
        border-bottom-color: var(--highlight-color, #0d6efd);
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.4s ease;
    }

    .tab-content.active {
        display: block;
    }

    /* Estilo do Menu Grid dentro das abas */
    .menu-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    .menu-item {
        background: var(--bg-card, #fff); padding: 15px 10px; border-radius: 12px; text-align: center; text-decoration: none;
        color: var(--text-secondary, #555); transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center;
        gap: 10px; border: 1px solid transparent; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .menu-item i { font-size: 1.6rem; color: var(--highlight-color, #0d6efd); transition: transform 0.3s; }
    .menu-item span { font-size: 0.85rem; font-weight: 500; line-height: 1.2; }
    .menu-item:hover {
        background: var(--bg-card, #fff); border-color: var(--highlight-color, #0d6efd); transform: translateY(-5px);
        color: var(--text-primary, #333); box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .menu-item:hover i { transform: scale(1.1); }

    /* Utilitários e Modais */
    .text-success-custom { color: #00E676 !important; }
    .text-danger-custom { color: #FF5252 !important; }
    .empty-msg { padding: 30px; text-align: center; color: var(--text-secondary, #888); font-style: italic; }
    .alert-estoque { background: rgba(220, 53, 69, 0.1); border: 1px solid #dc3545; color: #d63384; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
    .chat-alert { background: linear-gradient(45deg, #ff4444, #ff8888); color: white; padding: 12px; text-align: center; font-weight: bold; cursor: pointer; border-radius: 50px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(255, 68, 68, 0.4); animation: pulse 2s infinite; text-decoration: none; display: block; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.02); } 100% { transform: scale(1); } }
    
    .custom-modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
    .custom-modal-content { background-color: var(--bg-card, #fff); color: var(--text-primary, #333); margin: 15% auto; padding: 25px; border: 1px solid var(--border-color, #444); width: 90%; max-width: 500px; border-radius: 16px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .btn-modal { padding: 10px 20px; border-radius: 8px; border: none; font-weight: bold; cursor: pointer; margin: 5px; }
    .btn-cancel { background: #6c757d; color: #fff; }
    .btn-accept { background: var(--highlight-color, #0d6efd); color: #fff; }

    @media (max-width: 768px) {
        .home-header { flex-direction: column; text-align: center; gap: 10px; }
        .dashboard-kpi { grid-template-columns: 1fr 1fr; gap: 10px; }
    }
</style>

<div class="home-container">

    <div class="home-header">
        <div class="user-info">
            <h2 id="saudacao">Bem-vindo(a)</h2>
            <p><?= htmlspecialchars($nome_usuario) ?> • <?= ucfirst(htmlspecialchars($perfil)) ?></p>
        </div>
        <div class="date-info">
            <i class="far fa-calendar-alt"></i> <?= date('d/m/Y') ?>
        </div>
    </div>

    <?php if (temPermissao('vendas.php', $permissoes_usuario, $perfil) || temPermissao('contas_receber.php', $permissoes_usuario, $perfil)): ?>
    <div class="quick-actions">
        <?php if (temPermissao('vendas.php', $permissoes_usuario, $perfil) && $planoAtual !== 'basico'): ?>
            <a href="vendas.php" class="btn-quick venda">
                <i class="fas fa-cash-register"></i> <span>PDV / Vender</span>
            </a>
        <?php endif; ?>
        
        <?php if (temPermissao('contas_receber.php', $permissoes_usuario, $perfil)): ?>
            <a href="contas_receber.php" class="btn-quick receita">
                <i class="fas fa-plus-circle"></i> <span>Nova Receita</span>
            </a>
        <?php endif; ?>
        <?php if (temPermissao('contas_pagar.php', $permissoes_usuario, $perfil)): ?>
            <a href="contas_pagar.php" class="btn-quick despesa">
                <i class="fas fa-minus-circle"></i> <span>Nova Despesa</span>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($novoUsuario): ?>
    <div style="background: linear-gradient(135deg, #00bfff, #0066cc); padding: 20px; border-radius: 12px; margin-bottom: 25px; color: white;">
        <h4>🚀 Primeiros Passos</h4>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
            <a href="banco_cadastro.php" class="btn btn-sm btn-light text-primary">1. Bancos</a>
            <a href="cadastrar_pessoa_fornecedor.php" class="btn btn-sm btn-light text-primary">2. Clientes</a>
            <a href="contas_receber.php" class="btn btn-sm btn-light text-primary">3. Financeiro</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="dashboard-kpi">
        <div class="kpi-card" style="border-left-color: #00E676;">
            <div class="kpi-content">
                <span class="kpi-title">Saldo Disponível</span>
                <span class="kpi-value <?= $saldoCaixa >= 0 ? 'text-success-custom' : 'text-danger-custom' ?>">
                    R$ <?= number_format($saldoCaixa, 2, ',', '.') ?>
                </span>
            </div>
            <i class="fas fa-wallet kpi-icon" style="color: #00E676;"></i>
        </div>

        <div class="kpi-card" style="border-left-color: #FF5252;">
            <div class="kpi-content">
                <span class="kpi-title">Vence Hoje</span>
                <span class="kpi-value text-danger-custom">
                    R$ <?= number_format($dashData['pagar_hoje'] ?? 0, 2, ',', '.') ?>
                </span>
            </div>
            <i class="fas fa-calendar-day kpi-icon" style="color: #FF5252;"></i>
        </div>

        <div class="kpi-card" style="border-left-color: #00bfff;">
            <div class="kpi-content">
                <span class="kpi-title">Receita (<?= $nome_mes_atual ?>)</span>
                <span class="kpi-value">
                    R$ <?= number_format($dashData['receber_mes'] ?? 0, 2, ',', '.') ?>
                </span>
            </div>
            <i class="fas fa-chart-line kpi-icon" style="color: #00bfff;"></i>
        </div>

        <div class="kpi-card" style="border-left-color: #ffbb33;">
            <div class="kpi-content">
                <span class="kpi-title">Despesa (<?= $nome_mes_atual ?>)</span>
                <span class="kpi-value">
                    R$ <?= number_format($dashData['pagar_mes'] ?? 0, 2, ',', '.') ?>
                </span>
            </div>
            <i class="fas fa-file-invoice-dollar kpi-icon" style="color: #ffbb33;"></i>
        </div>
    </div>

    <?php if ($conviteChat): ?>
        <a class="chat-alert" onclick="abrirModalChat(<?php echo $conviteChat['id']; ?>)">
            <i class="fas fa-headset"></i> Suporte Online Disponível - Clique para iniciar
        </a>
    <?php endif; ?>
    <?php if (!empty($produtos_estoque_baixo) && temPermissao('controle_estoque.php', $permissoes_usuario, $perfil)): ?>
        <div class="alert-estoque">
            <strong><i class="fas fa-exclamation-triangle"></i> Estoque Baixo:</strong>
            <ul style="margin: 5px 0 0 20px;">
                <?php foreach ($produtos_estoque_baixo as $p): ?>
                    <li><?= htmlspecialchars($p['nome']) ?> (Restam: <?= intval($p['quantidade_estoque']) ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row today-section">
        <div class="col-md-6 mb-3">
            <div class="today-card">
                <div class="today-header">
                    <span style="color: #00E676;"><i class="fas fa-arrow-down"></i> Recebimentos Hoje</span>
                    <span class="badge bg-success rounded-pill"><?= count($listReceberHoje) ?></span>
                </div>
                <div class="today-list-container">
                    <?php if (empty($listReceberHoje)): ?>
                        <div class="empty-msg">Nenhum recebimento previsto.</div>
                    <?php else: ?>
                        <?php foreach($listReceberHoje as $item): ?>
                            <div class="today-item">
                                <span><?= htmlspecialchars($item['descricao']) ?></span>
                                <strong class="text-success-custom">R$ <?= number_format($item['valor'], 2, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="today-card">
                <div class="today-header">
                    <span style="color: #FF5252;"><i class="fas fa-arrow-up"></i> Pagamentos Hoje</span>
                    <span class="badge bg-danger rounded-pill"><?= count($listPagarHoje) ?></span>
                </div>
                <div class="today-list-container">
                    <?php if (empty($listPagarHoje)): ?>
                        <div class="empty-msg">Nenhum pagamento previsto.</div>
                    <?php else: ?>
                        <?php foreach($listPagarHoje as $item): ?>
                            <div class="today-item">
                                <span><?= htmlspecialchars($item['descricao']) ?></span>
                                <strong class="text-danger-custom">R$ <?= number_format($item['valor'], 2, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php 
    // VERIFICAÇÕES DE PERMISSÃO PARA OS GRUPOS
    $financeiro_items = ['contas_pagar.php', 'contas_pagar_baixadas.php', 'contas_receber.php', 'contas_receber_baixadas.php', 'lancamento_caixa.php', 'vendas_periodo.php'];
    $show_financeiro = false;
    foreach($financeiro_items as $item) { if(temPermissao($item, $permissoes_usuario, $perfil)) $show_financeiro = true; }

    $estoque_items = ['controle_estoque.php', 'vendas.php', 'compras.php'];
    $show_estoque = false;
    foreach($estoque_items as $item) { if(temPermissao($item, $permissoes_usuario, $perfil)) $show_estoque = true; }

    $cadastro_items = ['cadastrar_pessoa_fornecedor.php', 'perfil.php', 'banco_cadastro.php', 'categorias.php'];
    $show_cadastro = false;
    foreach($cadastro_items as $item) { if(temPermissao($item, $permissoes_usuario, $perfil)) $show_cadastro = true; }

    $sistema_items = ['lembretes.php', 'relatorios.php', 'trocar_usuario.php', 'usuarios.php', 'configuracao_fiscal.php'];
    $show_sistema = false;
    foreach($sistema_items as $item) { if(temPermissao($item, $permissoes_usuario, $perfil)) $show_sistema = true; }
    ?>

    <div class="tabs-container">
        <div class="tabs-nav">
            <?php if($show_financeiro): ?>
                <button class="tab-btn active" onclick="openTab(event, 'tab-financeiro')">
                    <i class="fas fa-coins"></i> Financeiro
                </button>
            <?php endif; ?>
            
            <?php if($show_estoque): ?>
                <button class="tab-btn" onclick="openTab(event, 'tab-comercial')">
                    <i class="fas fa-box-open"></i> Comercial
                </button>
            <?php endif; ?>
            
            <?php if($show_cadastro): ?>
                <button class="tab-btn" onclick="openTab(event, 'tab-cadastros')">
                    <i class="fas fa-folder-plus"></i> Cadastros
                </button>
            <?php endif; ?>
            
            <?php if($show_sistema): ?>
                <button class="tab-btn" onclick="openTab(event, 'tab-sistema')">
                    <i class="fas fa-cogs"></i> Sistema
                </button>
            <?php endif; ?>
        </div>

        <?php if($show_financeiro): ?>
        <div id="tab-financeiro" class="tab-content active">
            <div class="menu-grid">
                <?php if (temPermissao('contas_pagar.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="contas_pagar.php"><i class="fas fa-file-invoice-dollar"></i><span>A Pagar</span></a>
                <?php endif; ?>
                <?php if (temPermissao('contas_pagar_baixadas.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="contas_pagar_baixadas.php"><i class="fas fa-check-double"></i><span>Pagas</span></a>
                <?php endif; ?>
                <?php if (temPermissao('contas_receber.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="contas_receber.php"><i class="fas fa-hand-holding-dollar"></i><span>A Receber</span></a>
                <?php endif; ?>
                <?php if (temPermissao('contas_receber_baixadas.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="contas_receber_baixadas.php"><i class="fas fa-clipboard-check"></i><span>Recebidas</span></a>
                <?php endif; ?>
                <?php if (temPermissao('lancamento_caixa.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="lancamento_caixa.php"><i class="fas fa-exchange-alt"></i><span>Caixa</span></a>
                <?php endif; ?>
                
                <?php if (temPermissao('vendas_periodo.php', $permissoes_usuario, $perfil) && $planoAtual !== 'basico'): ?>
                    <a class="menu-item" href="vendas_periodo.php"><i class="fas fa-chart-line"></i><span>Rel. Vendas</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($show_estoque): ?>
        <div id="tab-comercial" class="tab-content" style="display:none;">
            <div class="menu-grid">
                <?php if (temPermissao('controle_estoque.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="controle_estoque.php"><i class="fas fa-boxes-stacked"></i><span>Estoque</span></a>
                <?php endif; ?>
                
                <?php if (temPermissao('vendas.php', $permissoes_usuario, $perfil) && $planoAtual !== 'basico'): ?>
                    <a class="menu-item" href="vendas.php"><i class="fas fa-cash-register"></i><span>PDV</span></a>
                <?php endif; ?>
                
                <?php if (temPermissao('compras.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="compras.php"><i class="fas fa-shopping-bag"></i><span>Compras</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($show_cadastro): ?>
        <div id="tab-cadastros" class="tab-content" style="display:none;">
            <div class="menu-grid">
                <?php if (temPermissao('cadastrar_pessoa_fornecedor.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="../pages/cadastrar_pessoa_fornecedor.php"><i class="fas fa-address-book"></i><span>Pessoas</span></a>
                <?php endif; ?>
                <?php if (temPermissao('perfil.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="perfil.php"><i class="fas fa-user-circle"></i><span>Perfil</span></a>
                <?php endif; ?>
                <?php if (temPermissao('banco_cadastro.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="../pages/banco_cadastro.php"><i class="fas fa-university"></i><span>Bancos</span></a>
                <?php endif; ?>
                <?php if (temPermissao('categorias.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="../pages/categorias.php"><i class="fas fa-tags"></i><span>Categorias</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($show_sistema): ?>
        <div id="tab-sistema" class="tab-content" style="display:none;">
            <div class="menu-grid">
                <?php if (temPermissao('lembretes.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="lembrete.php"><i class="fas fa-sticky-note"></i><span>Lembretes</span></a>
                <?php endif; ?>
                <?php if (temPermissao('relatorios.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="relatorios.php"><i class="fas fa-chart-pie"></i><span>Relatórios</span></a>
                <?php endif; ?>
                <?php if (temPermissao('trocar_usuario.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="selecionar_usuario.php"><i class="fas fa-users-cog"></i><span>Trocar User</span></a>
                <?php endif; ?>
                <?php if (temPermissao('usuarios.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="usuarios.php"><i class="fas fa-users"></i><span>Gerir Equipe</span></a>
                <?php endif; ?>
                <?php if (temPermissao('configuracao_fiscal.php', $permissoes_usuario, $perfil)): ?>
                    <a class="menu-item" href="configuracao_fiscal.php"><i class="fas fa-file-invoice"></i><span>Fiscal</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<div id="modalChatInvite" class="custom-modal">
  <div class="custom-modal-content">
    <div style="font-size:3rem; color: var(--highlight-color, #0d6efd); margin-bottom:10px;"><i class="fas fa-comments"></i></div>
    <h5>Convite de Suporte Online</h5>
    <p>Nossa equipe está disponível agora para te ajudar.</p>
    <div style="margin-top:20px;">
      <button type="button" class="btn-modal btn-cancel" onclick="document.getElementById('modalChatInvite').style.display='none'">Agora não</button>
      <button type="button" class="btn-modal btn-accept" id="btnAceitarChat">Iniciar Conversa</button>
    </div>
  </div>
</div>

<?php if ($popupLembrete): ?>
    <div id="toast-lembrete" onclick="window.location.href='lembrete.php'" 
         style="position: fixed; bottom: 30px; right: 30px; background: var(--highlight-color, #0d6efd); color: #fff; padding: 15px 25px; border-radius: 50px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 15px rgba(0,0,0,0.5); z-index: 9999; animation: slideUp 0.5s ease;">
        <i class="fas fa-bell"></i> Você tem lembretes para hoje!
    </div>
<?php endif; ?>

<script>
// SCRIPT DE ABAS (TABS)
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;

    // Esconde todos os conteúdos
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }

    // Remove classe active de todos os botões
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }

    // Mostra o atual e adiciona classe active
    document.getElementById(tabName).style.display = "block";
    // Pequeno delay para permitir animação se houver CSS transitions baseadas em classe
    setTimeout(() => {
        document.getElementById(tabName).classList.add("active");
    }, 10);
    
    evt.currentTarget.classList.add("active");
}

// Inicia primeira aba visível se nenhuma estiver ativa (fallback)
document.addEventListener("DOMContentLoaded", function() {
    const activeTab = document.querySelector('.tab-content.active');
    if (!activeTab) {
        const firstTab = document.querySelector('.tab-content');
        const firstBtn = document.querySelector('.tab-btn');
        if(firstTab) firstTab.style.display = 'block';
        if(firstBtn) firstBtn.classList.add('active');
    }
});

function atualizarSaudacao() {
    const agora = new Date();
    const hora = agora.getHours();
    let texto = "Olá";
    if (hora >= 5 && hora < 12) texto = "Bom dia";
    else if (hora >= 12 && hora < 18) texto = "Boa tarde";
    else texto = "Boa noite";
    
    const el = document.getElementById("saudacao");
    if(el) el.innerText = `${texto},`;
}
atualizarSaudacao();

let currentChatId = null;
function abrirModalChat(chatId) {
    currentChatId = chatId;
    document.getElementById('modalChatInvite').style.display = 'block';
}

const btnAceitarChat = document.getElementById('btnAceitarChat');
if(btnAceitarChat) {
    btnAceitarChat.addEventListener('click', function() {
        if(!currentChatId) return;
        const formData = new FormData();
        formData.append('action', 'aceitar_convite');
        formData.append('chat_id', currentChatId);
        fetch('../actions/chat_api.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                window.location.href = 'chat_suporte_online.php?chat_id=' + currentChatId;
            } else {
                alert('Erro ao iniciar o chat.');
            }
        });
    });
}
</script>

<?php if ($promo_modal): ?>
<div id="modalGift" class="custom-modal" style="display:block;">
    <div class="custom-modal-content" style="border: 1px solid #e67e22; box-shadow: 0 0 20px rgba(230, 126, 34, 0.3);">
        <div style="font-size: 3rem; color: #e67e22; margin-bottom: 10px;">
            <i class="fas fa-gift"></i>
        </div>
        <h2 style="color: var(--text-primary, #333);">Presente para você!</h2>
        <p style="font-size: 1.1rem; color: var(--text-secondary, #666); margin: 15px 0;">
            <strong style="color: #e67e22; font-size: 1.2rem;">
                <?= htmlspecialchars($promo_modal['descricao']) ?>
            </strong>
        </p>
        <button class="btn-modal btn-accept" style="background:#e67e22; width:100%; color:#fff;" onclick="fecharGift(<?= $promo_modal['id'] ?>)">
            Resgatar
        </button>
    </div>
</div>
<script>
function fecharGift(promoId) {
    fetch('../actions/marcar_promocao_lida.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + promoId
    }).then(() => {
        document.getElementById('modalGift').style.display = 'none';
    });
}
</script>
<?php endif; ?>

<?php if (isset($_SESSION['acabou_de_logar']) && $_SESSION['acabou_de_logar'] === true): ?>
    <div id="toast-welcome" style="position: fixed; top: 20px; right: 20px; z-index: 11000; background: linear-gradient(135deg, #0d6efd, #0099ff); color: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(13, 110, 253, 0.3); animation: slideInToast 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275); display: flex; align-items: center; gap: 15px; min-width: 320px; border: 1px solid rgba(255,255,255,0.2);">
        <div style="font-size: 2rem; width: 50px; height: 50px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-smile-wink"></i>
        </div>
        <div>
            <h5 style="margin: 0; font-weight: 700; font-size: 1.1rem;">Bem-vindo(a), <?= htmlspecialchars(explode(' ', $nome_usuario)[0]) ?>!</h5>
            <p style="margin: 2px 0 0 0; font-size: 0.9rem; opacity: 0.9;">Estamos felizes em vê-lo novamente.</p>
        </div>
        <button onclick="fecharToast()" style="background:none; border:none; color:white; margin-left:auto; cursor:pointer; font-size:1.2rem; opacity:0.8;">&times;</button>
    </div>
    <script>
        function fecharToast() {
            const t = document.getElementById('toast-welcome');
            if(t) {
                t.style.transition = 'all 0.5s ease';
                t.style.opacity = '0';
                t.style.transform = 'translateX(100%)';
                setTimeout(() => t.remove(), 500);
            }
        }
        setTimeout(fecharToast, 5000);
    </script>
    <style>
        @keyframes slideInToast {
            0% { opacity: 0; transform: translateX(100px); }
            100% { opacity: 1; transform: translateX(0); }
        }
    </style>
<?php endif; ?>

<?php include('../includes/mensagem_home_display.php'); ?>

<div style="height: 50px;"></div>

<?php include('../includes/footer.php'); ?>