<?php
// pages/admin/cupom_desconto.php
require_once '../../includes/session_init.php';
include('../../database.php');

// Proteção: Apenas super admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$master_conn = getMasterConnection();
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf();

// ==========================================================
// 1. CRIAR CUPOM (COM TRY-CATCH PARA ERRO DE DUPLICIDADE)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $codigo = strtoupper(trim($_POST['codigo']));
    $tipo = $_POST['tipo'];
    $modo_uso = $_POST['modo_uso']; // passivo ou interno
    $valor = floatval(str_replace(',', '.', $_POST['valor']));
    $limite_usos = trim($_POST['limite_usos'] ?? '') === '' ? null : max(1, intval($_POST['limite_usos']));
    $uso_unico_tenant = isset($_POST['uso_unico_tenant']) ? 1 : 0;
    $aplicar_extras = 0;
    $tempo_indeterminado = isset($_POST['tempo_indeterminado']) ? 1 : 0;
    $data_inicio = !$tempo_indeterminado && !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_fim = !$tempo_indeterminado && !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    $data_exp = $data_fim;
    $desc = trim($_POST['descricao']);

    if (!in_array($modo_uso, ['passivo', 'interno'], true) || !in_array($tipo, ['porcentagem', 'valor_fixo'], true)) {
        $msg = "<div class='alert error'>Tipo ou finalidade do cupom inválidos.</div>";
    } elseif ($valor <= 0 || ($tipo === 'porcentagem' && $valor > 100)) {
        $msg = "<div class='alert error'>Informe um desconto válido. A porcentagem deve ficar entre 0,01% e 100%.</div>";
    }

    if ($msg === '') {
    $stmt = $master_conn->prepare("INSERT INTO cupons_desconto
        (codigo, tipo_desconto, modo_uso, valor, limite_usos, uso_unico_tenant, aplicar_extras,
         data_expiracao, data_inicio, data_fim, tempo_indeterminado, descricao)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // s = string, d = double (float)
    // Ordem: codigo(s), tipo(s), modo(s), valor(d), data(s), desc(s)
    $stmt->bind_param("sssdiiisssis", $codigo, $tipo, $modo_uso, $valor, $limite_usos, $uso_unico_tenant, $aplicar_extras, $data_exp, $data_inicio, $data_fim, $tempo_indeterminado, $desc);

    try {
        $stmt->execute();
        $msg = "<div class='alert success'>Cupom criado com sucesso!</div>";
    } catch (mysqli_sql_exception $e) {
        // Código 1062 é entrada duplicada (Duplicate entry)
        if ($e->getCode() == 1062) {
            $msg = "<div class='alert error'>Erro: O código <strong>'$codigo'</strong> já existe. Tente outro.</div>";
        } else {
            $msg = "<div class='alert error'>Erro ao criar cupom: " . $e->getMessage() . "</div>";
        }
    }
    
    $stmt->close();
    }
}

// ==========================================================
// 2. ENVIAR PROMOÇÃO (Lógica do Modal - Apenas cupons internos)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'enviar_promocao') {
    $cupom_id = intval($_POST['cupom_id']);
    $tenants_selecionados = $_POST['tenants'] ?? [];
    
    $data_inicio_promo = $_POST['data_inicio_promo'];
    $data_fim_promo    = $_POST['data_fim_promo'];
    $promo_indeterminada = isset($_POST['promo_indeterminada']) ? 1 : 0;
    if ($promo_indeterminada) $data_fim_promo = null;

    if (empty($data_inicio_promo) || (!$promo_indeterminada && empty($data_fim_promo))) {
        $msg = "<div class='alert error'>Você precisa definir as datas de início e fim.</div>";
    } elseif (!$promo_indeterminada && strtotime($data_fim_promo) < strtotime($data_inicio_promo)) {
        $msg = "<div class='alert error'>A Data Fim não pode ser menor que a Data Início.</div>";
    } else {
        $count = 0;
        if (!empty($tenants_selecionados)) {
            $stmtProm = $master_conn->prepare("INSERT INTO tenant_promocoes (tenant_id, cupom_id, data_inicio, data_fim, tempo_indeterminado, visualizado, ativo) VALUES (?, ?, ?, ?, ?, 0, 1)");
            
            foreach ($tenants_selecionados as $t_id) {
                try {
                    $stmtProm->bind_param("iissi", $t_id, $cupom_id, $data_inicio_promo, $data_fim_promo, $promo_indeterminada);
                    $stmtProm->execute();
                    $count++;
                } catch (Exception $e) {
                    // Ignora erros individuais (ex: se já foi enviado) e continua o loop
                    continue; 
                }
            }
            $msg = "<div class='alert success'>Promoção enviada para $count clientes!<br>Válida de " . date('d/m/Y', strtotime($data_inicio_promo)) . " até " . date('d/m/Y', strtotime($data_fim_promo)) . ".</div>";
            $stmtProm->close();
        } else {
            $msg = "<div class='alert error'>Nenhum cliente selecionado.</div>";
        }
    }
}

// ==========================================================
// 3. EXCLUIR
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = intval($_POST['id'] ?? 0);
    try {
        $master_conn->query("DELETE FROM cupons_desconto WHERE id = $id");
        header("Location: cupom_desconto.php");
        exit;
    } catch (Exception $e) {
        $msg = "<div class='alert error'>Erro ao excluir: " . $e->getMessage() . "</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'desativar_cliente') {
    $tenantDesativar = (int)($_POST['tenant_id'] ?? 0);
    if ($tenantDesativar > 0) {
        $stmt = $master_conn->prepare('UPDATE tenant_promocoes SET ativo=0 WHERE tenant_id=?');
        $stmt->bind_param('i', $tenantDesativar); $stmt->execute(); $stmt->close();
        $stmt = $master_conn->prepare('UPDATE tenants SET cupom_ativo_id=NULL, promocao_ativa_id=NULL WHERE id=?');
        $stmt->bind_param('i', $tenantDesativar); $stmt->execute(); $stmt->close();
        $msg = "<div class='alert success'>Desconto desativado. A cobrança normal será restaurada pela reconciliação automática.</div>";
    }
}

// ==========================================================
// 4. CONSULTAS SEPARADAS
// ==========================================================
// Cupons de Uso Passivo (Cliente digita)
$cupons_passivos = $master_conn->query("SELECT c.*, (SELECT COUNT(*) FROM cupom_utilizacoes u WHERE u.cupom_id=c.id AND u.status='aplicado') AS usos_realizados FROM cupons_desconto c WHERE c.modo_uso = 'passivo' ORDER BY c.criado_em DESC");

// Cupons de Uso Interno (Admin aplica via modal)
$cupons_internos = $master_conn->query("SELECT * FROM cupons_desconto WHERE modo_uso = 'interno' ORDER BY criado_em DESC");

$clientes_res = $master_conn->query("SELECT id, nome_empresa, admin_email, status_assinatura FROM tenants WHERE status_assinatura IN ('ativo', 'trial') ORDER BY nome_empresa ASC");
$clientes = [];
while($row = $clientes_res->fetch_assoc()) { $clientes[] = $row; }
$descontos_ativos = $master_conn->query("SELECT t.id, t.nome_empresa, t.admin_email,
    COALESCE(cp.codigo, ci.codigo) codigo
    FROM tenants t
    LEFT JOIN cupons_desconto cp ON cp.id=t.cupom_ativo_id
    LEFT JOIN tenant_promocoes tp ON tp.id=t.promocao_ativa_id AND tp.ativo=1
    LEFT JOIN cupons_desconto ci ON ci.id=tp.cupom_id
    WHERE t.cupom_ativo_id IS NOT NULL OR t.promocao_ativa_id IS NOT NULL
    ORDER BY t.nome_empresa");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<link rel="stylesheet" href="../../assets/css/universal-theme.css?v=20260813-admin">
<script src="../../assets/js/global-theme.js?v=20260813-admin"></script>
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <meta charset="UTF-8">
    <title>Gerenciar Cupons</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #0e0e0e; color: #eee; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #121212; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.5); }
        h1, h2 { color: #00bfff; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #aaa; }
        input, select, textarea { width: 100%; padding: 10px; background: #1c1c1c; border: 1px solid #333; color: #fff; border-radius: 4px; box-sizing: border-box; }
        input:focus { border-color: #00bfff; outline: none; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; color: #fff; font-weight: bold; }
        .btn-save { background: #28a745; width: 100%; margin-top: 10px; }
        .btn-save:hover { background: #218838; }
        .btn-back { background: #555; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .btn-promo { background: #e67e22; padding: 5px 10px; font-size: 0.8rem; text-decoration: none; border-radius: 4px; color: white; border: none; cursor: pointer; margin-right: 5px; }
        .btn-promo:hover { background: #d35400; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #1a1a1a; border-radius: 8px; overflow: hidden; margin-bottom: 30px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #252525; color: #00bfff; text-transform: uppercase; font-size: 0.85rem; }
        tr:hover { background: #222; }
        
        .section-title { color: #fff; border-left: 4px solid #00bfff; padding-left: 10px; margin-bottom: 15px; font-size: 1.2rem; }

        .alert { padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; }
        .success { background: rgba(40, 167, 69, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .error { background: rgba(220, 53, 69, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); }
        .modal-content { background-color: #1a1a1a; margin: 5% auto; padding: 25px; border: 1px solid #333; width: 50%; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.7); }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #fff; }
        .client-list { max-height: 250px; overflow-y: auto; margin-top: 15px; border: 1px solid #333; padding: 10px; background: #151515; }
        .client-item { display: flex; align-items: center; padding: 8px; border-bottom: 1px solid #222; }
        .client-item:hover { background: #222; }
        .client-item input { width: auto; margin-right: 15px; transform: scale(1.2); }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
        
        <h1>Gerenciar Cupons de Desconto</h1>
        <?= $msg ?>

        <form method="POST" style="background: #1a1a1a; padding: 20px; border-radius: 8px; border: 1px solid #333; margin-bottom: 40px;">
            <input type="hidden" name="acao" value="criar">
            <h3 style="margin-top:0; color:#ccc; font-size:1.1rem; border-bottom:1px solid #333; padding-bottom:10px;">Criar Novo Cupom</h3>
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <div style="flex: 1;">
                    <label>Código do Cupom</label>
                    <input type="text" name="codigo" placeholder="EX: PROMOWEB" required style="text-transform: uppercase;">
                </div>
                
                <div style="flex: 1;">
                    <label>Finalidade</label>
                    <select name="modo_uso">
                        <option value="passivo">Uso Passivo (Cliente digita)</option>
                        <option value="interno">Uso Interno (Enviar via Modal)</option>
                    </select>
                </div>

                <div style="flex: 1;">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="porcentagem">Porcentagem (%)</option>
                        <option value="valor_fixo">Valor Fixo (R$)</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Valor</label>
                    <input type="number" name="valor" step="0.01" placeholder="10.00" required>
                </div>
                <div style="flex: 1;"><label>Início da validade</label><input type="date" name="data_inicio"></div>
                <div style="flex: 1;"><label>Término da validade</label><input type="date" name="data_fim"></div>
            </div>
            <div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:15px; align-items:end;">
                <div style="flex:1; min-width:220px;">
                    <label>Limite total de utilizações (opcional)</label>
                    <input type="number" name="limite_usos" min="1" placeholder="Sem limite">
                </div>
                <label style="flex:1; min-width:220px;"><input type="checkbox" name="uso_unico_tenant" value="1" checked style="width:auto;"> Uma utilização por cliente</label>
                <label style="flex:1; min-width:220px;"><input type="checkbox" name="tempo_indeterminado" value="1" style="width:auto;"> <i class="fas fa-infinity"></i> Ativo por tempo indeterminado</label>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label>Descrição</label>
                <textarea name="descricao" rows="2" placeholder="Descrição interna do cupom..."></textarea>
            </div>
            <button type="submit" class="btn btn-save"><i class="fas fa-plus"></i> Criar Cupom</button>
        </form>

        <div class="section-title">Cupons Especiais / Internos</div>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Desconto</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cupons_internos && $cupons_internos->num_rows > 0): ?>
                    <?php while($c = $cupons_internos->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: bold; color: #fff;">
                            <?= htmlspecialchars($c['codigo']) ?>
                            <div style="font-size:0.8rem; color:#aaa; font-weight:normal;"><?= htmlspecialchars($c['descricao']) ?></div>
                        </td>
                        <td>
                            <?= ($c['tipo_desconto'] == 'porcentagem') ? intval($c['valor']) . '%' : 'R$ ' . number_format($c['valor'], 2, ',', '.') ?>
                        </td>
                        <td>
                            <button type="button" class="btn-promo" onclick="abrirModalPromo(<?= $c['id'] ?>, '<?= $c['codigo'] ?>', '<?= ($c['tipo_desconto']=='porcentagem' ? intval($c['valor']).'%' : 'R$'.$c['valor']) ?>')" title="Enviar Promoção">
                                <i class="fas fa-paper-plane"></i> Enviar para Clientes
                            </button>
                            <form method="post" style="display:inline" onsubmit="return confirm('Excluir este cupom?')">
                                <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" style="color:#e74c3c;margin-left:10px;background:none;border:0" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center; color:#666;">Nenhum cupom interno cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">Descontos ativos por cliente</div>
        <table>
            <thead><tr><th>Cliente</th><th>Cupom</th><th>Ação</th></tr></thead>
            <tbody>
            <?php if ($descontos_ativos && $descontos_ativos->num_rows): while ($d=$descontos_ativos->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($d['nome_empresa']) ?><div style="font-size:.8rem;color:#aaa"><?= htmlspecialchars($d['admin_email']) ?></div></td>
                    <td><?= htmlspecialchars($d['codigo'] ?? '-') ?></td>
                    <td><form method="post" onsubmit="return confirm('Desativar desconto e retornar à mensalidade normal?')">
                        <input type="hidden" name="acao" value="desativar_cliente">
                        <input type="hidden" name="tenant_id" value="<?= (int)$d['id'] ?>">
                        <button class="btn btn-save" type="submit"><i class="fas fa-ban"></i> Desativar</button>
                    </form></td>
                </tr>
            <?php endwhile; else: ?><tr><td colspan="3">Nenhum desconto ativo.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <div class="section-title">Cupons de Uso Geral (Passivo)</div>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Desconto</th>
                    <th>Validade</th>
                    <th>Usos</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cupons_passivos && $cupons_passivos->num_rows > 0): ?>
                    <?php while($c = $cupons_passivos->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: bold; color: #fff;">
                            <?= htmlspecialchars($c['codigo']) ?>
                            <div style="font-size:0.8rem; color:#aaa; font-weight:normal;"><?= htmlspecialchars($c['descricao']) ?></div>
                        </td>
                        <td>
                            <?= ($c['tipo_desconto'] == 'porcentagem') ? intval($c['valor']) . '%' : 'R$ ' . number_format($c['valor'], 2, ',', '.') ?>
                        </td>
                        <td>
                            <?= $c['tempo_indeterminado'] ? '<i class="fas fa-infinity"></i> Indeterminado' : (($c['data_inicio'] ? date('d/m/Y', strtotime($c['data_inicio'])) : 'Imediato') . ' até ' . ($c['data_fim'] ? date('d/m/Y', strtotime($c['data_fim'])) : 'sem término')) ?>
                        </td>
                        <td>
                            <?= intval($c['usos_realizados']) ?> / <?= $c['limite_usos'] === null ? '&infin;' : intval($c['limite_usos']) ?>
                            <div style="font-size:.75rem;color:#aaa"><?= $c['uso_unico_tenant'] ? '1 por cliente' : 'reutilizÃ¡vel' ?> · somente plano</div>
                        </td>
                        <td>
                            <form method="post" style="display:inline" onsubmit="return confirm('Excluir este cupom?')">
                                <input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button type="submit" style="color:#e74c3c;background:none;border:0" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#666;">Nenhum cupom passivo cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <div id="modalPromo" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalPromo()">&times;</span>
            <h2 style="margin-bottom: 5px;">Aplicar Promoção Automática</h2>
            <p style="text-align:center; color:#aaa; margin-top:0;">
                Cupom: <strong id="modalCupomCodigo" style="color:#00bfff;"></strong> 
                (<span id="modalCupomValor"></span> de desconto)
            </p>
            
            <form method="POST">
                <input type="hidden" name="acao" value="enviar_promocao">
                <input type="hidden" name="cupom_id" id="inputCupomId">

                <div style="background: #252525; padding: 15px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #444;">
                    <h4 style="margin:0 0 10px 0; color:#e67e22;"><i class="fas fa-calendar-alt"></i> Período de Aplicação nas Faturas</h4>
                    <div style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label>Aplicar a partir de:</label>
                            <input type="date" name="data_inicio_promo" required>
                        </div>
                        <div style="flex:1;">
                            <label>Até a data:</label>
                            <input type="date" name="data_fim_promo">
                        </div>
                    </div>
                    <label style="display:block;margin-top:10px;"><input type="checkbox" name="promo_indeterminada" value="1" style="width:auto;"> <i class="fas fa-infinity"></i> Manter ativo até desativação manual pelo Master</label>
                    <small style="color:#999; display:block; margin-top:5px;">
                        * Todas as faturas geradas para os clientes selecionados dentro deste período receberão o desconto automaticamente.
                    </small>
                </div>

                <label>Selecione os Clientes:</label>
                <div class="client-list">
                    <div class="client-item" style="background: #333; position: sticky; top: 0; z-index: 2;">
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                        <label for="selectAll" style="margin:0; cursor:pointer; color: #fff;"><strong>Selecionar Todos</strong></label>
                    </div>
                    <?php if (count($clientes) > 0): ?>
                        <?php foreach($clientes as $cli): ?>
                        <div class="client-item">
                            <input type="checkbox" name="tenants[]" value="<?= $cli['id'] ?>" class="chk-cliente">
                            <div style="display:flex; flex-direction:column;">
                                <span style="color:white; font-weight:bold;"><?= htmlspecialchars($cli['nome_empresa']) ?></span>
                                <span style="font-size:0.8rem; color:#aaa;"><?= htmlspecialchars($cli['admin_email']) ?> · <?= $cli['status_assinatura'] === 'trial' ? 'PerÃ­odo de teste' : 'Ativo' ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="padding:10px; text-align:center;">Nenhum cliente ativo encontrado.</p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-save" style="margin-top:20px; background: #e67e22;">
                    <i class="fas fa-check-circle"></i> Confirmar e Aplicar
                </button>
            </form>
        </div>
    </div>

    <script>
        function abrirModalPromo(id, codigo, valor) {
            document.getElementById('inputCupomId').value = id;
            document.getElementById('modalCupomCodigo').innerText = codigo;
            document.getElementById('modalCupomValor').innerText = valor;
            const hoje = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="data_inicio_promo"]').value = hoje;
            document.getElementById('modalPromo').style.display = "block";
        }
        function fecharModalPromo() {
            document.getElementById('modalPromo').style.display = "none";
        }
        function toggleAll(source) {
            checkboxes = document.getElementsByClassName('chk-cliente');
            for(var i=0, n=checkboxes.length;i<n;i++) {
                checkboxes[i].checked = source.checked;
            }
        }
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalPromo')) {
                fecharModalPromo();
            }
        }
    </script>
</body>
</html>
