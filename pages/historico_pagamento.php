<?php
require_once '../includes/session_init.php';
require_once '../database.php';

// Verifica permissão
if (!isset($_SESSION['nivel_acesso']) || ($_SESSION['nivel_acesso'] !== 'admin' && $_SESSION['nivel_acesso'] !== 'master' && $_SESSION['nivel_acesso'] !== 'proprietario')) {
    header("Location: home.php");
    exit;
}

$conn = getMasterConnection();
$tenant_id = $_SESSION['tenant_id'] ?? null;
$faturas = [];

// --- LÓGICA DE DADOS ---
try {
    // Verifica se a tabela existe
    $checkTable = $conn->query("SHOW TABLES LIKE 'faturas_assinatura'");
    
    if ($checkTable && $checkTable->num_rows > 0) {
        // Tenta identificar colunas dinamicamente para evitar erro se 'valor_original' ou 'desconto' não existirem
        $cols = $conn->query("SHOW COLUMNS FROM faturas_assinatura");
        $colNames = [];
        while($c = $cols->fetch_assoc()) { $colNames[] = $c['Field']; }
        
        $sqlCampos = "id, data_vencimento, data_pagamento, valor, status, forma_pagamento";
        
        // Se existir a coluna, usa. Senão, cria um alias padrão
        if(in_array('valor_original', $colNames)) $sqlCampos .= ", valor_original";
        else $sqlCampos .= ", valor as valor_original"; 
        
        if(in_array('desconto', $colNames)) $sqlCampos .= ", desconto";
        else $sqlCampos .= ", 0 as desconto";

        $stmt = $conn->prepare("SELECT $sqlCampos FROM faturas_assinatura WHERE tenant_id = ? ORDER BY data_vencimento DESC");
        $stmt->bind_param("s", $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $faturas[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Erro ao buscar histórico: " . $e->getMessage());
}

include('../includes/header.php');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Pagamentos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #121212; color: #eee; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        
        .header-page { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #333; padding-bottom: 15px; }
        .header-page h2 { color: #00bfff; margin: 0; }
        
        .table-responsive { background-color: #1e1e1e; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #252525; color: #aaa; padding: 15px; text-align: left; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #333; color: #ddd; vertical-align: middle; font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #2a2a2a; }

        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase; }
        .badge-pago { background-color: rgba(40, 167, 69, 0.2); color: #2ecc71; border: 1px solid #28a745; }
        .badge-pendente { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        .badge-falha { background-color: rgba(220, 53, 69, 0.2); color: #ff6b6b; border: 1px solid #dc3545; }

        .btn-recibo { background-color: #333; color: #fff; border: 1px solid #555; padding: 8px 15px; border-radius: 5px; text-decoration: none; transition: 0.3s; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn-recibo:hover { background-color: #00bfff; color: #000; border-color: #00bfff; }

        html[data-app-theme="light"] #conteudo-principal .btn-recibo {
            background-color: var(--app-accent);
            border-color: var(--app-accent);
            color: #fff !important;
        }
        html[data-app-theme="light"] #conteudo-principal .btn-recibo i {
            color: inherit !important;
        }
        html[data-app-theme="light"] #conteudo-principal .btn-recibo:is(:hover, :focus-visible) {
            background-color: #06679b;
            border-color: #06679b;
        }
        
        .btn-voltar { color: #aaa; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-voltar:hover { color: #fff; }

        .text-original { text-decoration: line-through; color: #777; font-size: 0.85rem; margin-right: 5px; }
        .text-final { color: #fff; font-weight: bold; }
        .discount-tag { font-size: 0.75rem; color: #2ecc71; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-page">
        <h2><i class="fa-solid fa-list-ul"></i> Histórico de Pagamentos Detalhado</h2>
        <a href="minha_assinatura.php" class="btn-voltar"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
    </div>

    <?php if (empty($faturas)): ?>
        <div style="text-align: center; padding: 50px; color: #777; background: #1e1e1e; border-radius: 8px;">
            <i class="fa-regular fa-folder-open fa-3x"></i>
            <p style="margin-top: 15px;">Nenhuma fatura encontrada.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Data Venc.</th>
                        <th>Data Pagto.</th>
                        <th>Valores</th>
                        <th>Forma</th>
                        <th>Status</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faturas as $fat): 
                        // Cálculos visuais
                        $vlrOriginal = isset($fat['valor_original']) ? (float)$fat['valor_original'] : (float)$fat['valor'];
                        $vlrFinal = (float)$fat['valor'];
                        $vlrDesconto = isset($fat['desconto']) ? (float)$fat['desconto'] : ($vlrOriginal - $vlrFinal);
                        
                        // Proteção visual
                        if ($vlrDesconto < 0) $vlrDesconto = 0;
                    ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($fat['data_vencimento'])) ?></td>
                        <td>
                            <?= !empty($fat['data_pagamento']) ? date('d/m/Y', strtotime($fat['data_pagamento'])) : '-' ?>
                        </td>
                        <td>
                            <?php if ($vlrDesconto > 0.00): ?>
                                <div>
                                    <span class="text-original">R$ <?= number_format($vlrOriginal, 2, ',', '.') ?></span>
                                    <span class="text-final">R$ <?= number_format($vlrFinal, 2, ',', '.') ?></span>
                                </div>
                                <span class="discount-tag"><i class="fa-solid fa-tag"></i> Desconto: -R$ <?= number_format($vlrDesconto, 2, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="text-final">R$ <?= number_format($vlrFinal, 2, ',', '.') ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-transform: capitalize;"><?= htmlspecialchars((string) ($fat['forma_pagamento'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($fat['status'] === 'pago'): ?>
                                <span class="badge badge-pago">Pago</span>
                            <?php elseif ($fat['status'] === 'pendente'): ?>
                                <span class="badge badge-pendente">Pendente</span>
                            <?php else: ?>
                                <span class="badge badge-falha"><?= htmlspecialchars($fat['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($fat['status'] === 'pago'): ?>
                                <a href="recibo_pagamentos.php?id=<?= $fat['id'] ?>" target="_blank" class="btn-recibo">
                                    <i class="fa-solid fa-print"></i> Recibo
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>
</body>
</html>