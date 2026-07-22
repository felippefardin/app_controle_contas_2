<?php
require_once '../includes/session_init.php';
require_once '../database.php';

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    echo "SessÃ£o expirada.";
    exit;
}

$conn = getTenantConnection();
$id_usuario = get_data_owner_id();
$id_venda = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Busca dados da venda
$sql_venda = "SELECT v.*, pf.nome AS nome_cliente, nf.chave_acesso AS chave_nfe, nf.ambiente AS ambiente_nfe, nf.status AS status_nfe
              FROM vendas v
              LEFT JOIN pessoas_fornecedores pf ON v.id_cliente = pf.id
              LEFT JOIN notas_fiscais nf ON nf.id = (
                  SELECT nf2.id FROM notas_fiscais nf2
                  WHERE nf2.id_venda = v.id AND nf2.status = 'autorizada'
                  ORDER BY nf2.id DESC LIMIT 1
              )
              WHERE v.id = ? AND v.id_usuario = ?";
$stmt = $conn->prepare($sql_venda);
$stmt->bind_param("ii", $id_venda, $id_usuario);
$stmt->execute();
$venda = $stmt->get_result()->fetch_assoc();

if (!$venda) {
    echo "<div class='alert alert-danger'>Venda nÃ£o encontrada.</div>";
    exit;
}

// 2. Busca itens
$sql_itens = "SELECT vi.*, p.nome AS nome_produto 
              FROM venda_items vi
              LEFT JOIN produtos p ON vi.id_produto = p.id
              WHERE vi.id_venda = ?";
$stmt_itens = $conn->prepare($sql_itens);
$stmt_itens->bind_param("i", $id_venda);
$stmt_itens->execute();
$result_itens = $stmt_itens->get_result();
?>

<div class="row mb-3">
    <div class="col-6">
        <strong>Venda #<?= $venda['id'] ?></strong><br>
        Data: <?= date('d/m/Y H:i', strtotime($venda['data_venda'])) ?>
    </div>
    <div class="col-6 text-right">
        Cliente: <?= htmlspecialchars($venda['nome_cliente'] ?? 'BalcÃ£o') ?><br>
        Forma: <?= ucfirst(str_replace('_',' ',$venda['forma_pagamento'])) ?>
    </div>
</div>

<table class="table table-sm table-bordered text-light">
    <thead>
        <tr>
            <th>Produto</th>
            <th class="text-right">Qtd</th>
            <th class="text-right">Unit.</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php while($item = $result_itens->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($item['nome_produto']) ?></td>
            <td class="text-right"><?= $item['quantidade'] ?></td>
            <td class="text-right">R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
            <td class="text-right">R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3" class="text-right">Total</th>
            <th class="text-right">R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?></th>
        </tr>
    </tfoot>
</table>

<hr class="bg-secondary">

<div class="d-flex justify-content-between">

    <a href="recibo_venda.php?id=<?= $venda['id'] ?>" target="_blank" class="btn btn-primary">
        <i class="fas fa-print"></i> Imprimir Recibo
    </a>

    <?php if (!empty($venda['chave_nfe'])): ?>
        <a 
            id="btn-imprimir-danfe"
            href="../actions/gerar_danfe.php?chave=<?= $venda['chave_nfe'] ?>" 
            target="_blank"
            class="btn btn-info"
        >
            <i class="fas fa-file-invoice"></i> DANFE
        </a>
        <?php if ((int)$venda['ambiente_nfe'] === 2): ?><small class="text-warning align-self-center">SimulaÃ§Ã£o/HomologaÃ§Ã£o</small><?php endif; ?>
    <?php else: ?>
        <button class="btn btn-secondary" disabled>
            <i class="fas fa-file-invoice"></i> DANFE indisponÃ­vel
        </button>
    <?php endif; ?>

    <button type="button" class="btn btn-danger" id="btn-abrir-cancelar" data-id="<?= $venda['id'] ?>">
        <i class="fas fa-trash"></i> Cancelar Venda
    </button>

</div>

