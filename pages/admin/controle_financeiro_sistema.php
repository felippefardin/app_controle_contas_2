<?php
require_once '../../includes/session_init.php';
include('../../database.php');

// Proteção: Apenas super admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$master_conn = getMasterConnection();

// --- 1. MÉTRICAS DE USUÁRIOS (PESSOAS) ---
// Contagem baseada na tabela 'usuarios'
// Lógica: Removemos caracteres não numéricos do documento para saber se é CPF (11) ou CNPJ (14)
$sql_users_details = "
    SELECT 
        SUM(CASE WHEN CHAR_LENGTH(REGEXP_REPLACE(documento, '[^0-9]', '')) = 14 THEN 1 ELSE 0 END) as total_cnpj,
        SUM(CASE WHEN CHAR_LENGTH(REGEXP_REPLACE(documento, '[^0-9]', '')) = 11 THEN 1 ELSE 0 END) as total_cpf,
        SUM(CASE WHEN perfil = 'admin' THEN 1 ELSE 0 END) as total_admins,
        SUM(CASE WHEN perfil = 'padrao' THEN 1 ELSE 0 END) as total_padrao,
        COUNT(*) as total_geral_usuarios
    FROM usuarios
";
$metrics_users = $master_conn->query($sql_users_details)->fetch_assoc();

// --- 2. MÉTRICAS DE TENANTS (EMPRESAS/ASSINATURAS) ---
// Contagem baseada na tabela 'tenants' para status da conta
$sql_tenants_status = "
    SELECT 
        SUM(CASE WHEN status_assinatura = 'ativo' THEN 1 ELSE 0 END) as contas_ativas,
        SUM(CASE WHEN status_assinatura = 'inativo' THEN 1 ELSE 0 END) as contas_inativas,
        SUM(CASE WHEN status_assinatura = 'cancelado' THEN 1 ELSE 0 END) as contas_canceladas,
        SUM(CASE WHEN data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as contas_novas_30d
    FROM tenants
";
$metrics_tenants = $master_conn->query($sql_tenants_status)->fetch_assoc();

// --- 3. LÓGICA FINANCEIRA (FATURAS DO SISTEMA) ---
$mes_atual = date('m');
$ano_atual = date('Y');
$filtro_mes = $_GET['mes'] ?? $mes_atual;
$filtro_ano = $_GET['ano'] ?? $ano_atual;

// Totais Financeiros do Mês Selecionado
$sql_finance = "
    SELECT 
        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as recebido,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as a_receber,
        COUNT(CASE WHEN status = 'pago' THEN 1 END) as qtd_pagas
    FROM faturas_assinatura 
    WHERE MONTH(data_vencimento) = '$filtro_mes' AND YEAR(data_vencimento) = '$filtro_ano'
";
$metrics_finance = $master_conn->query($sql_finance)->fetch_assoc();

$recebido = $metrics_finance['recebido'] ?? 0;
$a_receber = $metrics_finance['a_receber'] ?? 0;
$total_mes = $recebido + $a_receber;

// Detalhamento Diário
$sql_daily = "
    SELECT 
        DAY(data_vencimento) as dia,
        SUM(CASE WHEN status = 'pago' THEN valor ELSE 0 END) as total_pago,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as total_pendente
    FROM faturas_assinatura
    WHERE MONTH(data_vencimento) = '$filtro_mes' AND YEAR(data_vencimento) = '$filtro_ano'
    GROUP BY dia
    ORDER BY dia ASC
";
$res_daily = $master_conn->query($sql_daily);

$chart_labels = [];
$chart_data_pago = [];
$chart_data_pendente = [];
$daily_data = [];

while($row = $res_daily->fetch_assoc()) {
    $daily_data[] = $row;
    $chart_labels[] = $row['dia'] . '/' . $filtro_mes;
    $chart_data_pago[] = $row['total_pago'];
    $chart_data_pendente[] = $row['total_pendente'];
}

$json_labels = json_encode($chart_labels);
$json_pago = json_encode($chart_data_pago);
$json_pendente = json_encode($chart_data_pendente);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<link rel="stylesheet" href="../../assets/css/universal-theme.css?v=20260813-admin">
<script src="../../assets/js/global-theme.js?v=20260813-admin"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle Financeiro & Usuários</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #0e0e0e; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding-bottom: 40px; }
        .topbar { background: #1a1a1a; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.4); }
        .topbar a { color: #eee; text-decoration: none; font-weight: bold; }
        
        .container { max-width: 1250px; margin: 20px auto; padding: 20px; }
        
        h1, h2, h3 { color: #fff; text-align: center; }
        h2 { color: #00bfff; margin-top: 40px; border-bottom: 1px solid #333; padding-bottom: 10px; text-align: left; display: flex; align-items: center; gap: 10px; }

        /* CARDS */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 20px; }
        .card { background: #1e1e1e; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #333; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: transform 0.2s; position: relative; overflow: hidden; }
        .card:hover { transform: translateY(-3px); border-color: #444; }
        .card-icon { font-size: 2rem; margin-bottom: 10px; opacity: 0.9; }
        .card-value { font-size: 2rem; font-weight: bold; margin: 10px 0; color: #fff; }
        .card-label { font-size: 0.85rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px; }
        .card-sub { font-size: 0.8rem; color: #777; margin-top: 5px; }

        /* HEADER COLORS */
        .border-blue { border-top: 4px solid #3498db; }
        .border-green { border-top: 4px solid #2ecc71; }
        .border-red { border-top: 4px solid #e74c3c; }
        .border-orange { border-top: 4px solid #e67e22; }
        .border-purple { border-top: 4px solid #9b59b6; }
        .border-gray { border-top: 4px solid #7f8c8d; }

        .c-blue { color: #3498db; }
        .c-green { color: #2ecc71; }
        .c-red { color: #e74c3c; }
        .c-orange { color: #e67e22; }
        .c-purple { color: #9b59b6; }

        /* FILTER */
        .filter-bar { background: #1e1e1e; padding: 15px; border-radius: 8px; display: flex; justify-content: center; gap: 15px; margin-bottom: 30px; align-items: center; border: 1px solid #333; }
        select, button { padding: 10px 20px; border-radius: 4px; border: 1px solid #444; background: #2c2c2c; color: white; cursor: pointer; font-size: 14px; }
        button { background: #00bfff; border: none; font-weight: bold; transition: 0.2s; }
        button:hover { background: #009acd; }

        /* TABLE */
        table { width: 100%; border-collapse: collapse; margin-top: 0; background: #1e1e1e; border-radius: 0 0 8px 8px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #252525; color: #00bfff; text-transform: uppercase; font-size: 0.8rem; }
        tr:hover { background: #2a2a2a; }
        .text-right { text-align: right; }

        .btn-voltar { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; color: #aaa; text-decoration: none; transition: 0.2s; }
        .btn-voltar:hover { color: #fff; }
    </style>
</head>
<body>

    <div class="topbar">
        <div style="font-weight: bold; color: #00bfff;">Painel Master <span style="color:#fff;">Financeiro</span></div>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>

    <div class="container">
        <a href="dashboard.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar ao Dashboard</a>

        <h1>Panorama Geral do Sistema</h1>

        <h2><i class="fas fa-id-card"></i> Base de Usuários (Pessoas)</h2>
        <div class="cards-grid">
            
            <div class="card border-blue">
                <div class="card-icon c-blue"><i class="fas fa-users"></i></div>
                <div class="card-value"><?= $metrics_users['total_geral_usuarios'] ?></div>
                <div class="card-label">Total de Usuários</div>
            </div>

            <div class="card border-purple">
                <div class="card-icon c-purple"><i class="fas fa-building"></i></div>
                <div class="card-value"><?= $metrics_users['total_cnpj'] ?></div>
                <div class="card-label">CNPJs Cadastrados</div>
                <div class="card-sub">Pessoas Jurídicas</div>
            </div>

            <div class="card border-purple">
                <div class="card-icon c-purple"><i class="fas fa-user"></i></div>
                <div class="card-value"><?= $metrics_users['total_cpf'] ?></div>
                <div class="card-label">CPFs Cadastrados</div>
                <div class="card-sub">Pessoas Físicas</div>
            </div>

            <div class="card border-orange">
                <div class="card-icon c-orange"><i class="fas fa-user-shield"></i></div>
                <div class="card-value"><?= $metrics_users['total_admins'] ?></div>
                <div class="card-label">Contas Admin</div>
                <div class="card-sub">Gestores do Sistema</div>
            </div>

            <div class="card border-orange">
                <div class="card-icon c-orange"><i class="fas fa-user-tag"></i></div>
                <div class="card-value"><?= $metrics_users['total_padrao'] ?></div>
                <div class="card-label">Contas Padrão</div>
                <div class="card-sub">Funcionários/Operadores</div>
            </div>

        </div>

        <h2><i class="fas fa-server"></i> Status das Assinaturas (Tenants)</h2>
        <div class="cards-grid">
            
            <div class="card border-green">
                <div class="card-icon c-green"><i class="fas fa-check-circle"></i></div>
                <div class="card-value"><?= $metrics_tenants['contas_ativas'] ?></div>
                <div class="card-label">Contas Ativas</div>
            </div>

            <div class="card border-gray">
                <div class="card-icon" style="color: #95a5a6;"><i class="fas fa-pause-circle"></i></div>
                <div class="card-value"><?= $metrics_tenants['contas_inativas'] ?></div>
                <div class="card-label">Contas Desativadas</div>
            </div>

            <div class="card border-red">
                <div class="card-icon c-red"><i class="fas fa-ban"></i></div>
                <div class="card-value"><?= $metrics_tenants['contas_canceladas'] ?></div>
                <div class="card-label">Contas Canceladas</div>
            </div>

            <div class="card border-blue">
                <div class="card-icon c-blue"><i class="fas fa-rocket"></i></div>
                <div class="card-value"><?= $metrics_tenants['contas_novas_30d'] ?></div>
                <div class="card-label">Novas Contas</div>
                <div class="card-sub">Últimos 30 dias</div>
            </div>

        </div>

        <h2><i class="fas fa-wallet"></i> Controle Financeiro</h2>
        
        <form class="filter-bar" method="GET">
            <div style="color: #aaa; font-weight: bold;">Período:</div>
            <select name="mes">
                <?php for($i=1; $i<=12; $i++): $m = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $m ?>" <?= $filtro_mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
            <select name="ano">
                <?php for($i=date('Y'); $i>=2023; $i--): ?>
                    <option value="<?= $i ?>" <?= $filtro_ano == $i ? 'selected' : '' ?>><?= $i ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
        </form>

        <div class="cards-grid">
            <div class="card border-green">
                <div class="card-icon c-green"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="card-value">R$ <?= number_format($recebido, 2, ',', '.') ?></div>
                <div class="card-label">Recebido (Pago)</div>
                <div class="card-sub"><?= $metrics_finance['qtd_pagas'] ?? 0 ?> faturas pagas</div>
            </div>
            <div class="card border-orange">
                <div class="card-icon c-orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="card-value">R$ <?= number_format($a_receber, 2, ',', '.') ?></div>
                <div class="card-label">A Receber (Pendente)</div>
            </div>
            <div class="card border-blue">
                <div class="card-icon c-blue"><i class="fas fa-coins"></i></div>
                <div class="card-value">R$ <?= number_format($total_mes, 2, ',', '.') ?></div>
                <div class="card-label">Previsão Total</div>
            </div>
        </div>

        <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 30px;">
            <div style="flex: 2; min-width: 400px; background: #1e1e1e; padding: 20px; border-radius: 8px; border: 1px solid #333;">
                <h3 style="margin-top:0; text-align: left; font-size: 1.1rem; color: #ddd;"><i class="fas fa-chart-bar"></i> Fluxo Diário - <?= $filtro_mes ?>/<?= $filtro_ano ?></h3>
                <div style="height: 350px;">
                    <canvas id="financeChart"></canvas>
                </div>
            </div>

            <div style="flex: 1; min-width: 300px; background: #1e1e1e; padding: 0; border-radius: 8px; border: 1px solid #333; display: flex; flex-direction: column;">
                <div style="padding: 15px 20px; border-bottom: 1px solid #333; background: #252525;">
                    <h3 style="margin:0; text-align: left; font-size: 1rem; color: #fff;">Detalhamento por Dia</h3>
                </div>
                <div style="overflow-y: auto; max-height: 350px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Dia</th>
                                <th class="text-right">Recebido</th>
                                <th class="text-right">Pendente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($daily_data)): ?>
                                <tr><td colspan="3" style="text-align:center; padding: 30px; color: #777;">Nenhum dado financeiro neste mês.</td></tr>
                            <?php else: ?>
                                <?php foreach($daily_data as $d): ?>
                                <tr>
                                    <td>Dia <?= str_pad($d['dia'], 2, '0', STR_PAD_LEFT) ?></td>
                                    <td class="text-right c-green">R$ <?= number_format($d['total_pago'], 2, ',', '.') ?></td>
                                    <td class="text-right c-orange">R$ <?= number_format($d['total_pendente'], 2, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        const ctx = document.getElementById('financeChart').getContext('2d');
        const financeChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $json_labels ?>,
                datasets: [
                    {
                        label: 'Recebido (R$)',
                        data: <?= $json_pago ?>,
                        backgroundColor: '#2ecc71',
                        borderRadius: 4,
                        barPercentage: 0.6
                    },
                    {
                        label: 'A Receber (R$)',
                        data: <?= $json_pendente ?>,
                        backgroundColor: '#e67e22',
                        borderRadius: 4,
                        barPercentage: 0.6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: '#ccc' }, position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#333', borderDash: [5, 5] },
                        ticks: { color: '#aaa', callback: function(value) { return 'R$ ' + value; } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#aaa' }
                    }
                }
            }
        });
    </script>
</body>
</html>
