<?php
// pages/minha_assinatura.php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/cupom_service.php';

/* -------------------- 1) Verificações iniciais -------------------- */
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$usuario_nome_sess = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Cliente';

/* -------------------- 2) Conexão com BD master -------------------- */
$conn = getMasterConnection();
if ($conn === null) {
    die("Erro ao conectar ao banco de dados principal.");
}

/* Valores padrão */
$dados_assinatura = [];
$plano_db = 'basico';
$nome_plano_atual = 'Plano Básico';
$limite_base = 3;
$extras_comprados = 0;
$limite_total = 3;
$status_assinatura = 'padrao';
$is_trial = false;
$dias_restantes_teste = 0;
$dias_ate_renovacao = 0;
$tipo_cancelamento = null;
$valor_fatura_total = 0.00;
$codigo_indicacao_usado = null;
$nome_indicador = null;
$meses_gratis_indicacao = 0;
$inicio_beneficio_indicacao = null;
$fim_beneficio_indicacao = null;
$beneficio_indicacao_utilizado = false;

// Variáveis de Preço e Desconto
$preco_plano_base = 0.00;
$preco_final_atual = 0.00;
$tem_desconto = false;
$data_fim_promocao = null;
$nome_cupom_ativo = null;
$cupom_primeiro_pagamento = false;
$valor_desconto_cupom = 0.00;
$valor_original_fatura = 0.00;
$calculo_total_definido = false;

// Configuração do preço por usuário extra
$preco_por_extra = 4.00; 

/* Mapa de planos */
$mapa_planos = [
    'basico'    => ['nome' => 'Plano Básico', 'base' => 3,  'desc' => 'Ideal para pequenos negócios',       'preco' => 19.00],
    'plus'      => ['nome' => 'Plano Plus',   'base' => 6,  'desc' => 'Para empresas em crescimento',      'preco' => 39.00],
    'essencial' => ['nome' => 'Plano Essencial','base' => 16,'desc' => 'Gestão completa para sua equipe',   'preco' => 59.00]
];

/* -------------------- 3) Buscar dados do tenant -------------------- */
if ($tenant_id) {
    // Busca dados principais do Tenant
    $stmt = $conn->prepare("SELECT * FROM tenants WHERE tenant_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $tenant_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $dados_assinatura = $result->fetch_assoc();

            if (!empty($dados_assinatura['nome_empresa'])) {
                $usuario_nome_sess = $dados_assinatura['nome_empresa'];
            } elseif (!empty($dados_assinatura['nome'])) {
                $usuario_nome_sess = $dados_assinatura['nome'];
            }

            $plano_db = $dados_assinatura['plano_atual'] ?? 'basico';
            if (!array_key_exists($plano_db, $mapa_planos)) {
                $plano_db = 'basico';
            }

            $extras_comprados = (int)($dados_assinatura['usuarios_extras'] ?? 0);
            $info_plano = $mapa_planos[$plano_db];
            
            $limite_base = (int)($info_plano['base'] ?? 3);
            $nome_plano_atual = $info_plano['nome'] ?? 'Plano Básico';
            $preco_plano_base = (float)($info_plano['preco'] ?? 0.00); 
            
            $limite_total = $limite_base + $extras_comprados;
            $status_assinatura = $dados_assinatura['status_assinatura'] ?? 'padrao';
            $tipo_cancelamento = $dados_assinatura['tipo_cancelamento'] ?? null;
            $meses_gratis_indicacao = min(2, max(0, (int)($dados_assinatura['indicacao_meses_gratis'] ?? 0)));
            $inicio_beneficio_indicacao = $dados_assinatura['indicacao_inicio_beneficio'] ?? null;
            $fim_beneficio_indicacao = $dados_assinatura['indicacao_fim_beneficio'] ?? null;
            $beneficio_indicacao_utilizado = (int)($dados_assinatura['indicacao_beneficio_utilizado'] ?? 0) === 1;
            $custo_extras_total = $extras_comprados * $preco_por_extra;
            $valor_original_fatura = $preco_plano_base + $custo_extras_total;

            // Exibe a indicação vinculada ao cadastro. O novo usuário recebe
            // dois meses grátis a partir da contratação; a recompensa por
            // metas de indicação do indicador é um benefício separado.
            $usuario_master_id = (int)($dados_assinatura['usuario_id'] ?? 0);
            if ($usuario_master_id > 0) {
                $stmtIndicacao = $conn->prepare("
                    SELECT indicador.nome, indicador.codigo_indicacao
                    FROM indicacoes i
                    JOIN usuarios indicador ON indicador.id = i.id_indicador
                    WHERE i.id_indicado = ?
                    ORDER BY i.id ASC
                    LIMIT 1
                ");
                $stmtIndicacao->bind_param('i', $usuario_master_id);
                $stmtIndicacao->execute();
                $indicacao = $stmtIndicacao->get_result()->fetch_assoc();
                $stmtIndicacao->close();

                if ($indicacao) {
                    $nome_indicador = $indicacao['nome'];
                    $codigo_indicacao_usado = $indicacao['codigo_indicacao'];
                }
            }

            // --- Lógica de Desconto (Corrigida para ler tabela de promoções) ---
            
            // 1. Preço base inicial
            $preco_final_atual = $preco_plano_base;

            // 2. Verifica se há promoção ativa vinda do Admin (Modal)
            $stmtPromo = $conn->prepare("
                SELECT tp.data_fim, c.tipo_desconto, c.valor, c.codigo 
                FROM tenant_promocoes tp 
                JOIN cupons_desconto c ON tp.cupom_id = c.id 
                WHERE tp.tenant_id = ? 
                AND tp.ativo = 1 
                AND tp.data_inicio <= CURDATE() 
                AND tp.data_fim >= CURDATE() 
                ORDER BY tp.id DESC LIMIT 1
            ");
            
            $tenant_id_int = $dados_assinatura['id']; 
            
            $stmtPromo->bind_param("i", $tenant_id_int);
            $stmtPromo->execute();
            $resPromo = $stmtPromo->get_result();

            if ($resPromo && $resPromo->num_rows > 0) {
                $promo = $resPromo->fetch_assoc();
                $tem_desconto = true;
                $data_fim_promocao = date('d/m/Y', strtotime($promo['data_fim']));
                $nome_cupom_ativo = $promo['codigo'];

                if ($promo['tipo_desconto'] === 'porcentagem') {
                    $desconto_valor = $preco_plano_base * ($promo['valor'] / 100);
                    $preco_final_atual = $preco_plano_base - $desconto_valor;
                } else {
                    $preco_final_atual = $preco_plano_base - $promo['valor'];
                }
            } else {
                // 3. Cupom informado no cadastro: mostra o desconto durante
                // o teste, mas ele só será consumido no primeiro pagamento.
                $cupom_registro = strtoupper(trim((string)($dados_assinatura['cupom_registro'] ?? '')));
                if ($cupom_registro !== '') {
                    $validacaoCupom = validarCupomPassivo($conn, $cupom_registro, (int)$tenant_id_int);
                    if ($validacaoCupom['ok']) {
                        $cupom = $validacaoCupom['cupom'];
                        $calculoCupom = calcularDescontoCupom($preco_plano_base, $custo_extras_total, $cupom);

                        $tem_desconto = true;
                        $cupom_primeiro_pagamento = true;
                        $nome_cupom_ativo = $cupom['codigo'];
                        $valor_desconto_cupom = (float)$calculoCupom['desconto'];
                        $valor_fatura_total = (float)$calculoCupom['final'];
                        $preco_final_atual = max(0, $valor_fatura_total - $custo_extras_total);
                        $calculo_total_definido = true;

                        if (!empty($cupom['data_expiracao'])) {
                            $data_fim_promocao = date('d/m/Y', strtotime($cupom['data_expiracao']));
                        }
                    }
                }

                // 4. Fallback: Valor promocional direto na tabela
                $valor_promocional = isset($dados_assinatura['valor_promocional']) ? (float)$dados_assinatura['valor_promocional'] : null;
                $fim_promo_db = $dados_assinatura['data_fim_promocao'] ?? null;

                if (!$tem_desconto && $valor_promocional !== null && $valor_promocional < $preco_plano_base) {
                    if ($fim_promo_db) {
                        $dt_fim_promo = new DateTime($fim_promo_db);
                        $dt_hoje_promo = new DateTime('now');
                        if ($dt_hoje_promo <= $dt_fim_promo) {
                            $tem_desconto = true;
                            $preco_final_atual = $valor_promocional;
                            $data_fim_promocao = $dt_fim_promo->format('d/m/Y');
                        }
                    } else {
                        $tem_desconto = true;
                        $preco_final_atual = $valor_promocional;
                    }
                }
            }

            // Garante que não fique negativo
            if ($preco_final_atual < 0) $preco_final_atual = 0;

            // --- CÁLCULO FINAL DA FATURA (Plano + Extras) ---
            if (!$calculo_total_definido) {
                $valor_fatura_total = $preco_final_atual + $custo_extras_total;
                $valor_desconto_cupom = max(0, $valor_original_fatura - $valor_fatura_total);
            }

            // Data renovação e Trial
            $data_renovacao_str = $dados_assinatura['data_renovacao'] ?? null;
            if (!empty($data_renovacao_str)) {
                try {
                    $dt_hoje = new DateTime('now');
                    $dt_hoje->setTime(0,0,0);
                    $dt_renovacao = new DateTime($data_renovacao_str);
                    $dt_renovacao->setTime(0,0,0);
                    $dias_ate_renovacao = ($dt_renovacao > $dt_hoje) ? (int)$dt_hoje->diff($dt_renovacao)->format('%a') : 0;
                } catch (Exception $e) { $dias_ate_renovacao = 0; }
            }

            if (($dados_assinatura['status_assinatura'] ?? '') === 'trial') {
                $is_trial = true;
                $dias_teste = ($plano_db === 'essencial') ? 30 : 15;
                $data_ref = $dados_assinatura['data_inicio_teste'] ?? $dados_assinatura['data_criacao'] ?? null;
                if ($data_ref) {
                    try {
                        $data_inicio = new DateTime($data_ref);
                        $data_inicio->setTime(0,0,0);
                        $data_fim_teste = clone $data_inicio;
                        $data_fim_teste->modify("+{$dias_teste} days");
                        $hoje = new DateTime('now');
                        $hoje->setTime(0,0,0);
                        $dias_restantes_teste = ($hoje < $data_fim_teste) ? (int)$hoje->diff($data_fim_teste)->format('%a') : 0;
                    } catch (Exception $e) { $dias_restantes_teste = 0; }
                }
            }
        }
        $stmt->close();
    }
}
$trocaPlanoPendente = false;
if ($tenant_id) {
    $stmtTroca = $conn->prepare("SELECT id FROM trocas_planos WHERE tenant_id = ? AND estado <> 'concluida' LIMIT 1");
    $stmtTroca->bind_param('s', $tenant_id);
    $stmtTroca->execute();
    $trocaPlanoPendente = (bool) $stmtTroca->get_result()->fetch_row();
    $stmtTroca->close();
}
$conn->close();

include('../includes/header.php');
display_flash_message();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Minha Assinatura</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #121212; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container-assinatura {
            width: calc(100% - 32px);
            max-width: none;
            margin: 12px auto;
            padding: 0;
        }
        .page-header { border-bottom: 1px solid #00bfff; padding-bottom: 8px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; gap: 15px; }
        .page-header h2 { margin: 0; color: #00bfff; font-size: 1.45rem; }
        .btn-top-back { color: #aaa; text-decoration: none; font-size: 1rem; display: flex; align-items: center; gap: 8px; padding: 8px 15px; border: 1px solid #333; border-radius: 6px; background-color: #1e1e1e; transition: all 0.3s; }
        .btn-top-back:hover { color: #fff; border-color: #555; background-color: #2c2c2c; }
        .card-custom { background-color: #1e1e1e; border: 1px solid #333; border-radius: 10px; padding: 14px 16px; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: border-color 0.3s; }
        .card-custom:hover { border-color: #444; box-shadow: 0 4px 20px rgba(0, 191, 255, 0.1); }
        .card-title { color: #00bfff; font-size: 1.08rem; border-bottom: 1px solid #333; padding-bottom: 7px; margin-bottom: 9px; display: flex; align-items: center; gap: 8px; }
        .grid-layout { display: grid; grid-template-columns: 1.15fr .85fr; gap: 12px; align-items: stretch; }
        @media(max-width: 768px) { .grid-layout { grid-template-columns: 1fr; } }
        .info-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid #2c2c2c; }
        .info-row:last-child { border-bottom: none; }
        .label { color: #aaa; font-size: 0.95rem; }
        .value { font-weight: bold; color: #fff; font-size: 1rem; text-align: right; }
        .status-ativo { color: #2ecc71; }
        .status-inativo { color: #e74c3c; }
        .extra-highlight { color: #00bfff; font-weight: 800; }
        .neon-number { font-size: 2.5rem; font-weight: bold; color: #fff; line-height: 1; text-shadow: 0 0 15px rgba(0, 191, 255, 0.6); }
        .btn-custom { padding: 8px 10px; border-radius: 6px; font-weight: bold; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; border: none; cursor: pointer; color: #fff; transition: all 0.2s; font-size: .92rem; width: 100%; }
        .btn-custom:hover { transform: translateY(-2px); color: #fff; }
        .btn-assinar { background: linear-gradient(135deg, #28a745, #218838); box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3); }
        .btn-cancel { background: transparent; border: 1px solid #ff003c; color: #ff003c; margin-top: 10px; font-size: 0.95rem; width: auto; display: inline-flex; padding: 8px 20px; }
        html[data-app-theme="dark"] .btn-custom.btn-cancel { color: #ff879d; border-color: #ff879d; }
        html[data-app-theme="dark"] .btn-custom.btn-cancel:hover,
        html[data-app-theme="dark"] .btn-custom.btn-cancel:focus-visible { color: #ffc2cd; border-color: #ffc2cd; }
        .btn-cancel:hover { background: rgba(255, 0, 60, 0.1); color: #ff4d79; border-color: #ff4d79; }
        .plans-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 6px; }
        .plan-card { padding: 10px 12px; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between; text-align: center; transition: transform 0.2s; }
        .plan-card:hover { transform: translateY(-3px); }
        .plan-card h3 { margin: 0 0 4px; font-size: 1.15rem; }
        .plan-desc { color: #aaa; font-size: 0.8rem; margin-bottom: 5px; }
        .plan-limit { color: #fff; font-size: .95rem; margin-bottom: 5px; }
        .trial-alert { background: rgba(255, 193, 7, 0.15); border: 1px solid #ffc107; color: #ffc107; padding: 8px 12px; border-radius: 8px; font-weight: bold; text-align: center; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .btn-group { display: flex; gap: 15px; margin-top: 15px; }
        .btn-history { background-color: #17a2b8; flex: 1; }
        .btn-receipt { background-color: #6c757d; flex: 1; }
        .action-box { background: #252525; padding: 10px; border-radius: 8px; margin-top: 8px; border: 1px dashed #444; }
        .action-row { display: flex; gap: 10px; }
        .action-row form { flex: 1; }
        .btn-add-user { background: linear-gradient(135deg, #00bfff, #008cba); }
        .btn-remove-user { background: transparent; border: 1px solid #dc3545; color: #ff6b6b; }
        .btn-remove-user:hover { background: rgba(220, 53, 69, 0.1); }
        .footer-links { text-align: center; margin-top: 6px; display: flex; justify-content: center; gap: 25px; }
        .footer-link { color: #666; text-decoration: none; display: flex; align-items: center; gap: 8px; transition: color 0.3s; }
        .footer-link:hover { color: #fff; }
        
        /* Ajuste Visual de Preços */
        .preco-original { text-decoration: line-through; color: #777; font-size: 0.95rem; margin-right: 8px; font-weight: normal; }
        .preco-promocional { color: #2ecc71; font-weight: bold; font-size: 1.1rem; }
        .aviso-validade { font-size: 0.8rem; color: #ffc107; margin-top: 3px; display:block; text-align: right;}
        .valor-total-destaque { font-size: 1.3rem; font-weight: 800; color: #2ecc71; display: block; }

        /* Compacta os espaços ainda definidos por classes utilitárias do Bootstrap. */
        .container-assinatura .mt-4 { margin-top: .6rem !important; }
        .container-assinatura .btn-group { margin-top: 6px; }
        #planos-disponiveis > p { margin-bottom: 8px !important; }
        .grid-layout .card-custom { height: 100%; }

        /* Tema claro: substitui as cores dark fixas e também os estilos inline dos planos. */
        html[data-app-theme="light"] body,
        body.light-mode { background-color: #f4f7fb; color: #172033; }
        html[data-app-theme="light"] .card-custom,
        body.light-mode .card-custom {
            background-color: #fff;
            color: #172033;
            border-color: #d9e1eb !important;
            box-shadow: 0 8px 22px rgba(20, 41, 70, .08);
        }
        html[data-app-theme="light"] .card-title,
        body.light-mode .card-title { border-color: #d9e1eb; }
        html[data-app-theme="light"] :is(.label, .plan-desc, #planos-disponiveis > p),
        body.light-mode :is(.label, .plan-desc, #planos-disponiveis > p) { color: #667085 !important; }
        html[data-app-theme="light"] :is(.value, .plan-limit, .neon-number),
        body.light-mode :is(.value, .plan-limit, .neon-number) { color: #172033; }
        html[data-app-theme="light"] .info-row,
        body.light-mode .info-row { border-color: #e5eaf0; }
        html[data-app-theme="light"] .action-box,
        body.light-mode .action-box {
            background: #f8fafc;
            color: #344054;
            border-color: #cfd8e3;
        }
        html[data-app-theme="light"] .action-box > div,
        body.light-mode .action-box > div { color: #344054 !important; }
        html[data-app-theme="light"] .plan-card,
        body.light-mode .plan-card {
            background: #f8fafc !important;
            color: #172033;
            border-color: #d9e1eb !important;
        }
        html[data-app-theme="light"] .plan-card h3,
        body.light-mode .plan-card h3 { color: #172033 !important; }
        html[data-app-theme="light"] .plan-card div[style*="color: #fff"],
        body.light-mode .plan-card div[style*="color: #fff"] { color: #172033 !important; }
        html[data-app-theme="light"] .card-custom [style*="color: #aaa"],
        body.light-mode .card-custom [style*="color: #aaa"] { color: #667085 !important; }
        html[data-app-theme="light"] .plan-card[style*="rgba(0, 191, 255"],
        body.light-mode .plan-card[style*="rgba(0, 191, 255"] {
            background: #eaf7fd !important;
            border-color: #00a6df !important;
        }
        html[data-app-theme="light"] .btn-top-back,
        body.light-mode .btn-top-back {
            background: #fff;
            color: #344054;
            border-color: #cfd8e3;
        }

        @media (min-width: 900px) and (max-height: 850px) {
            .container-assinatura { margin-top: 8px; margin-bottom: 8px; }
            .card-custom { padding: 10px 14px; margin-bottom: 8px; }
            .card-title { margin-bottom: 6px; padding-bottom: 5px; }
            .info-row { margin-bottom: 4px; padding-bottom: 4px; }
            .grid-layout [style*="margin-bottom: 20px"] { margin-bottom: 8px !important; }
            .plan-card [style*="margin-bottom: 15px"] { margin-bottom: 6px !important; }
            .footer-links { margin-top: 2px; }
        }

        @media(max-width: 768px) {
            .container-assinatura { margin: 10px auto; padding: 0 10px; }
            .page-header { align-items: flex-start; }
            .page-header h2 { font-size: 1.25rem; }
            .plans-grid { grid-template-columns: 1fr; }
            .card-custom { padding: 14px; }
        }
        .container-assinatura { --assinatura-espaco-caixas: 20px; }
        .container-assinatura > .grid-layout {
            gap: var(--assinatura-espaco-caixas);
            margin-block-end: var(--assinatura-espaco-caixas);
        }
        .container-assinatura > .grid-layout > .card-custom { margin-bottom: 0; }
        .container-assinatura > .card-custom { margin-bottom: var(--assinatura-espaco-caixas); }
        .container-assinatura .plans-grid { gap: var(--assinatura-espaco-caixas); }

        @media (max-width: 768px) {
            .container-assinatura { --assinatura-espaco-caixas: 16px; }
        }
        html[data-app-theme="light"] .btn-plano-atual:disabled,
    body.light-mode .btn-plano-atual:disabled {
        color: #075985 !important;
        opacity: 1 !important;
    }
        html[data-app-theme="light"] .btn-custom.btn-alterar-plano,
    body.light-mode .btn-custom.btn-alterar-plano {
        color: #fff;
        background: #075985;
    }
    html[data-app-theme="light"] .btn-custom.btn-alterar-plano:is(:hover, :focus-visible),
    body.light-mode .btn-custom.btn-alterar-plano:is(:hover, :focus-visible) {
        color: #fff;
        background: #0c4a6e;
    }
        .financial-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .financial-actions .btn-custom { padding: 8px 10px; }
    .financial-actions .btn-history { background-color: #0e6879; }
    .financial-actions .btn-next-invoice { background-color: #075985; color: #fff; }
    @media (max-width: 575px) {
        .financial-actions { grid-template-columns: 1fr; gap: 8px; }
    }
    </style>
</head>
<body>

<div class="container-assinatura">
    <div class="page-header">
        <h2><i class="fa-solid fa-star"></i> Gestão da Assinatura</h2>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="perfil.php" class="btn-top-back"><i class="fa-solid fa-user"></i> Voltar para Perfil</a>
        </div>
    </div>

    <?php if ($is_trial && $dias_restantes_teste > 0): ?>
        <div class="trial-alert">
            <i class="fa-solid fa-clock fa-lg"></i>
            <div>PERÍODO DE TESTE: Restam <strong><?= (int)$dias_restantes_teste ?> dias</strong> gratuitos.</div>
            <a href="assinar.php?plano_selecionado=<?= htmlspecialchars($plano_db) ?>" class="btn-custom" style="background: #ffc107; color: #000; padding: 6px 15px; font-size: 0.9rem; width: auto; text-decoration:none;">
                Assinar Agora
            </a>
        </div>
    <?php endif; ?>

    <div class="grid-layout">
        <div class="card-custom">
            <div class="card-title"><i class="fa-solid fa-file-contract"></i> Detalhes do Plano</div>

            <div class="info-row">
                <span class="label">Plano Atual:</span>
                <span class="value" style="color: #00bfff; font-size: 1.1rem;"><?= htmlspecialchars($nome_plano_atual) ?></span>
            </div>

            <div class="info-row" style="align-items: flex-start;">
                <span class="label" style="margin-top: 5px;">Valor Mensal Fatura:</span>
                <div style="text-align: right;">
                    <span class="valor-total-destaque">
                        R$ <?= number_format($valor_fatura_total, 2, ',', '.') ?>
                    </span>
                    
                    <?php if ($extras_comprados > 0 && !$cupom_primeiro_pagamento): ?>
                        <div style="font-size: 0.8rem; color: #aaa; margin-top: 3px;">
                            (Plano: R$ <?= number_format($preco_final_atual, 2, ',', '.') ?> + Extras: R$ <?= number_format($custo_extras_total, 2, ',', '.') ?>)
                        </div>
                    <?php endif; ?>

                    <?php if ($tem_desconto): ?>
                        <div style="font-size: 0.8rem; color: #aaa; margin-top: 3px;">
                            <span class="preco-original" style="font-size: 0.8rem;">
                                Valor sem desconto: R$ <?= number_format($valor_original_fatura, 2, ',', '.') ?>
                            </span>
                            <?php if ($valor_desconto_cupom > 0): ?>
                                <span class="aviso-validade">
                                    Desconto: R$ <?= number_format($valor_desconto_cupom, 2, ',', '.') ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($cupom_primeiro_pagamento): ?>
                                <span class="aviso-validade">
                                    Cupom <?= htmlspecialchars($nome_cupom_ativo) ?> reservado para o primeiro pagamento após o teste
                                </span>
                            <?php elseif ($data_fim_promocao): ?>
                                <span class="aviso-validade">Promoção válida até <?= $data_fim_promocao ?></span>
                            <?php else: ?>
                                <span class="aviso-validade">Desconto aplicado</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-row">
                <span class="label">Limite Base:</span>
                <span class="value"><?= (int)$limite_base ?> Usuários</span>
            </div>

            <?php if ($codigo_indicacao_usado): ?>
                <div class="info-row" style="align-items: flex-start;">
                    <span class="label">Indicação utilizada:</span>
                    <span class="value" style="text-align: right;">
                        <?= htmlspecialchars($codigo_indicacao_usado) ?>
                        <?php if ($nome_indicador): ?>
                            <small style="display:block; color:#aaa; margin-top:3px;">
                                Indicado por <?= htmlspecialchars($nome_indicador) ?>
                            </small>
                        <?php endif; ?>
                        <span style="display:block; color:#2ecc71; font-weight:700; margin-top:6px;">
                            <i class="fa-solid fa-gift"></i>
                            Benefício: <?= (int)$meses_gratis_indicacao ?> meses grátis
                        </span>
                        <small style="display:block; color:#aaa; margin-top:3px; max-width:320px;">
                            <?php if ($beneficio_indicacao_utilizado && $fim_beneficio_indicacao): ?>
                                Benefício ativado na contratação. Primeira cobrança prevista para
                                <?= date('d/m/Y', strtotime($fim_beneficio_indicacao)) ?>.
                            <?php else: ?>
                                Exclusivo para cadastro com código de indicação. O período começa
                                após a contratação e a primeira cobrança ocorre somente ao final
                                dos <?= (int)$meses_gratis_indicacao ?> meses gratuitos.
                            <?php endif; ?>
                        </small>
                    </span>
                </div>
            <?php endif; ?>

            <div class="info-row">
                <span class="label">Status:</span>
                <span class="value">
                    <?php
                    if ($tipo_cancelamento) {
                        echo '<div style="margin-bottom:5px; color: #ffc107; font-weight:bold;"><i class="fa-solid fa-calendar-xmark"></i> Cancelamento Agendado</div>';
                        echo '<small style="color:#ccc; font-size: 0.85rem;">Encerra em: <strong style="color:#fff">' . (int)$dias_ate_renovacao . ' dias</strong></small>';
                    } else {
                        if ($status_assinatura === 'ativo') {
                            echo '<span class="status-ativo"><i class="fa-solid fa-check-circle"></i> Ativo</span>';
                        } elseif ($status_assinatura === 'trial') {
                            echo ($dias_restantes_teste > 0)
                                ? '<span style="color: #ffc107"><i class="fa-solid fa-flask"></i> Em Teste</span>'
                                : '<span class="status-inativo"><i class="fa-solid fa-times-circle"></i> Teste Expirado</span>';
                        } else {
                            echo '<span class="status-inativo"><i class="fa-solid fa-times-circle"></i> Inativo</span>';
                        }
                    }
                    ?>
                </span>
            </div>

            <?php if (!$is_trial && !empty($dados_assinatura['data_renovacao'])): ?>
                <div class="info-row" style="margin-top: 15px;">
                    <span class="label"><?= $tipo_cancelamento ? 'Encerramento em:' : 'Próxima Cobrança:' ?></span>
                    <span class="value">
                        <?= htmlspecialchars(date('d/m/Y', strtotime($dados_assinatura['data_renovacao']))) ?>
                        <br>
                        <small style="font-size:0.8rem; color:#888; font-weight:normal;">(Faltam <?= (int)$dias_ate_renovacao ?> dias)</small>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (in_array($status_assinatura, ['trial', 'ativo', 'padrao'], true)): ?>
                <div class="mt-4 text-center">
                    <button type="button" class="btn-custom btn-cancel" onclick="abrirModalCancelamento()">
                        <i class="fa-solid fa-pause"></i> Encerrar conta no fim do ciclo
                    </button>
                    <small style="display:block; margin-top:8px; color:#aaa;">Acesso mantido até o fim do ciclo vigente.</small>
                </div>
            <?php endif; ?>

            <?php if ($status_assinatura === 'inativo' || ($is_trial && $dias_restantes_teste <= 0)): ?>
                <div class="mt-4">
                    <a href="assinar.php?plano_selecionado=<?= htmlspecialchars($plano_db) ?>" class="btn-custom btn-assinar">
                        <i class="fa-solid fa-rotate-right"></i> Reativar Meu Plano
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-custom" style="border-color: rgba(0, 191, 255, 0.3);">
            <div class="card-title"><i class="fa-solid fa-users-gear"></i> Capacidade</div>

            <div style="text-align: center; margin-bottom: 20px;">
                <div class="neon-number"><?= (int)$limite_total ?></div>
                <div style="color: #aaa; font-size: 0.9rem;">Usuários Totais Permitidos</div>
            </div>

            <div class="info-row">
                <span class="label">Incluso no Plano:</span>
                <span class="value"><?= (int)$limite_base ?></span>
            </div>
            <div class="info-row">
                <span class="label">Extras Contratados:</span>
                <span class="value extra-highlight">+<?= (int)$extras_comprados ?></span>
            </div>

            <div class="action-box">
                <div style="font-size: 0.85rem; color: #ccc; margin-bottom: 10px; text-align: center;">
                    Gerencie seus slots extras (R$ <?= number_format($preco_por_extra, 2, ',', '.') ?>/mês cada).
                </div>
                <div class="action-row">
                    <form id="formAddExtra" action="../actions/comprar_extra_action.php" method="POST">
                        <input type="hidden" name="qtd_extra" value="1">
                        <button type="button" class="btn-custom btn-add-user" onclick="confirmarAdicao()">
                            <i class="fa-solid fa-plus"></i> Add
                        </button>
                    </form>

                    <?php if ($extras_comprados > 0): ?>
                    <form id="formRemoveExtra" action="../actions/remover_extra_action.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <?php
                            $dbUsuariosExtra = getTenantConnection();
                            $usuarioAtor = (int) ($_SESSION['usuario_id'] ?? 0);
                            $donoConta = (int) get_data_owner_id();
                            $usuariosRemoviveis = $dbUsuariosExtra ? $dbUsuariosExtra->query("SELECT id,nome FROM usuarios WHERE deleted_at IS NULL AND status='ativo' AND COALESCE(is_master,0)=0 AND id NOT IN ($usuarioAtor,$donoConta) ORDER BY nome") : null;
                            ?>
                            <label for="usuario-extra-remover">Usuário da vaga</label>
                            <select id="usuario-extra-remover" name="usuario_remover">
                                <option value="0">Vaga sem usuário</option>
                                <?php while ($usuarioExtra = $usuariosRemoviveis?->fetch_assoc()): ?>
                                    <option value="<?= (int) $usuarioExtra['id'] ?>"><?= htmlspecialchars($usuarioExtra['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endwhile; ?>
                            </select>
                        <button type="button" class="btn-custom btn-remove-user" onclick="confirmarRemocao()">
                            <i class="fa-solid fa-minus"></i> Remover
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom" id="planos-disponiveis">
        <div class="card-title"><i class="fa-solid fa-layer-group"></i> Planos Disponíveis</div>
        <p style="color: #aaa; font-size: 0.9rem; margin-bottom: 20px;">Escolha o plano ideal para o seu negócio. A alteração é gerenciada na próxima etapa.</p>

        <div class="plans-grid">
            <?php foreach ($mapa_planos as $slug => $plano):
                $e_plano_atual = ($slug === $plano_db);
                $ativo = ($e_plano_atual && $status_assinatura === 'ativo');

                $border = $e_plano_atual ? '2px solid #00bfff' : '1px solid #333';
                $bg = $e_plano_atual ? 'rgba(0, 191, 255, 0.08)' : '#252525';
                $color_title = $e_plano_atual ? '#00bfff' : '#fff';
            ?>
                <div class="plan-card" style="background: <?= $bg ?>; border: <?= $border ?>;">
                    <div>
                        <h3 style="color: <?= $color_title ?>"><?= htmlspecialchars($plano['nome']) ?></h3>
                        <p class="plan-desc"><?= htmlspecialchars($plano['desc']) ?></p>
                        <div class="plan-limit"><i class="fa-solid fa-user-group"></i> <?= (int)$plano['base'] ?> Usuários</div>
                        <div style="font-size: 1.2rem; font-weight: bold; color: #fff; margin-bottom: 15px;">
                            R$ <?= number_format($plano['preco'], 2, ',', '.') ?> <small style="font-size: 0.8rem; font-weight: normal;">/mês</small>
                        </div>
                    </div>

                    <?php if ($ativo): ?>
                        <button disabled class="btn-custom btn-plano-atual" style="background: transparent; border: 1px solid #00bfff; color: #00bfff; cursor: default; opacity: 0.8;">
                            <i class="fa-solid fa-check"></i> Plano Atual
                        </button>
                    <?php elseif ($status_assinatura === 'ativo' && !empty($dados_assinatura['id_assinatura_mp'])): ?>
                        <a class="btn-custom btn-alterar-plano" href="simular_troca_plano.php?plano=<?= rawurlencode($slug) ?>">Alterar plano</a>
                    <?php else: ?>
                        <a href="assinar.php?plano_selecionado=<?= htmlspecialchars($slug) ?>" class="btn-custom" style="background: #00bfff;">
                            <?= $e_plano_atual ? 'Reativar Plano' : 'Selecionar Plano' ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card-custom">
        <div class="card-title"><i class="fa-solid fa-wallet"></i> Financeiro</div>
        <div class="financial-actions">
            <a href="historico_pagamento.php" class="btn-custom btn-history"><i class="fa-solid fa-list-ul"></i> Histórico</a>
            <a href="historico_pagamento.php" class="btn-custom btn-receipt"><i class="fa-solid fa-file-invoice-dollar"></i> Comprovantes</a>
            <a href="proximas_faturas.php" class="btn-custom btn-next-invoice"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Próximas faturas</a>
        </div>
    </div>

</div>

<div class="modal fade" id="modalCancelarAssinatura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background-color: #2c2c2c; color: #eee; border: 1px solid #444;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title" style="color: #ffb020;"><i class="fa-solid fa-pause"></i> Encerrar conta no fim do ciclo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Confirme a pausa temporária da sua conta:</p>
                <form id="formCancelarAssinatura" action="../actions/solicitar_cancelamento.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-check mb-3 p-3" style="background: rgba(255,255,255,0.05); border-radius: 5px;">
                        <input class="form-check-input" type="radio" name="opcao_cancelamento" id="opcao1" value="desativar" required>
                        <label class="form-check-label" for="opcao1">
                            <strong>Confirmo o encerramento e a exclusão permanente ao fim do ciclo.</strong><br>
                            <small style="color: #aaa;">O acesso continua até o fim do ciclo. A próxima cobrança fica pausada enquanto o cancelamento estiver pendente.</small>
                        </label>
                    </div>
                    <div class="mt-3 p-3" style="background: rgba(255,193,7,.1); border:1px solid rgba(255,193,7,.35); border-radius:5px;"><small>Para reativar a conta antes do encerramento, entre em contato com o suporte.</small></div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #444;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" form="formCancelarAssinatura" class="btn btn-danger">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function abrirModalCancelamento() {
    var myModal = new bootstrap.Modal(document.getElementById('modalCancelarAssinatura'));
    myModal.show();
}

function confirmarAdicao() {
    <?php if ($trocaPlanoPendente): ?>
    Swal.fire({
        title: 'Troca de plano em andamento',
        text: 'Sua troca de plano ainda está em andamento. Aguarde o fechamento do ciclo atual da assinatura para contratar mais usuários.',
        icon: 'info',
        confirmButtonText: 'Entendi',
        confirmButtonColor: '#00bfff',
        background: '#1e1e1e', color: '#eee'
    });
    <?php else: ?>
    Swal.fire({
        title: 'Adicionar Usuário?',
        text: "Adicional de R$ <?= number_format($preco_por_extra, 2, ',', '.') ?>/mês na fatura.",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#00bfff',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sim, adicionar',
        background: '#1e1e1e', color: '#eee'
    }).then((result) => {
        if (result.isConfirmed) document.getElementById('formAddExtra').submit();
    });
    <?php endif; ?>
}

function confirmarRemocao() {
    Swal.fire({
        title: 'Remover Vaga?',
        text: "A vaga fica disponível até o fim do ciclo. A próxima cobrança será reduzida, sem estorno. Se você selecionar um usuário, o acesso dele será encerrado no fim do ciclo.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, remover',
        background: '#1e1e1e', color: '#eee'
    }).then((result) => {
        if (result.isConfirmed && document.getElementById('formRemoveExtra')) document.getElementById('formRemoveExtra').submit();
    });
}
</script>

<?php include('../includes/footer.php'); ?>
</body>
</html>
