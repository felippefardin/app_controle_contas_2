<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/caixa_service.php';

if (empty($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

$conn = getTenantConnection();
$ownerId = get_data_owner_id();
$hoje = date('Y-m-d');
$resumo = caixa_resumo($conn, $ownerId, $hoje);
$caixaAberto = ($resumo['sessao']['status'] ?? '') === 'aberto';

$stmt = $conn->prepare('SELECT id,data,valor,tipo,descricao,forma_pagamento,criado_em
    FROM caixa_diario WHERE usuario_id=? ORDER BY data DESC, id DESC');
$stmt->bind_param('i', $ownerId);
$stmt->execute();
$lancamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$rotulos = [
    'abertura' => 'Abertura do caixa',
    'venda' => 'Venda no PDV',
    'manual_entrada' => 'Entrada manual',
    'manual_saida' => 'Retirada manual',
];

include '../includes/header.php';
display_flash_message();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movimentações do Caixa</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body{background:#121212;color:#eee}.caixa-container{width:95%;max-width:1250px;margin:28px auto;background:#1e1e1e;padding:25px;border-radius:10px}
        h1,h3{color:#00bfff}.summary-card{height:100%;padding:17px;border-radius:8px;background:#282828;border-left:5px solid #00bfff}
        .summary-card strong{display:block;font-size:1.35rem}.form-control{background:#2c2c2c!important;color:#fff!important;border-color:#555!important}
        .table{color:#eee}.table thead th{background:#087da8;border-color:#087da8}.table td{border-color:#444}
        .entrada{color:#6ee7a0;font-weight:700}.saida{color:#ff8181;font-weight:700}.badge-status{font-size:.95rem}
    </style>
</head>
<body>
<main class="caixa-container">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-money-bill-transfer"></i> Movimentações do Caixa</h1>
        <div>
            <a href="vendas.php" class="btn btn-info">Ir para o PDV</a>
            <a href="fechamento_caixa.php" class="btn btn-warning">Fechamento</a>
        </div>
    </div>

    <div class="alert <?= $caixaAberto ? 'alert-success' : 'alert-secondary' ?>">
        Caixa de hoje:
        <strong><?= $caixaAberto ? 'ABERTO' : (($resumo['sessao']['status'] ?? '') === 'fechado' ? 'FECHADO' : 'AINDA NÃO ABERTO') ?></strong>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-2"><div class="summary-card">Saldo inicial<strong>R$ <?= number_format($resumo['valor_abertura'],2,',','.') ?></strong></div></div>
        <div class="col-md-3 mb-2"><div class="summary-card">Vendas em dinheiro<strong>R$ <?= number_format($resumo['total_dinheiro'],2,',','.') ?></strong></div></div>
        <div class="col-md-3 mb-2"><div class="summary-card">Ajustes em dinheiro<strong>R$ <?= number_format($resumo['total_manual'],2,',','.') ?></strong></div></div>
        <div class="col-md-3 mb-2"><div class="summary-card">Dinheiro esperado<strong>R$ <?= number_format($resumo['saldo_dinheiro_esperado'],2,',','.') ?></strong></div></div>
    </div>

    <?php if ($caixaAberto): ?>
    <form method="post" action="../actions/add_caixa_diario.php" class="p-3 mb-4 rounded" style="background:#282828">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <h3>Adicionar entrada ou retirada</h3>
        <div class="form-row">
            <div class="form-group col-md-3">
                <label>Movimento</label>
                <select name="tipo_movimento" class="form-control">
                    <option value="entrada">Entrada de dinheiro</option>
                    <option value="saida">Retirada de dinheiro</option>
                </select>
            </div>
            <div class="form-group col-md-3"><label>Valor</label><input name="valor" class="form-control" inputmode="decimal" required placeholder="0,00"></div>
            <div class="form-group col-md-4"><label>Descrição</label><input name="descricao" class="form-control" maxlength="255" required placeholder="Ex.: reforço de troco ou sangria"></div>
            <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-success btn-block">Salvar</button></div>
        </div>
    </form>
    <?php else: ?>
        <div class="alert alert-warning">Para adicionar dinheiro ou fazer uma retirada, primeiro abra o caixa no PDV.</div>
    <?php endif; ?>

    <h3>Histórico automático</h3>
    <div class="table-responsive">
        <table class="table table-dark table-hover">
            <thead><tr><th>Data/hora</th><th>Origem</th><th>Descrição</th><th>Forma</th><th>Entrada</th><th>Saída</th></tr></thead>
            <tbody>
            <?php foreach ($lancamentos as $item):
                $saida = $item['tipo'] === 'manual_saida';
            ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($item['criado_em'] ?? $item['data'])) ?></td>
                    <td><?= htmlspecialchars($rotulos[$item['tipo']] ?? ucfirst((string)$item['tipo']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($item['descricao'] ?: '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(ucfirst(str_replace('_',' ',(string)($item['forma_pagamento'] ?: '-'))), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="entrada"><?= !$saida ? 'R$ '.number_format((float)$item['valor'],2,',','.') : '-' ?></td>
                    <td class="saida"><?= $saida ? 'R$ '.number_format((float)$item['valor'],2,',','.') : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lancamentos): ?><tr><td colspan="6" class="text-center">Nenhuma movimentação registrada.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
</body>
</html>
