<?php
require_once '../includes/session_init.php';
require_once '../database.php';
include('../includes/header.php');

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../pages/login.php');
    exit;
}

$conn = getTenantConnection();
$id_usuario = $_SESSION['usuario_id'];
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

$sql = "
    SELECT forma_pagamento, SUM(valor_total) AS total 
    FROM vendas 
    WHERE id_usuario = ? AND DATE(data_venda) = ?
    GROUP BY forma_pagamento
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id_usuario, $data_selecionada);
$stmt->execute();
$lancamentos = $stmt->get_result();

$totais = [];
$total_geral = 0;
while ($row = $lancamentos->fetch_assoc()) {
    $forma = $row['forma_pagamento'] ?: 'outros';
    $totais[$forma] = $row['total'];
    $total_geral += $row['total'];
}

$clientes_novos = 0;
$clientes_retorno = 0;
$clientes_dia_ids = [];

$stmt_clientes_dia = $conn->prepare("
    SELECT DISTINCT id_cliente 
    FROM vendas 
    WHERE id_usuario = ? AND DATE(data_venda) = ?
");
$stmt_clientes_dia->bind_param("is", $id_usuario, $data_selecionada);
$stmt_clientes_dia->execute();
$result_clientes_dia = $stmt_clientes_dia->get_result();
while ($row = $result_clientes_dia->fetch_assoc()) {
    $clientes_dia_ids[] = $row['id_cliente'];
}
$stmt_clientes_dia->close();

if (!empty($clientes_dia_ids)) {
    $placeholders = implode(',', array_fill(0, count($clientes_dia_ids), '?'));
    $types = 'i' . str_repeat('i', count($clientes_dia_ids));
    $params = [$id_usuario, ...$clientes_dia_ids];

    $sql_primeira_venda = "
        SELECT id_cliente, MIN(DATE(data_venda)) AS data_primeira_venda
        FROM vendas
        WHERE id_usuario = ? AND id_cliente IN ($placeholders)
        GROUP BY id_cliente
    ";

    $stmt_primeira_venda = $conn->prepare($sql_primeira_venda);
    $stmt_primeira_venda->bind_param($types, ...$params);
    $stmt_primeira_venda->execute();
    $result_primeira_venda = $stmt_primeira_venda->get_result();

    while ($row_primeira = $result_primeira_venda->fetch_assoc()) {
        if ($row_primeira['data_primeira_venda'] == $data_selecionada) {
            $clientes_novos++;
        } else {
            $clientes_retorno++;
        }
    }
    $stmt_primeira_venda->close();
}

$stmt_vendas = $conn->prepare("
    SELECT v.id, v.data_venda, v.valor_total, v.forma_pagamento, pf.nome AS nome_cliente
    FROM vendas v
    LEFT JOIN pessoas_fornecedores pf ON v.id_cliente = pf.id
    WHERE v.id_usuario = ? AND DATE(v.data_venda) = ? 
    ORDER BY v.id DESC
");
$stmt_vendas->bind_param("is", $id_usuario, $data_selecionada);
$stmt_vendas->execute();
$vendas = $stmt_vendas->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Fechamento de Caixa</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #121212; color: #eee; font-family: 'Segoe UI', sans-serif; }
        .container { background-color: #1f1f1f; padding: 25px; border-radius: 10px; margin-top: 30px; box-shadow: 0 0 15px rgba(0,0,0,0.5); }
        h1 { color: #0af; }
        .card-resumo { border-left: 5px solid; padding: 10px 15px; border-radius: 6px; margin-bottom: 10px; font-size: 0.95rem; }
        .card-resumo strong { font-size: 1rem; }
        .forma-dinheiro { border-color: #28a745; background-color: rgba(40,167,69,0.1); }
        .forma-pix { border-color: #20c997; background-color: rgba(32,201,151,0.1); }
        .forma-debito { border-color: #17a2b8; background-color: rgba(23,162,184,0.1); }
        .forma-credito { border-color: #ffc107; background-color: rgba(255,193,7,0.1); }
        .forma-outros { border-color: #6c757d; background-color: rgba(108,117,125,0.1); }
        tr.venda:hover { background-color: #333; cursor: pointer; }
        .total-geral { background-color: #0d6efd; color: #fff; font-weight: bold; text-align: center; border-radius: 6px; padding: 10px; }
        @media print {
            body { background-color: #fff; color: #000; }
            .container { background-color: #fff; box-shadow: none; }
            .no-print { display: none; }
            .total-geral { background-color: #000; color: #fff; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center no-print mb-3">
        <h1><i class="fas fa-cash-register"></i> Fechamento de Caixa</h1>
        <a href="vendas.php" class="btn btn-secondary">Voltar</a>
    </div>

    <div id="alert-container-fechamento"></div>

    <form method="GET" class="form-inline mb-4 no-print">
        <label for="data" class="mr-2">Selecione a Data:</label>
        <input type="date" name="data" id="data" class="form-control mr-2" value="<?= htmlspecialchars($data_selecionada) ?>">
        <button type="submit" class="btn btn-primary">Buscar</button>
    </form>

    <h4 class="mb-3">Resumo por Forma de Pagamento</h4>
    <?php if (count($totais) > 0): ?>
        <?php foreach ($totais as $forma => $total): ?>
            <?php
                $classe = 'forma-outros';
                $nome = ucfirst(str_replace('_', ' ', $forma));
                if (stripos($forma, 'dinheiro') !== false) $classe = 'forma-dinheiro';
                elseif (stripos($forma, 'pix') !== false) $classe = 'forma-pix';
                elseif (stripos($forma, 'débito') !== false || stripos($forma, 'debito') !== false) $classe = 'forma-debito';
                elseif (stripos($forma, 'crédito') !== false || stripos($forma, 'credito') !== false) $classe = 'forma-credito';
            ?>
            <div class="card-resumo <?= $classe ?>">
                <strong><?= $nome ?>:</strong>
                <span class="float-right">R$ <?= number_format($total, 2, ',', '.') ?></span>
            </div>
        <?php endforeach; ?>
        <div class="total-geral mt-3">Total Geral: R$ <?= number_format($total_geral, 2, ',', '.') ?></div>
    <?php else: ?>
        <p>Nenhuma venda encontrada nesta data.</p>
    <?php endif; ?>

    <h4 class="mt-5 mb-3">Resumo de Clientes</h4>
    <div class="row">
        <div class="col-md-6">
            <div class="card-resumo forma-dinheiro">
                <strong>Clientes Novos:</strong>
                <span class="float-right" style="font-size: 1.2rem;"><?= $clientes_novos ?></span>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-resumo forma-debito">
                <strong>Clientes de Retorno:</strong>
                <span class="float-right" style="font-size: 1.2rem;"><?= $clientes_retorno ?></span>
            </div>
        </div>
    </div>

    <h4 class="mt-5 mb-3">Vendas do Dia</h4>
    <table class="table table-dark table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cliente</th> <th>Data</th>
                <th>Forma de Pagamento</th>
                <th>Valor Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($venda = $vendas->fetch_assoc()): ?>
            <tr class="venda" data-id="<?= $venda['id'] ?>">
                <td><?= $venda['id'] ?></td>
                <td><?= htmlspecialchars($venda['nome_cliente'] ?? 'Cliente Balcão') ?></td>
                <td><?= date('d/m/Y H:i', strtotime($venda['data_venda'])) ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $venda['forma_pagamento'])) ?></td>
                <td>R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <button onclick="window.print()" class="btn btn-success no-print mt-3">
        <i class="fas fa-print"></i> Imprimir
    </button>
</div>

<div class="modal fade" id="modalRomaneio" tabindex="-1" role="dialog" aria-labelledby="romaneioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="romaneioLabel">Romaneio da Venda</h5>
                <button type="button" class="close text-light" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="conteudoRomaneio">
                <p>Carregando...</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfirmarCancelamento" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;"> <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content bg-dark text-light border-danger">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Cancelar Venda</h5>
                <button type="button" class="close text-light" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p>Tem certeza que deseja cancelar a venda <strong>#<span id="span-venda-id"></span></strong>?</p>
                <p class="small text-muted">O estoque será devolvido e os lançamentos financeiros removidos. Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer border-top-0 justify-content-center">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Não, Voltar</button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-exclusao">Sim, Cancelar Venda</button>
            </div>
        </div>
    </div>
</div>

<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
    let vendaIdParaCancelar = null;

    // Função para exibir alertas na tela
    function showAlert(message, type) {
        $('#alert-container-fechamento').html(`
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        `);
    }

    // 1. Clicar na linha da tabela abre o Romaneio (Detalhes)
    $(".venda").click(function() {
        const vendaId = $(this).data("id");
        $("#conteudoRomaneio").html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Carregando...</div>');
        $("#modalRomaneio").modal("show");
        
        $.get("buscar_venda.php", { id: vendaId }, function(data) {
            $("#conteudoRomaneio").html(data);
        }).fail(function() {
            $("#conteudoRomaneio").html('<div class="alert alert-danger">Erro ao carregar detalhes.</div>');
        });
    });

    // 2. Clicar em "Cancelar Venda" dentro do Romaneio abre a Confirmação
    $('#modalRomaneio').on('click', '#btn-abrir-cancelar', function() {
        vendaIdParaCancelar = $(this).data('id');
        $('#span-venda-id').text(vendaIdParaCancelar);
        
        // Fecha o romaneio e abre a confirmação
        $('#modalRomaneio').modal('hide');
        setTimeout(() => {
            $('#modalConfirmarCancelamento').modal('show');
        }, 200); // Pequeno delay para transição suave
    });

    // 3. Confirmar o cancelamento no Modal de Confirmação
    $('#btn-confirmar-exclusao').click(function() {
        if (!vendaIdParaCancelar) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando...');

        fetch('cancelar_venda.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ 'venda_id': vendaIdParaCancelar })
        })
        .then(res => res.json())
        .then(data => {
            $('#modalConfirmarCancelamento').modal('hide');
            
            if (data.success) {
                showAlert(data.message, 'success');
                // Remove a linha da tabela visualmente ou recarrega
                $(`tr[data-id="${vendaIdParaCancelar}"]`).fadeOut();
                setTimeout(() => location.reload(), 1500); // Recarrega para atualizar totais
            } else {
                showAlert('Erro: ' + data.message, 'danger');
                btn.prop('disabled', false).html('Sim, Cancelar Venda');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            $('#modalConfirmarCancelamento').modal('hide');
            showAlert('Erro de comunicação com o servidor.', 'danger');
            btn.prop('disabled', false).html('Sim, Cancelar Venda');
        });
    });
});
</script>
</body>
</html>
