<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';

$tenantId = (string) ($_SESSION['tenant_id'] ?? '');
if (empty($_SESSION['usuario_logado']) || $tenantId === ''
    || !in_array($_SESSION['nivel_acesso'] ?? '', ['admin', 'master', 'proprietario'], true)) {
    http_response_code(403);
    exit('Acesso negado.');
}

$id_fatura = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id_fatura === null) {
    header('Location: historico_pagamento.php');
    exit;
}
if ($id_fatura === false) {
    http_response_code(400);
    exit('Fatura inválida.');
}

try {
    $conn = getMasterConnection();
    $stmt = $conn->prepare(
        "SELECT id, data_pagamento, valor, forma_pagamento FROM faturas_assinatura
         WHERE id = ? AND tenant_id = ? AND status = 'pago' AND data_pagamento IS NOT NULL LIMIT 1"
    );
    $stmt->bind_param('is', $id_fatura, $tenantId);
    $stmt->execute();
    $fatura = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fatura || strtotime($fatura['data_pagamento']) === false) {
        http_response_code(404);
        exit('Recibo de pagamento não encontrado.');
    }

    $owner = $conn->prepare("SELECT nome, cpf FROM usuarios WHERE tenant_id = ? AND nivel_acesso = 'proprietario' ORDER BY id LIMIT 1");
    $owner->bind_param('s', $tenantId);
    $owner->execute();
    $cliente = $owner->get_result()->fetch_assoc();
    $owner->close();
    $fatura['nome_cliente'] = $cliente['nome'] ?? 'Cliente';
    $fatura['cpf_cnpj'] = $cliente['cpf'] ?? '';
    $fatura['descricao'] = 'Pagamento de assinatura - App Controle de Contas';
} catch (Throwable $error) {
    error_log('Falha ao consultar recibo: ' . $error->getMessage());
    http_response_code(500);
    exit('Não foi possível consultar o recibo.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<?php session_idle_script(); ?>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
    <meta charset="UTF-8">
    <title>Recibo #<?= $id_fatura ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #ccc; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 40px 0; margin: 0; }
        .recibo-container {
            background-color: #fff;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-radius: 4px;
            color: #333;
        }
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #333; text-transform: uppercase; letter-spacing: 2px; font-size: 24px; }
        .header p { margin: 5px 0 0; color: #777; font-size: 14px; }
        
        .info-grid { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .info-box h3 { font-size: 14px; color: #999; text-transform: uppercase; margin: 0 0 5px 0; }
        .info-box p { margin: 0; font-weight: bold; font-size: 16px; }
        
        .details-box { background-color: #f9f9f9; padding: 20px; border-radius: 6px; border: 1px solid #eee; margin-bottom: 30px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .row:last-child { margin-bottom: 0; }
        .row span:first-child { color: #666; }
        .row span:last-child { font-weight: bold; text-align: right; }
        
        .total-row { border-top: 2px solid #ddd; margin-top: 15px; padding-top: 15px; font-size: 1.2em; color: #000; }
        
        .footer { text-align: center; font-size: 12px; color: #aaa; margin-top: 40px; }
        
        .btn-print {
            display: block; width: 100%; max-width: 200px; margin: 20px auto 0; padding: 12px; 
            background: #007bff; color: white; text-align: center; border-radius: 5px; 
            text-decoration: none; font-weight: bold; cursor: pointer; border: none;
        }
        .btn-print:hover { background: #0056b3; }
        
        @media print {
            body { background-color: #fff; padding: 0; }
            .recibo-container { box-shadow: none; max-width: 100%; padding: 0; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="recibo-container">
    <div class="header">
        <h1><i class="fa-solid fa-check-circle" style="color: #28a745;"></i> Recibo de Pagamento</h1>
        <p>Comprovante de pagamento de assinatura</p>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h3>Pagador</h3>
            <p><?= htmlspecialchars($fatura['nome_cliente']) ?></p>
            <p style="font-size: 14px; color: #555; font-weight: normal;"><?= htmlspecialchars($fatura['cpf_cnpj'] ?? '') ?></p>
        </div>
        <div class="info-box" style="text-align: right;">
            <h3>Beneficiário</h3>
            <p>App Controle de Contas</p>
        </div>
    </div>

    <div class="details-box">
        <div class="row">
            <span>Número da Fatura:</span>
            <span>#<?= str_pad($fatura['id'], 6, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="row">
            <span>Data do Pagamento:</span>
            <span><?= date('d/m/Y', strtotime($fatura['data_pagamento'])) ?></span>
        </div>
        <div class="row">
            <span>Forma de Pagamento:</span>
            <span style="text-transform: uppercase;"><?= htmlspecialchars($fatura['forma_pagamento'] ?? 'Diversos') ?></span>
        </div>
        <div class="row">
            <span>Descrição:</span>
            <span><?= htmlspecialchars($fatura['descricao'] ?? 'Assinatura') ?></span>
        </div>
        
        <div class="row total-row">
            <span>Valor Total Pago:</span>
            <span style="color: #28a745;">R$ <?= number_format($fatura['valor'], 2, ',', '.') ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Este documento é um comprovante de pagamento gerado eletronicamente.</p>
        <p><?= date('d/m/Y H:i:s') ?></p>
    </div>

    <button onclick="window.print()" class="btn-print"><i class="fa-solid fa-print"></i> Imprimir Recibo</button>
</div>

</body>
</html>