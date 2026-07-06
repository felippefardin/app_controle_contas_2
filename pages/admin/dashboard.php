<?php
require_once '../../includes/session_init.php';
include('../../database.php');

// Proteção: Apenas super admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$admin = $_SESSION['super_admin'];
$master_conn = getMasterConnection();

// --- LÓGICA DE PESQUISA DE TENANTS ---
$busca = trim($_GET['busca'] ?? '');
$sql = "SELECT t.id, t.usuario_id, t.nome, t.nome_empresa, t.status_assinatura, t.data_criacao, 
               u.documento, u.email 
        FROM tenants t 
        LEFT JOIN usuarios u ON t.usuario_id = u.id";

if (!empty($busca)) {
    $term = $master_conn->real_escape_string($busca);
    $sql .= " WHERE t.nome LIKE '%$term%' OR t.nome_empresa LIKE '%$term%' OR u.documento LIKE '%$term%' OR u.email LIKE '%$term%'";
}
$sql .= " ORDER BY t.id DESC";
$tenants_result = $master_conn->query($sql);

// --- LÓGICA DE CHAMADOS (FILA) ---
$sql_chamados = "
    SELECT c.*, t.nome_empresa, t.nome as nome_proprietario
    FROM chamados_suporte c
    JOIN tenants t ON c.tenant_id COLLATE utf8mb4_unicode_ci = t.tenant_id COLLATE utf8mb4_unicode_ci
    WHERE c.status != 'concluido'
    ORDER BY FIELD(c.status, 'aberto', 'em_atendimento') ASC, c.criado_em DESC";
$result_chamados = $master_conn->query($sql_chamados);

// --- LÓGICA DE RANKING ---
$sql_ranking = "
    SELECT 
        u_ind.nome AS nome_indicador,
        u_ind.email AS email_indicador,
        t.nome_empresa,
        COUNT(i.id) AS total_indicacoes,
        FLOOR(COUNT(i.id) / 3) AS premios_ganhos,
        (3 - (COUNT(i.id) % 3)) AS faltam_para_proximo
    FROM indicacoes i
    JOIN usuarios u_ind ON i.id_indicador = u_ind.id
    LEFT JOIN tenants t ON u_ind.tenant_id COLLATE utf8mb4_unicode_ci = t.tenant_id COLLATE utf8mb4_unicode_ci
    GROUP BY i.id_indicador
    ORDER BY total_indicacoes DESC
    LIMIT 10
";
$res_ranking = $master_conn->query($sql_ranking);

// --- LÓGICA SUPORTE INICIAL (ONBOARDING) ---
$sql_suporte_inicial = "
    SELECT s.*, t.nome_empresa 
    FROM solicitacoes_suporte_inicial s
    JOIN tenants t ON s.tenant_id = t.id
    WHERE s.status = 'pendente'
    ORDER BY s.criado_em DESC
";
$res_suporte_inicial = $master_conn->query($sql_suporte_inicial);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Master</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* --- GLOBAL --- */
        body { background-color: #0e0e0e; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding-bottom: 40px; overflow-x: hidden; }
        /* --- TOPBAR --- */
        .topbar { width: 100%; background: #1a1a1a; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.4); position: sticky; top: 0; z-index: 100; box-sizing: border-box; }
        .topbar-title { font-size: 1.2rem; color: #00bfff; font-weight: bold; }
        .topbar-actions { display: flex; gap: 15px; align-items: center; }
        .topbar a { color: #eee; text-decoration: none; padding: 8px 14px; border-radius: 4px; background-color: #333; transition: 0.2s; font-size: 14px; }
        .topbar a:hover { background-color: #444; }
        .topbar .logout { background-color: #d13c3c; }
        .topbar .logout:hover { background-color: #ff4a4a; }
        /* --- CONTAINER --- */
        .container { width: 98%; max-width: 100%; margin: 20px auto; background: #121212; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.2); box-sizing: border-box; }
        h1, h2 { margin-bottom: 20px; text-align: center; }
        h1 { color: #00bfff; }
        h2 { color: #ff9f43; border-top: 1px solid #333; padding-top: 30px; margin-top: 40px; }
        /* --- SEARCH --- */
        .search-container { display: flex; gap: 10px; margin-bottom: 25px; justify-content: center; flex-wrap: wrap; }
        .search-input { padding: 12px; width: 350px; max-width: 100%; border-radius: 5px; border: 1px solid #333; background-color: #1c1c1c; color: #fff; font-size: 15px; }
        .btn-search { padding: 12px 20px; background-color: #28a745; border: none; color: white; border-radius: 5px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .btn-search:hover { background-color: #218838; }
        .btn-clear { color: #aaa; text-decoration: underline; font-size: 14px; align-self: center; }
        /* --- TABLE --- */
        table { width: 100%; min-width: 100%; border-collapse: collapse; border-radius: 8px; overflow: hidden; margin-bottom: 20px; background: #1a1a1a; table-layout: auto; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #2a2a2a; }
        th { background-color: #252525; color: #00bfff; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; white-space: nowrap; }
        td { color: #ddd; vertical-align: middle; }
        tr:hover { background-color: #2a2a2a; }
        /* --- BUTTONS --- */
        .btn-gerenciar { background-color: #00bfff; color: #fff; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; transition: 0.2s; white-space: nowrap; margin-right: 5px; }
        .btn-gerenciar:hover { background-color: #009acd; }
        .btn-chat { background-color: #e91e63; color: #fff; padding: 6px 12px; border: none; border-radius: 4px; text-decoration: none; font-size: 13px; cursor: pointer; transition: 0.2s; white-space: nowrap; display: inline-flex; align-items: center; gap: 5px; }
        .btn-chat:hover { background-color: #c2185b; }
        .btn-action { border: none; border-radius: 4px; padding: 8px; cursor: pointer; font-size: 14px; color: white; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; margin-right: 5px; transition: 0.2s; }
        .btn-atender { background-color: #e67e22; } .btn-atender:hover { background-color: #d35400; }
        .btn-resolver { background-color: #27ae60; } .btn-resolver:hover { background-color: #219150; }
        .btn-excluir { background-color: #c0392b; } .btn-excluir:hover { background-color: #a93226; }
        .status-ball { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
        .status-aberto { background-color: #ff4d4d; box-shadow: 0 0 8px #ff4d4d; }
        .status-em-atendimento { background-color: #f1c40f; box-shadow: 0 0 8px #f1c40f; }
        .status-concluido { background-color: #2ecc71; box-shadow: 0 0 8px #2ecc71; }
        /* --- MODAL --- */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); backdrop-filter: blur(3px); }
        .modal-content { background-color: #1e1e1e; margin: 3% auto; padding: 25px; border: 1px solid #444; width: 70%; max-width: 900px; border-radius: 12px; color: #eee; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #fff; }
        textarea { width: 100%; background: #2c2c2c; border: 1px solid #444; color: white; padding: 12px; border-radius: 6px; resize: vertical; box-sizing: border-box; font-family: inherit; }
        textarea:focus { outline: none; border-color: #00bfff; }
        .history-container { margin-bottom: 25px; border-left: 2px solid #333; padding-left: 20px; margin-left: 10px; }
        .history-item { margin-bottom: 20px; position: relative; }
        .history-item::before { content: ''; position: absolute; left: -25px; top: 6px; width: 10px; height: 10px; background: #555; border-radius: 50%; border: 2px solid #1e1e1e; }
        .history-meta { font-size: 0.85rem; color: #888; margin-bottom: 5px; font-weight: 600; }
        .history-msg { background: #252525; padding: 12px 15px; border-radius: 6px; font-size: 0.95rem; color: #ddd; line-height: 1.5; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .admin-msg { border-left: 4px solid #e67e22; }
        .admin-msg::before { background-color: #e67e22; }
        @media (min-width: 768px) { .topbar span { display: inline; } }
        @media (max-width: 900px) {
            .container { width: 100%; margin: 0; padding: 15px; border-radius: 0; }
            .search-input { width: 100%; }
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr { background: #202020; margin-bottom: 15px; border-radius: 8px; padding: 15px; border: 1px solid #333; }
            td { border: none; padding: 8px 0; display: flex; justify-content: space-between; text-align: right; font-size: 0.95rem; }
            td::before { content: attr(data-label); font-weight: bold; color: #00bfff; text-align: left; flex-basis: 40%; padding-right: 10px; }
            td:last-child { margin-top: 10px; padding-top: 10px; border-top: 1px solid #333; justify-content: flex-end; gap: 10px; }
            .modal-content { width: 95%; margin: 10% auto; padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="topbar-title">App Control <span style="color:#fff; font-weight:300; display:inline;">Master</span></div>
        <div class="topbar-actions">
    <span><?= htmlspecialchars($admin['email'] ?? 'Admin') ?></span>
    <a href="novo_admin.php" title="Adicionar Novo Super Admin" style="background-color: #28a745;">
        <i class="fas fa-user-plus"></i> <span style="display:none;">Add Admin</span>
    </a>
    <a href="redefinir_senha.php" title="Alterar Minha Senha">
        <i class="fas fa-key"></i> <span style="display:none;">Senha</span>
    </a>
    <a href="../logout.php" class="logout" title="Sair">
        <i class="fas fa-sign-out-alt"></i> <span style="display:none;">Sair</span>
    </a>
</div>
    </div>

    <div class="container">
        
        <h1>Painel de Controle</h1>
        <form method="GET" class="search-container">
            <input type="text" name="busca" class="search-input" placeholder="Buscar Cliente (Nome, Email, CPF/CNPJ)..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
            <?php if (!empty($busca)): ?>
                <a href="dashboard.php" class="btn-clear">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Cliente / Email</th><th>CPF/CNPJ</th><th>Status</th><th>Cadastro</th><th>Acesso</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($tenants_result && $tenants_result->num_rows > 0): ?>
                <?php while ($tenant = $tenants_result->fetch_assoc()): ?>
                    <tr>
                        <td data-label="ID">#<?= $tenant['id']; ?></td>
                        <td data-label="Cliente">
                            <div style="font-weight:bold; color:#fff;">
                                <?= htmlspecialchars($tenant['nome_empresa'] ?: $tenant['nome'] ?: 'Sem Nome'); ?>
                            </div>
                            <div style="font-size:0.85rem; color:#aaa;">
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($tenant['email'] ?? 'Sem Email'); ?>
                            </div>
                        </td>
                        <td data-label="Documento"><?= htmlspecialchars($tenant['documento'] ?? '-'); ?></td>
                        <td data-label="Status"><?= htmlspecialchars($tenant['status_assinatura'] ?? '-'); ?></td>
                        <td data-label="Cadastro"><?= !empty($tenant['data_criacao']) ? date('d/m/y', strtotime($tenant['data_criacao'])) : '-'; ?></td>
                        <td data-label="Ação">
                            <a class="btn-gerenciar" href="../../actions/admin_impersonate.php?tenant_id=<?= $tenant['id']; ?>"><i class="fas fa-external-link-alt"></i> Acessar</a>
                            <?php if(!empty($tenant['usuario_id'])): ?>
                                <button class="btn-chat" onclick="iniciarSuporte(<?= $tenant['usuario_id']; ?>, '<?= htmlspecialchars($tenant['email'] ?? '') ?>')" title="Iniciar Chat Online com <?= htmlspecialchars($tenant['email']) ?>">
                                    <i class="fas fa-comments"></i> Chat
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px; color: #777;">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 50px; margin-bottom: 20px; border-top: 1px solid #333; padding-top: 30px; flex-wrap: wrap; gap: 10px;">
            <h2 style="margin: 0; border: none; padding: 0; color: #ff9f43;"><i class="fas fa-headset"></i> Fila de Suporte</h2>
            <div>
                <a href="usuarios_sistema.php" class="btn-gerenciar" style="background: #007bff;"><i class="fas fa-users"></i> Todos Usuários</a>
                <a href="cupom_desconto.php" class="btn-gerenciar" style="background: #d35400;"><i class="fas fa-ticket-alt"></i> Cupons</a>
                <a href="email_marketing.php" class="btn-gerenciar" style="background: #8e44ad;"><i class="fas fa-bullhorn"></i> Email Marketing</a>
                <a href="chamados_resolvidos.php" class="btn-gerenciar" style="background: #2ecc71;"><i class="fas fa-archive"></i> Ver Arquivo</a>
                <a href="arquivos_suportes.php" class="btn-gerenciar" style="background: #34495e;"><i class="fas fa-file-pdf"></i> Logs de Chat</a>
                <a href="suporte_via_login.php" class="btn-gerenciar" style="background: #356985;"><i class="fa-solid fa-headset"></i></i> Suporte via login</a>
                <a href="feedback.php" class="btn-gerenciar" style="background: #258966;"><i class="fa-solid fa-comment-dots"></i></i></i> Feedback</a>
                <a href="documento_de_registro_no_sistema.php" class="btn-gerenciar" style="background: #3258;"><i class="fa-solid fa-user-shield"></i> LGPD de usuários</a>
                <a href="controle_financeiro_sistema.php" class="btn-gerenciar" style="background: #125896;"><i class="fa-solid fa-coins"></i> Controle Financeiro</a>      
                <a href="mensagens_home_criadas.php" class="btn-gerenciar" style="background: #102589;"><i class="fa-solid fa-envelope-open-text"></i> Mensagens Personaliza</a>
            </div>
        </div>

        <?php if(isset($_SESSION['msg_suporte'])): ?>
            <div style="padding: 15px; background: rgba(40,167,69,0.2); color: #2ecc71; border: 1px solid #2ecc71; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['msg_suporte'] ?>
            </div>
            <?php unset($_SESSION['msg_suporte']); ?>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 12%;">Data/Hora</th>
                    <th style="width: 20%;">Empresa / Cliente</th>
                    <th style="width: 10%;">Tipo</th>
                    <th style="width: 10%;">Custo</th>
                    <th>Descrição</th>
                    <th style="width: 15%;">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result_chamados && $result_chamados->num_rows > 0): ?>
                <?php while ($c = $result_chamados->fetch_assoc()): 
                    $statusClass = 'status-' . ($c['status'] == 'aberto' ? 'aberto' : 'em-atendimento');
                    $statusNome = ($c['status'] == 'aberto') ? 'Fechado' : 'Em Atendimento';
                    
                    $id_chamado = $c['id'];
                    $res_hist = $master_conn->query("SELECT * FROM chamados_historico WHERE chamado_id = $id_chamado ORDER BY criado_em ASC");
                    $historico_html = "";
                    if ($res_hist && $res_hist->num_rows > 0) {
                        while ($h = $res_hist->fetch_assoc()) {
                            $autor = htmlspecialchars($h['autor_nome']);
                            $data = date('d/m/y H:i', strtotime($h['criado_em']));
                            $msg = nl2br(htmlspecialchars($h['mensagem']));
                            $tipoClass = ($h['autor_tipo'] == 'admin') ? 'admin-msg' : '';
                            $itemClass = ($h['autor_tipo'] == 'admin') ? 'admin-msg-item' : '';

                            $historico_html .= "
                            <div class='history-item $itemClass'>
                                <div class='history-meta'>$autor - $data</div>
                                <div class='history-msg $tipoClass'>$msg</div>
                            </div>";
                        }
                    } else {
                        $historico_html = "<div style='text-align:center; color:#666; padding:10px;'>Nenhum histórico de interação.</div>";
                    }
                    $historico_js = addslashes(str_replace(["\r", "\n"], '', $historico_html)); 
                ?>
                <tr>
                    <td data-label="Status">
                        <div style="display: flex; align-items: center; justify-content: flex-end;">
                            <span class="status-ball <?= $statusClass ?>"></span>
                            <span style="font-size: 0.9rem; color: #eee;"><?= $statusNome ?></span>
                        </div>
                    </td>
                    <td data-label="Data/Hora">
                        <?= date('d/m/Y H:i', strtotime($c['criado_em'])) ?>
                    </td>
                    <td data-label="Empresa">
                        <div style="line-height: 1.4;">
                            <strong style="color: #fff; font-size:1rem;"><?= htmlspecialchars($c['nome_empresa'] ?: $c['nome_proprietario']) ?></strong><br>
                            <span style="color: #aaa; font-size: 0.85rem;">Sol: <?= htmlspecialchars($c['usuario_nome']) ?></span><br>
                            <span style="color: #777; font-size: 0.8rem;"><?= htmlspecialchars($c['usuario_email']) ?></span>
                        </div>
                    </td>
                    <td data-label="Tipo">
                        <?= ($c['tipo'] == 'chat_aovivo') 
                            ? '<span style="background:#e74c3c; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:bold;">AO VIVO</span>' 
                            : '<span style="background:#3498db; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:bold;">ONLINE</span>' 
                        ?>
                    </td>
                    <td data-label="Custo">
                        <?php if (floatval($c['custo']) > 0): ?>
                            <span style="background:rgba(255, 193, 7, 0.2); color:#ffc107; border:1px solid #ffc107; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:bold;">
                                R$ <?= number_format($c['custo'], 2, ',', '.') ?>
                            </span>
                        <?php else: ?>
                            <span style="background:rgba(40, 167, 69, 0.2); color:#2ecc71; border:1px solid #2ecc71; padding:3px 8px; border-radius:4px; font-size:12px; font-weight:bold;">
                                GRÁTIS
                            </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Descrição">
                        <div style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: help;" title="<?= htmlspecialchars($c['descricao']) ?>">
                            <?= htmlspecialchars($c['descricao']) ?>
                        </div>
                    </td>
                    <td data-label="Ações">
                        <button class="btn-action btn-atender" onclick="abrirModal(<?= $c['id'] ?>, `<?= htmlspecialchars(addslashes($c['descricao'])) ?>`, `<?= htmlspecialchars($c['usuario_nome']) ?>`, `<?= $historico_js ?>`)" title="Atender">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="../../actions/admin_suporte.php" method="POST" style="display:inline;" onsubmit="return confirm('Marcar este chamado como resolvido?');">
                            <input type="hidden" name="acao" value="resolver">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-action btn-resolver" title="Resolver"><i class="fas fa-check"></i></button>
                        </form>
                        <form action="../../actions/admin_suporte.php" method="POST" style="display:inline;" onsubmit="return confirm('ATENÇÃO: Isso excluirá o chamado e todo o histórico permanentemente. Confirmar?');">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-action btn-excluir" title="Excluir"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding: 30px; color: #777; font-size: 1.1rem;">Nenhum chamado pendente no momento.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <h2 style="color: #2ecc71; margin-top: 40px; border-top: 1px solid #333; padding-top: 30px;">
            <i class="fas fa-gift"></i> Ranking Indique e Ganhe
        </h2>

        <table>
            <thead>
                <tr>
                    <th>Indicador</th>
                    <th>Empresa</th>
                    <th style="text-align: center;">Total Indicações</th>
                    <th style="text-align: center;">Prêmios (Meses Grátis)</th>
                    <th style="text-align: center;">Meta Próximo</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($res_ranking && $res_ranking->num_rows > 0): ?>
                <?php while ($rank = $res_ranking->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Indicador">
                            <div style="font-weight:bold; color:#fff;"><?= htmlspecialchars($rank['nome_indicador']) ?></div>
                            <div style="font-size:0.8rem; color:#aaa;"><?= htmlspecialchars($rank['email_indicador']) ?></div>
                        </td>
                        <td data-label="Empresa"><?= htmlspecialchars($rank['nome_empresa'] ?? '-') ?></td>
                        <td data-label="Total" style="text-align: center;">
                            <span style="background:#3498db; padding:4px 12px; border-radius:15px; font-weight:bold; color:#fff;">
                                <?= $rank['total_indicacoes'] ?>
                            </span>
                        </td>
                        <td data-label="Ganhos" style="text-align: center;">
                            <span style="color: #2ecc71; font-weight:bold; font-size: 1.1rem;">
                                <?= $rank['premios_ganhos'] ?>x <small style="color:#aaa; font-size:0.8rem;">(+<?= $rank['premios_ganhos'] * 30 ?> dias)</small>
                            </span>
                        </td>
                        <td data-label="Meta" style="text-align: center;">
                            <?php if($rank['faltam_para_proximo'] == 3): ?>
                                <span style="color: #f1c40f; font-weight:bold;">Acabou de Ganhar!</span>
                            <?php else: ?>
                                <span style="color: #aaa;">Faltam <strong><?= $rank['faltam_para_proximo'] ?></strong> indicações</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; color: #777; padding: 20px;">Nenhuma indicação registrada ainda.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <h2 style="color: #20c997; margin-top: 40px; border-top: 1px solid #333; padding-top: 30px;">
            <i class="fas fa-clipboard-list"></i> Solicitações de Suporte Inicial (Plano Essencial)
        </h2>

        <table>
            <thead>
                <tr>
                    <th style="width: 120px;">Status</th>
                    <th>Data</th>
                    <th>Empresa</th>
                    <th>Nome Contato</th>
                    <th>Email</th>
                    <th>WhatsApp</th>
                    <th style="width: 100px;">Ação</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($res_suporte_inicial && $res_suporte_inicial->num_rows > 0): ?>
                <?php while ($s = $res_suporte_inicial->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Status">
                            <div style="display: flex; align-items: center;">
                                <span class="status-ball" style="background-color: #e74c3c; box-shadow: 0 0 8px #e74c3c;"></span>
                                <span style="font-size: 0.9rem; color: #e74c3c; font-weight: bold;">Aberto</span>
                            </div>
                        </td>
                        <td data-label="Data"><?= date('d/m H:i', strtotime($s['data_solicitacao'])) ?></td>
                        <td data-label="Empresa"><?= htmlspecialchars($s['nome_empresa']) ?></td>
                        <td data-label="Nome"><?= htmlspecialchars($s['nome_usuario']) ?></td>
                        <td data-label="Email">
                            <a href="mailto:<?= htmlspecialchars($s['email_usuario']) ?>" style="color:#00bfff;">
                                <?= htmlspecialchars($s['email_usuario']) ?>
                            </a>
                        </td>
                        <td data-label="WhatsApp">
                            <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $s['whatsapp_usuario']) ?>" target="_blank" style="color:#2ecc71;">
                                <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($s['whatsapp_usuario']) ?>
                            </a>
                        </td>
                        <td data-label="Ação" style="white-space: nowrap;">
                            <form action="../../actions/suporte_inicial_cor.php" method="POST" onsubmit="return confirm('Marcar como contatado? (Ficará verde)');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-action" style="background-color: #c0392b;" title="Marcar como Feito">
                                    <i class="fas fa-check-double"></i>
                                </button>
                            </form>
                            
                            <form action="../../actions/excluir_suporte_inicial_dashboard.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta solicitação?');" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn-action btn-excluir" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding: 20px; color: #777;">Nenhuma solicitação de onboarding pendente.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    </div>

    <div id="modalAtendimento" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2 style="color: #00bfff; text-align: left; border: none; margin-top: 0; padding-top: 0;"><i class="fas fa-headset"></i> Atendimento #<span id="modalIdDisplay"></span></h2>
            <div style="background: #252525; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #3498db;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong style="color: #fff;">Solicitante: <span id="modalSolicitante"></span></strong>
                    <span style="color: #888; font-size: 0.9rem;">Descrição Original</span>
                </div>
                <p id="modalDescricao" style="white-space: pre-wrap; margin: 0; color: #ddd;"></p>
            </div>
            <div style="margin-bottom: 20px;">
                <h4 style="color: #ccc; border-bottom: 1px solid #444; padding-bottom: 8px; margin-bottom: 15px;">Histórico de Interações</h4>
                <div id="modalHistoryContainer" class="history-container"></div>
            </div>
            <form action="../../actions/admin_suporte.php" method="POST">
                <input type="hidden" name="acao" value="salvar_notas">
                <input type="hidden" name="id" id="modalIdInput">
                <label for="nova_mensagem" style="display:block; margin-bottom: 8px; color: #00bfff; font-weight: bold;">Adicionar Nova Nota / Resposta:</label>
                <textarea name="nova_mensagem" id="novaMensagem" rows="4" placeholder="Digite os detalhes do atendimento aqui..." required></textarea>
                <div style="text-align: right; margin-top: 15px;">
                    <button type="button" onclick="fecharModal()" style="padding: 10px 20px; background: transparent; border: 1px solid #666; color: #ccc; border-radius: 5px; cursor: pointer; margin-right: 10px;">Cancelar</button>
                    <button type="submit" class="btn-search" style="background: #00bfff; width: auto;">Salvar e Atualizar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal(id, descricao, solicitante, historicoHtml) {
            document.getElementById('modalIdDisplay').innerText = id;
            document.getElementById('modalIdInput').value = id;
            document.getElementById('modalDescricao').innerText = descricao;
            document.getElementById('modalSolicitante').innerText = solicitante;
            document.getElementById('modalHistoryContainer').innerHTML = historicoHtml;
            document.getElementById('novaMensagem').value = ''; 
            document.getElementById('modalAtendimento').style.display = "block";
            document.body.style.overflow = 'hidden';
        }

        function fecharModal() {
            document.getElementById('modalAtendimento').style.display = "none";
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('modalAtendimento')) {
                fecharModal();
            }
        }

        function iniciarSuporte(userId, userEmail) {
            if(!confirm("Deseja iniciar um Chat Online com o email: " + userEmail + "? Ele receberá um convite na Home.")) return;

            const formData = new FormData();
            formData.append('action', 'iniciar_suporte');
            formData.append('target_user_id', userId);
            
            fetch('../../actions/chat_api.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    alert('Solicitação enviada! Você será redirecionado para a sala de espera.');
                    window.location.href = '../../pages/chat_suporte_online.php?chat_id=' + data.chat_id;
                } else {
                    alert('Erro: ' + (data.msg || 'Verifique se já existe um chat pendente.'));
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de conexão.');
            });
        }
    </script>
</body>
</html>