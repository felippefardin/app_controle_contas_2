<?php
require_once '../includes/session_init.php';
require_once '../includes/check_plan.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils para o Flash Message e CSS
require_once '../includes/caixa_service.php';

// ----------------------------
// 1. VERIFICA SE USUÃRIO ESTÃ LOGADO
// ----------------------------
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) { 
    header('Location: login.php');
    exit;
}

$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao obter a conexão com o banco de dados do cliente.");
}

// ----------------------------
// 2. DADOS DO USUÃRIO
// ----------------------------
$id_usuario = get_data_owner_id();
$perfil     = $_SESSION['nivel_acesso'];
$sessao_caixa = caixa_buscar_sessao($conn, $id_usuario, date('Y-m-d'));
$caixa_aberto = $sessao_caixa && $sessao_caixa['status'] === 'aberto';
$ambiente_nfce = 2;
$stmtAmbienteNfce = $conn->prepare("SELECT valor FROM configuracoes_tenant WHERE chave = 'ambiente' LIMIT 1");
if ($stmtAmbienteNfce && $stmtAmbienteNfce->execute()) {
    $ambienteSalvo = $stmtAmbienteNfce->get_result()->fetch_column();
    if (in_array((int) $ambienteSalvo, [1, 2], true)) {
        $ambiente_nfce = (int) $ambienteSalvo;
    }
}
$stmtAmbienteNfce?->close();
$token_nfce = $_SESSION['token_validacao_nfce'] ??= bin2hex(random_bytes(32));

// ----------------------------
// 3. BLOCO AJAX PARA BUSCAS
// ----------------------------
if (isset($_GET['action'])) {
    $term = "%" . ($_GET['term'] ?? '') . "%";
    $response = [];

    if ($_GET['action'] === 'search_barcode') {
        $codigo = trim((string)($_GET['codigo'] ?? ''));
        header('Content-Type: application/json; charset=UTF-8');
        if ($codigo === '') {
            echo json_encode(['found' => false, 'message' => 'Informe ou leia um codigo de barras.']);
            exit;
        }
        $stmt = $conn->prepare("SELECT id, codigo, nome, preco_venda, quantidade_estoque
                                FROM produtos
                                WHERE codigo = ? AND id_usuario = ? AND deleted_at IS NULL
                                LIMIT 1");
        $stmt->bind_param('si', $codigo, $id_usuario);
        $stmt->execute();
        $produto = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$produto) {
            echo json_encode(['found' => false, 'message' => 'Codigo nao cadastrado para esta empresa.']);
            exit;
        }
        echo json_encode(['found' => true, 'product' => [
            'id' => (int)$produto['id'],
            'codigo' => $produto['codigo'],
            'text' => $produto['nome'] . ' (Estoque: ' . $produto['quantidade_estoque'] . ')',
            'preco_venda' => (float)$produto['preco_venda'],
            'estoque' => (int)$produto['quantidade_estoque']
        ]]);
        exit;
    }

    if ($_GET['action'] === 'search_clientes') {
        $sql = "SELECT id, nome 
                FROM pessoas_fornecedores 
                WHERE tipo = 'pessoa' AND nome LIKE ? AND id_usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $term, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response[] = ['id' => $row['id'], 'text' => $row['nome']];
        }
    }

    if ($_GET['action'] === 'search_produtos') {
        $sql = "SELECT id, codigo, nome, preco_venda, quantidade_estoque 
                FROM produtos 
                WHERE (nome LIKE ? OR codigo LIKE ?) AND id_usuario = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $term, $term, $id_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response[] = [
                'id'          => $row['id'],
                'text'        => ($row['codigo'] ? $row['codigo'] . ' - ' : '') . $row['nome'] . " (Estoque: " . $row['quantidade_estoque'] . ")",
                'preco_venda' => $row['preco_venda'],
                'estoque'     => $row['quantidade_estoque']
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['results' => $response]);
    exit;
}

// ----------------------------
// 4. INCLUDES DE CABEÇALHO
// ----------------------------
include('../includes/header.php');

// ✅ EXIBE O FLASH MESSAGE DO PHP (Se houver msg na sessão)
display_flash_message();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caixa de Vendas (PDV)</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />  
</head>
<body>
    <style>
/* =========================================
   PADRÃO GERAL
========================================= */
body {
    background-color: #121212;
    color: #eee;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

/* MODIFICADO PARA FULL DESKTOP E RESPONSIVIDADE */
.container {
    background-color: #1e1e1e;
    padding: 25px;
    border-radius: 10px;
    margin: 30px auto;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.4);
    /* Full Desktop: ocupa quase toda a largura, mas mantém margem */
    max-width: 98%; 
    width: 100%;
}

/* TÃTULOS */
h1, h2 {
    color: #00bfff;
    border-bottom: 2px solid #00bfff;
    padding-bottom: 8px;
    margin-bottom: 20px;
    font-weight: 600;
}

/* LABELS */
label {
    font-weight: bold;
    color: #ccc;
}

/* =========================================
   CAMPOS DE FORMULÃRIO
========================================= */
.form-control,
.select2-container .select2-selection--single {
    background-color: #2a2a2a !important;
    color: #eee !important;
    border: 1px solid #444 !important;
    border-radius: 6px !important;
    height: calc(1.5em + .75rem + 2px) !important;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: #00bfff !important;
    box-shadow: 0 0 5px #00bfff33 !important;
}

/* SELECT2 */
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #eee !important;
    line-height: 38px !important;
}

.select2-dropdown {
    background-color: #333 !important;
    border: 1px solid #444 !important;
}

.select2-results__option {
    color: #eee !important;
}

.select2-results__option--highlighted {
    background-color: #00bfff !important;
}

/* =========================================
   BOTÕES
========================================= */
.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}
.btn-primary:hover {
    background-color: #0069d9;
    border-color: #0062cc;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}
.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}
.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

/* =========================================
   TABELAS
========================================= */
.table-responsive {
    background-color: #1a1a1a;
    border-radius: 8px;
    overflow-x: auto;
    box-shadow: inset 0 0 5px rgba(255, 255, 255, 0.05);
}

.table {
    color: #ddd;
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap; /* Garante que não quebre em mobile */
}

.table thead th {
    background-color: #00bfff;
    color: #fff;
    text-align: center;
    padding: 10px;
    border: none;
    font-weight: 600;
}

.table tbody tr {
    background-color: #2a2a2a;
    transition: background-color 0.2s, transform 0.1s;
}

.table tbody tr:hover {
    background-color: #333;
    transform: scale(1.00); /* Retirado scale no mobile para evitar overflow lateral */
}
@media (min-width: 992px) {
    .table tbody tr:hover { transform: scale(1.01); }
}

.table td {
    vertical-align: middle;
    text-align: center;
    border-top: 1px solid #444;
    padding: 10px;
}

.table input.form-control {
    background-color: #2b2b2b;
    border: 1px solid #444;
    color: #fff;
    text-align: center;
    padding: 5px;
    border-radius: 5px;
    min-width: 60px;
}

/* =========================================
   TOTAL
========================================= */
.total-venda {
    font-size: 1.8rem;
    font-weight: bold;
    color: #28a745;
    text-shadow: 0 0 8px #28a74555;
}

/* =========================================
   ALERTAS E NOTIFICAÇÕES (CORREÇÃO)
========================================= */
.alert {
    border-radius: 6px;
    font-weight: 500;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

/* CSS para o Flash Message JS (Correção) */
.alert-overlay {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999; /* Z-Index alto para ficar acima de modals */
    pointer-events: none;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.alert-box {
    background-color: #333;
    color: white;
    padding: 15px 20px;
    border-radius: 5px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.6);
    pointer-events: auto;
    display: flex;
    align-items: flex-start;
    min-width: 300px;
    border-left: 5px solid #007bff;
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

.alert-box.alert-success { border-left-color: #28a745; }
.alert-box.alert-danger, .alert-box.alert-error { border-left-color: #dc3545; }
.alert-box.alert-warning { border-left-color: #ffc107; }
.alert-box.alert-info { border-left-color: #17a2b8; }

.alert-msg { flex-grow: 1; margin-left: 15px; font-size: 14px; margin-top: 2px; }
.alert-box i { font-size: 24px !important; margin: 0 !important; display: inline-block !important; }
.btn-fechar-alert { background: none; border: none; color: #aaa; cursor: pointer; font-weight: bold; margin-left: 10px; font-size: 16px; }
.btn-fechar-alert:hover { color: white; }


/* =========================================
   CARDS
========================================= */
.card.bg-dark {
    background-color: #1a1a1a !important;
    border: 1px solid #333;
}

.card-header {
    background-color: #00bfff22;
    border-bottom: 1px solid #00bfff55;
    color: #00bfff;
    font-weight: 600;
}

/* =========================================
   SCROLL
========================================= */
::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}
::-webkit-scrollbar-thumb {
    background-color: #555;
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #00bfff;
}

/* =========================================
   META DE VENDAS
========================================= */
.meta-widget {
    background-color: #2a2a2a;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 25px;
    border: 1px solid #333;
}

.meta-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-size: 1.1rem;
    flex-wrap: wrap; /* Para mobile */
}

.meta-header-titulo {
    color: #00bfff;
    font-weight: 600;
}

.meta-header-valores {
    color: #eee;
    font-weight: bold;
}

.meta-header-editar {
    color: #00bfff;
    cursor: pointer;
    transition: color 0.2s;
    font-size: 0.9rem;
}

.meta-header-editar:hover {
    color: #fff;
}

.meta-actions { display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
.meta-status { margin-top:8px; color:#bbb; font-size:.9rem; text-align:center; }
.meta-status.atingida { color:#69db7c; font-weight:700; }
.meta-history-table { width:100%; color:#eee; }
.meta-history-table th,.meta-history-table td { padding:9px; border-bottom:1px solid #444; white-space:nowrap; }
.meta-history-table th { color:#00bfff; }

.progress {
    height: 25px;
    background-color: #1a1a1a;
    border-radius: 5px;
    border: 1px solid #444;
    padding: 2px;
}

.progress-bar {
    background-color: #28a745 !important;
    color: #fff;
    font-weight: bold;
    line-height: 21px;
    text-align: center;
    overflow: visible;
    text-shadow: 0 0 2px #000;
    transition: width 0.6s ease;
}

/* Meta de vendas no modo claro */
html[data-app-theme="light"] body.light-mode .meta-widget {
    background-color: #f8fafc !important;
    color: #172033 !important;
    border-color: #d9e1eb !important;
    box-shadow: 0 6px 18px rgba(20, 41, 70, 0.08);
}

html[data-app-theme="light"] body.light-mode .meta-header-titulo,
html[data-app-theme="light"] body.light-mode .meta-header-editar {
    color: #075dd8 !important;
}

html[data-app-theme="light"] body.light-mode .meta-header-editar:hover {
    color: #003f9b !important;
}

html[data-app-theme="light"] body.light-mode .meta-header-valores {
    color: #172033 !important;
}

html[data-app-theme="light"] body.light-mode .meta-status {
    color: #5d6878 !important;
}

html[data-app-theme="light"] body.light-mode .meta-status.atingida {
    color: #15803d !important;
}

html[data-app-theme="light"] body.light-mode .meta-widget .progress {
    background-color: #e7edf5 !important;
    border-color: #cbd5e1 !important;
}

/* =========================================
   MEDIA QUERIES (RESPONSIVIDADE)
========================================= */
@media (max-width: 768px) {
    /* Ajuste do Container no Mobile */
    .container {
        padding: 15px;
        margin: 10px auto;
        width: 100%;
        max-width: 100%;
    }

    /* Cabeçalho flexível */
    .d-flex.justify-content-between {
        flex-direction: column;
        align-items: stretch !important;
        text-align: center;
    }
    
    .d-flex.justify-content-between h1 {
        font-size: 1.5rem;
        margin-bottom: 15px;
    }

    .d-flex.justify-content-between .btn {
        width: 100%;
        margin-bottom: 10px;
    }

    /* Botão adicionar produto */
    #add-produto {
        margin-top: 15px;
    }

    /* Botões de Ação (Recibo e NFe) */
    #btn-recibo, #btn-nfe {
        width: 100%;
        display: block;
        margin: 0 0 10px 0 !important;
    }
    
    .text-right {
        text-align: center !important;
    }
    
    .total-venda {
        font-size: 1.5rem;
    }
}

/* Select2 insere as listas de clientes e produtos diretamente no body.
   Estas regras garantem contraste correto quando o tema claro está ativo. */
html[data-app-theme="light"] .select2-container--default .select2-selection--single,
html[data-app-theme="light"] .select2-container--default .select2-search--dropdown .select2-search__field,
html[data-app-theme="light"] .select2-container--default .select2-dropdown {
    background-color: #ffffff !important;
    color: #172033 !important;
    border-color: #b8c5d6 !important;
}

html[data-app-theme="light"] .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #172033 !important;
}

html[data-app-theme="light"] .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #667085 !important;
}

html[data-app-theme="light"] .select2-container--default .select2-results__option {
    background-color: #ffffff !important;
    color: #172033 !important;
}

html[data-app-theme="light"] .select2-container--default .select2-results__option--selected {
    background-color: #e6f0ff !important;
    color: #12345b !important;
}

html[data-app-theme="light"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #075dd8 !important;
    color: #ffffff !important;
}

html[data-app-theme="light"] .select2-container--default .select2-results__message,
html[data-app-theme="light"] .select2-container--default .select2-results__option[aria-disabled="true"] {
    color: #667085 !important;
}

/* =========================================
   CONTRASTE COMPLETO POR TEMA
========================================= */
html[data-app-theme="light"] body.light-mode {
    background-color: #eef3f9 !important;
    color: #172033 !important;
}

html[data-app-theme="light"] body.light-mode > .container {
    background-color: #ffffff !important;
    color: #172033 !important;
    border: 1px solid #d5deea !important;
    box-shadow: 0 10px 28px rgba(20, 41, 70, .12) !important;
}

html[data-app-theme="light"] body.light-mode :is(h1, h2) {
    color: #075dad !important;
    border-bottom-color: #1597d4 !important;
}

html[data-app-theme="light"] body.light-mode :is(label, legend, .total-venda, .meta-header-valores) {
    color: #172033 !important;
}

html[data-app-theme="light"] body.light-mode :is(small, .text-muted, .form-text) {
    color: #556274 !important;
}

html[data-app-theme="light"] body.light-mode :is(.form-control, select, textarea) {
    background-color: #ffffff !important;
    color: #172033 !important;
    border-color: #aebccd !important;
}

html[data-app-theme="light"] body.light-mode :is(.form-control, textarea)::placeholder {
    color: #667085 !important;
    opacity: 1 !important;
}

html[data-app-theme="light"] body.light-mode select option {
    background-color: #ffffff !important;
    color: #172033 !important;
}

html[data-app-theme="light"] body.light-mode :is(.card.bg-dark, .modal-content) {
    background-color: #ffffff !important;
    color: #172033 !important;
    border-color: #d5deea !important;
}

html[data-app-theme="light"] body.light-mode :is(.card-header, .modal-header, .modal-footer) {
    background-color: #eaf5fc !important;
    color: #075dad !important;
    border-color: #c8d9e8 !important;
}

html[data-app-theme="light"] body.light-mode .modal-content .close {
    color: #172033 !important;
    opacity: .8 !important;
}

html[data-app-theme="light"] body.light-mode .table-responsive {
    background-color: #ffffff !important;
    border: 1px solid #d5deea !important;
}

html[data-app-theme="light"] body.light-mode .table tbody tr,
html[data-app-theme="light"] body.light-mode .table tbody td {
    background-color: #ffffff !important;
    color: #172033 !important;
    border-color: #d5deea !important;
}

html[data-app-theme="light"] body.light-mode .table tbody tr:hover,
html[data-app-theme="light"] body.light-mode .table tbody tr:hover td {
    background-color: #edf5ff !important;
    color: #102a4c !important;
}

html[data-app-theme="light"] body.light-mode .alert-box {
    background-color: #ffffff !important;
    color: #172033 !important;
    border: 1px solid #d5deea;
    box-shadow: 0 8px 24px rgba(20, 41, 70, .18);
}

html[data-app-theme="light"] body.light-mode .btn-fechar-alert {
    color: #344054 !important;
}

html[data-app-theme="light"] body.light-mode .alert-secondary {
    background-color: #eef2f7 !important;
    color: #243247 !important;
    border-color: #cbd5e1 !important;
}

html[data-app-theme="light"] body.light-mode .alert-warning {
    background-color: #fff4ce !important;
    color: #5b3a00 !important;
    border-color: #e4b84a !important;
}

html[data-app-theme="light"] .caixa-status-alert {
    background-color: #d9f2e2 !important;
    color: #14532d !important;
    border-color: #70bd89 !important;
}

html[data-app-theme="light"] .caixa-status-alert :is(.caixa-status-texto, .caixa-saldo-inicial, i) {
    color: #14532d !important;
}

html[data-app-theme="light"] .caixa-status-alert .btn-movimentacoes {
    background-color: #166534 !important;
    color: #ffffff !important;
    border-color: #166534 !important;
    font-weight: 600;
}

html[data-app-theme="light"] .caixa-status-alert .btn-movimentacoes:hover,
html[data-app-theme="light"] .caixa-status-alert .btn-movimentacoes:focus {
    background-color: #0f4d27 !important;
    color: #ffffff !important;
    border-color: #0f4d27 !important;
}

html[data-app-theme="dark"] body.dark-mode {
    background-color: #0f1115 !important;
    color: #edf4fc !important;
}

html[data-app-theme="dark"] body.dark-mode > .container {
    background-color: #191c22 !important;
    color: #edf4fc !important;
    border: 1px solid #374151 !important;
}

html[data-app-theme="dark"] body.dark-mode :is(h1, h2) {
    color: #4fd1ff !important;
    border-bottom-color: #2fbce9 !important;
}

html[data-app-theme="dark"] body.dark-mode :is(label, legend, .total-venda, .meta-header-valores) {
    color: #edf4fc !important;
}

html[data-app-theme="dark"] body.dark-mode :is(small, .text-muted, .form-text, .meta-status) {
    color: #b8c5d6 !important;
}

html[data-app-theme="dark"] body.dark-mode :is(.form-control, select, textarea),
html[data-app-theme="dark"] .select2-container--default :is(.select2-selection--single, .select2-search__field, .select2-dropdown) {
    background-color: #252a33 !important;
    color: #f1f5f9 !important;
    border-color: #526071 !important;
}

html[data-app-theme="dark"] body.dark-mode :is(.form-control, textarea)::placeholder,
html[data-app-theme="dark"] .select2-container--default .select2-selection__placeholder {
    color: #aab6c5 !important;
    opacity: 1 !important;
}

html[data-app-theme="dark"] body.dark-mode select option,
html[data-app-theme="dark"] .select2-container--default .select2-results__option {
    background-color: #252a33 !important;
    color: #f1f5f9 !important;
}

html[data-app-theme="dark"] .select2-container--default .select2-selection__rendered {
    color: #f1f5f9 !important;
}

html[data-app-theme="dark"] .select2-container--default .select2-results__option--selected {
    background-color: #334155 !important;
    color: #ffffff !important;
}

html[data-app-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #087cba !important;
    color: #ffffff !important;
}

html[data-app-theme="dark"] body.dark-mode :is(.card.bg-dark, .modal-content, .table-responsive) {
    background-color: #191c22 !important;
    color: #edf4fc !important;
    border-color: #374151 !important;
}

html[data-app-theme="dark"] body.dark-mode :is(.card-header, .modal-header, .modal-footer) {
    background-color: #202b38 !important;
    color: #4fd1ff !important;
    border-color: #405065 !important;
}

html[data-app-theme="dark"] body.dark-mode .modal-content .close {
    color: #ffffff !important;
    opacity: .9 !important;
}

html[data-app-theme="dark"] body.dark-mode .table tbody tr,
html[data-app-theme="dark"] body.dark-mode .table tbody td {
    background-color: #232831 !important;
    color: #edf4fc !important;
    border-color: #455164 !important;
}

html[data-app-theme="dark"] body.dark-mode .table tbody tr:hover,
html[data-app-theme="dark"] body.dark-mode .table tbody tr:hover td {
    background-color: #2d3745 !important;
    color: #ffffff !important;
}

html[data-app-theme="dark"] body.dark-mode .alert-box {
    background-color: #20252e !important;
    color: #f1f5f9 !important;
    border-top: 1px solid #455164;
    border-right: 1px solid #455164;
    border-bottom: 1px solid #455164;
}

html[data-app-theme="dark"] .caixa-status-alert {
    background-color: #173d29 !important;
    color: #e1f8e9 !important;
    border-color: #3b8f5b !important;
}

html[data-app-theme="dark"] .caixa-status-alert :is(.caixa-status-texto, .caixa-saldo-inicial, i) {
    color: #e1f8e9 !important;
}

html[data-app-theme="dark"] .caixa-status-alert .btn-movimentacoes {
    background-color: #2f9e5b !important;
    color: #ffffff !important;
    border-color: #58c77e !important;
    font-weight: 600;
}

html[data-app-theme="dark"] .caixa-status-alert .btn-movimentacoes:hover,
html[data-app-theme="dark"] .caixa-status-alert .btn-movimentacoes:focus {
    background-color: #3abf6d !important;
    color: #071d10 !important;
    border-color: #70df98 !important;
}

html[data-app-theme="dark"] body.dark-mode .btn-fechar-alert {
    color: #d7e0ea !important;
}

html[data-app-theme="dark"] body.dark-mode :is(.btn-warning, .btn-info) {
    color: #111827 !important;
}
</style>
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-cash-register"></i> Caixa de Vendas (PDV)</h1>
        <a href="vendas_periodo.php" class="btn btn-secondary">Validar NFC-e de venda registrada</a>
        <a href="fechamento_caixa.php" class="btn btn-info">
            <i class="fas fa-print"></i> Fechamento de Caixa
        </a>
    </div>

    <div class="meta-widget">
        <div class="meta-header">
            <span class="meta-header-titulo"><i class="fas fa-bullseye"></i> Meta de Vendas — ciclo atual</span>
            <div class="meta-actions">
                <span class="meta-header-editar" data-toggle="modal" data-target="#modalHistoricoMetas" title="Consultar metas anteriores"><i class="fas fa-history"></i> Histórico</span>
                <?php if ($perfil === 'admin' || $perfil === 'proprietario'): ?>
                    <span id="btn-editar-meta" class="meta-header-editar" data-toggle="modal" data-target="#modalMetaVendas" title="Criar uma nova meta e arquivar a atual">
                        <i class="fas fa-plus"></i> Nova meta
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="progress">
            <div id="meta-progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
        </div>
        <div class="meta-header-valores text-center mt-2">
            <span id="meta-valores-texto">Atual: R$ 0,00 / Meta: R$ 0,00</span>
        </div>
        <div id="meta-status-texto" class="meta-status">Nenhuma meta ativa.</div>
    </div>

    <div id="alert-container"></div>

    <?php if ($caixa_aberto): ?>
        <div class="alert alert-success caixa-status-alert d-flex justify-content-between align-items-center">
            <span class="caixa-status-texto"><i class="fas fa-lock-open"></i> Caixa aberto hoje com saldo inicial de
                <strong class="caixa-saldo-inicial">R$ <?= number_format((float)$sessao_caixa['valor_abertura'], 2, ',', '.') ?></strong>.
            </span>
            <a class="btn btn-sm btn-outline-light btn-movimentacoes" href="lancamento_caixa.php">Ver movimentações</a>
        </div>
    <?php elseif ($sessao_caixa && $sessao_caixa['status'] === 'fechado'): ?>
        <div class="alert alert-secondary">
            <i class="fas fa-lock"></i> O caixa de hoje já foi fechado. Um novo caixa poderá ser aberto amanhã.
        </div>
    <?php else: ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><i class="fas fa-exclamation-triangle"></i> Abra o caixa para começar as vendas de hoje.</span>
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalAbrirCaixa">
                <i class="fas fa-lock-open"></i> Abrir caixa
            </button>
        </div>
    <?php endif; ?>

    <form id="form-venda"<?= $caixa_aberto ? '' : ' style="opacity:.45;pointer-events:none"' ?>>

        <div class="form-group">
            <label for="cliente_id">Cliente</label>
            <div class="input-group">
                <select id="cliente_id" name="cliente_id" class="form-control" required></select>
                <div class="input-group-append">
                    <button class="btn btn-success" type="button" data-toggle="modal" data-target="#modalNovoCliente" title="Novo Cliente">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card bg-dark text-white mb-4">
            <div class="card-header"><h2>Adicionar Produtos</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label for="barcode_input"><i class="fas fa-barcode"></i> Código de barras / SKU (opcional)</label>
                    <div class="input-group">
                        <input type="text" id="barcode_input" class="form-control"
                               placeholder="Leia com o aparelho ou digite o código e pressione Enter"
                               autocomplete="off">
                        <div class="input-group-append">
                            <button type="button" id="buscar-barcode" class="btn btn-info" title="Buscar código">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">Leitores USB e Bluetooth funcionam automaticamente neste campo.</small>
                </div>
                <div class="form-row align-items-end">
                    <div class="form-group col-md-8">
                        <label for="produto_select">Produto</label>
                        <select id="produto_select" class="form-control"></select>
                    </div>
                    <div class="form-group col-md-4">
                        <button type="button" id="add-produto" class="btn btn-success btn-block">
                            <i class="fas fa-plus"></i> Adicionar à Venda
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <h2><i class="fas fa-shopping-cart"></i> Itens da Venda</h2>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th style="width: 120px;">Quantidade</th>
                        <th style="width: 150px;">Preço Unit.</th>
                        <th style="width: 150px;">Subtotal</th>
                        <th style="width: 100px;">Ação</th>
                    </tr>
                </thead>
                <tbody id="venda-items"></tbody>
            </table>
        </div>

        <div class="text-right mt-3">
            <h3 class="total-venda">Total: R$ <span id="total-geral">0.00</span></h3>
        </div>

        <div class="form-row mt-4">
            <div class="form-group col-md-4 col-12">
                <label for="forma_pagamento">Forma de Pagamento</label>
                <select id="forma_pagamento" name="forma_pagamento" class="form-control" required>
                    <option value="dinheiro" selected>Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="cartao_debito">Cartão de Débito</option>
                    <option value="cartao_credito">Cartão de Crédito</option>
                    <option value="receber">A Receber (A Prazo)</option>
                </select>
            </div>
            <div class="form-group col-md-3 col-12">
                <label for="desconto">Desconto (R$)</label>
                <input type="text" id="desconto" name="desconto" class="form-control" placeholder="0.00">
            </div>
        </div>

        <div class="mt-4">
            <button type="button" id="btn-recibo" class="btn btn-primary btn-lg mr-2">
                <i class="fas fa-receipt"></i> Finalizar e Gerar Recibo
            </button>
            <?php if (is_tenant_admin()): ?>
                <button type="button" id="btn-nfe" class="btn btn-warning btn-lg">
                    <i class="fas fa-file-invoice"></i>
                    Finalizar e Enviar NFC-e à SEFAZ (<?= $ambiente_nfce === 1 ? 'Produção' : 'Homologação' ?>)
                </button>
                <small class="d-block mt-2 text-muted">
                    A venda será registrada e a NFC-e será transmitida à SEFAZ no ambiente fiscal configurado.
                </small>
            <?php endif; ?>
        </div>

    </form>

    <div class="modal fade" id="modalAbrirCaixa" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content bg-dark text-light">
                <form method="post" action="../actions/abrir_caixa.php">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-cash-register"></i> Abrir caixa de hoje</h5>
                        <button type="button" class="close text-light" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <label>Dinheiro inicial em caixa (opcional)</label>
                            <input type="text" name="valor_abertura" class="form-control" value="0,00" inputmode="decimal">
                            <small class="text-muted">Pode abrir com zero. Se houver troco ou fundo de caixa, informe o valor.</small>
                        </div>
                        <div class="form-group">
                            <label>Observação (opcional)</label>
                            <input type="text" name="observacao" class="form-control" maxlength="255" placeholder="Ex.: fundo de troco">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar abertura</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($perfil === 'admin' || $perfil === 'proprietario'): ?>
<div class="modal fade" id="modalMetaVendas" tabindex="-1" role="dialog" aria-labelledby="modalMetaLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="background-color: #222; color: #eee;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title" id="modalMetaLabel">Criar novo ciclo de meta</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="form-meta-vendas">
                    <div class="form-group">
                        <label for="valor_meta_input">Valor da Meta (R$)</label>
                        <input type="text" class="form-control" id="valor_meta_input" name="meta" placeholder="Ex: 10000,00" style="background-color: #333; color: #eee; border: 1px solid #555;">
                    </div>
                    <div class="alert alert-info" style="background:#17394a;color:#d9f3ff;border-color:#24647f">Ao criar uma nova meta, a atual será encerrada e guardada no histórico com o total alcançado. O novo ciclo começa imediatamente.</div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #444;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-salvar-meta" class="btn btn-primary">Salvar Meta</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalHistoricoMetas" tabindex="-1" role="dialog" aria-labelledby="modalHistoricoMetasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" style="background-color:#222;color:#eee;">
            <div class="modal-header" style="border-bottom:1px solid #444;">
                <h5 class="modal-title" id="modalHistoricoMetasLabel"><i class="fas fa-history"></i> Histórico de metas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar" style="color:#fff"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" style="overflow:auto">
                <table class="meta-history-table">
                    <thead><tr><th>Ciclo</th><th>Meta</th><th>Realizado</th><th>Resultado</th><th>Início</th><th>Atingida em</th><th>Encerramento</th><th>Criada por</th></tr></thead>
                    <tbody id="historico-metas-body"><tr><td colspan="8">Carregando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovoCliente" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1050;">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="background-color: #222; color: #eee;">
            <div class="modal-header" style="border-bottom: 1px solid #444;">
                <h5 class="modal-title">Cadastrar Novo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:white;">&times;</button>
            </div>
            <div class="modal-body">
                <form id="form-novo-cliente">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="tipo" value="pessoa">
                    
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" class="form-control" required style="background:#333; color:#fff; border:1px solid #555;">
                    </div>
                    <div class="form-group">
                        <label>CPF ou CNPJ</label>
                        <input type="text" name="cpf_cnpj" class="form-control" style="background:#333; color:#fff; border:1px solid #555;">
                    </div>
                    <div class="form-group">
                        <label>Endereço</label>
                        <input type="text" name="endereco" class="form-control" style="background:#333; color:#fff; border:1px solid #555;">
                    </div>
                    <div class="form-group">
                        <label>Contato (Telefone)</label>
                        <input type="text" name="contato" class="form-control" style="background:#333; color:#fff; border:1px solid #555;">
                    </div>
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" class="form-control" style="background:#333; color:#fff; border:1px solid #555;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Salvar Cliente</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {

    let tipoFinalizacao = 'recibo';
    const userPerfil = '<?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?>';

    // =======================================================
    // ✅ FUNÇÃO DE ALERTA FLUENTE VIA JAVASCRIPT (CORRIGIDA)
    // =======================================================
    function showAlert(message, type) {
        let cssClass = 'alert-info';
        let icon = '<i class="fas fa-info-circle"></i>';

        if (type === 'success') {
            cssClass = 'alert-success';
            icon = '<i class="fas fa-check-circle" style="color:#28a745"></i>';
        } else if (type === 'danger' || type === 'error') {
            cssClass = 'alert-danger';
            icon = '<i class="fas fa-times-circle" style="color:#dc3545"></i>';
        } else if (type === 'warning') {
            cssClass = 'alert-warning';
            icon = '<i class="fas fa-exclamation-triangle" style="color:#ffc107"></i>';
        }

        // Verifica se overlay já existe
        let overlay = $('#flash-overlay');
        if (overlay.length === 0) {
            $('body').append(`<div class='alert-overlay' id='flash-overlay'></div>`);
            overlay = $('#flash-overlay');
        }

        const id = 'alert-' + Date.now();
        const html = `
            <div class='alert-box ${cssClass}' id='${id}'>
                ${icon}
                <div class='alert-msg'>${message}</div>
                <button onclick="$('#${id}').remove()" class='btn-fechar-alert'>&times;</button>
            </div>
        `;

        overlay.append(html);

        // Fecha automaticamente após 4 segundos
        setTimeout(() => {
            const el = document.getElementById(id);
            if(el) {
                el.style.transition = 'opacity 0.5s, transform 0.5s';
                el.style.opacity = '0';
                el.style.transform = 'translateX(20px)';
                setTimeout(() => el.remove(), 500);
            }
        }, 4000);
    }

    /* ================================
       CARREGAR META DE VENDAS
       ================================== */
    function carregarMetaVendas() {
        fetch('../actions/get_meta_vendas.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {

                const meta = parseFloat(data.meta);
                const atual = parseFloat(data.atual);
                const percentualReal = parseFloat(data.percentual || 0);
                const larguraBarra = Math.min(100, percentualReal);

                $('#meta-valores-texto').text(`Atual: R$ ${data.atual_formatado} / Meta: R$ ${data.meta_formatada}`);

                $('#meta-progress-bar')
                    .css('width', larguraBarra + '%')
                    .attr('aria-valuenow', percentualReal)
                    .text(percentualReal.toFixed(1) + '%');

                const status = $('#meta-status-texto').removeClass('atingida');
                if (!data.meta_id) {
                    status.text('Nenhuma meta ativa. Crie uma meta para iniciar o primeiro ciclo.');
                } else if (data.atingida) {
                    status.addClass('atingida').text(`Meta atingida! A contagem continua. Excedente: R$ ${data.excedente_formatado}.`);
                } else {
                    status.text(`Ciclo iniciado em ${new Date(data.inicio_em.replace(' ', 'T')).toLocaleString('pt-BR')}.`);
                }

                if (userPerfil === 'admin' || userPerfil === 'proprietario') {
                    $('#valor_meta_input').val('');
                }

            } else {
                console.warn('Não foi possível carregar a meta de vendas: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erro ao buscar meta:', err);
            // Opcional: showAlert('Erro de comunicação ao buscar meta de vendas.', 'danger');
        });
    }

    carregarMetaVendas();

    function carregarHistoricoMetas() {
        const body = $('#historico-metas-body').html('<tr><td colspan="8">Carregando...</td></tr>');
        fetch('../actions/get_historico_metas.php')
        .then(res => res.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Falha ao carregar histórico.');
            if (!data.metas.length) {
                body.html('<tr><td colspan="8">Nenhuma meta registrada.</td></tr>');
                return;
            }
            body.empty();
            data.metas.forEach(metaItem => {
                const situacao = metaItem.status === 'ativa' ? '<span class="badge badge-primary">Ativa</span>' : (metaItem.atingida ? '<span class="badge badge-success">Atingida</span>' : '<span class="badge badge-warning">Não atingida</span>');
                body.append(`<tr><td>#${metaItem.id} ${situacao}</td><td>R$ ${metaItem.meta}</td><td>R$ ${metaItem.realizado}</td><td>${metaItem.percentual}%</td><td>${metaItem.inicio}</td><td>${metaItem.atingida || '-'}</td><td>${metaItem.encerramento || '-'}</td><td>${$('<div>').text(metaItem.criador).html()}</td></tr>`);
            });
        })
        .catch(err => body.html(`<tr><td colspan="8">${$('<div>').text(err.message).html()}</td></tr>`));
    }

    $('#modalHistoricoMetas').on('show.bs.modal', carregarHistoricoMetas);

    /* ================================
       MODAL DE META (ADMIN)
       ================================== */
    if (userPerfil === 'admin' || userPerfil === 'proprietario') {

        $('#valor_meta_input').mask('000.000.000,00', {reverse: true});

        $('#btn-salvar-meta').on('click', function() {

            const formData = new FormData();
            const metaValor = $('#valor_meta_input').val();

            if (metaValor === '') {
                showAlert('Por favor, insira um valor para a meta.', 'warning');
                return;
            }

            formData.append('meta', metaValor);

            fetch('../actions/set_meta_vendas.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    $('#modalMetaVendas').modal('hide');
                    carregarMetaVendas();
                    carregarHistoricoMetas();

                } else {
                    showAlert('Erro: ' + data.message, 'danger');
                }
            })
            .catch(err => {
                console.error('Erro ao salvar meta:', err);
                showAlert('Erro de comunicação ao salvar a meta.', 'danger');
            });
        });
    }

    /* ================================
       SELECT2 CLIENTES
       ================================== */
    $('#cliente_id').select2({
        placeholder: 'Selecione ou digite o nome de um cliente',
        ajax: {
            url: 'vendas.php?action=search_clientes',
            dataType: 'json',
            delay: 250,
            processResults: data => ({ results: data.results }),
            cache: true
        }
    });

    /* ================================
       AJAX: CADASTRO RÃPIDO DE CLIENTE
       ================================== */
    $('#form-novo-cliente').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        // Flag para indicar que é via AJAX
        formData.append('ajax', true); 

        $.ajax({
            url: '../actions/cadastrar_pessoa_fornecedor_action.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Como o backend redireciona, o 'success' é chamado.
                // Assumimos sucesso pois se houvesse erro de script seria 'error'.
                // Fechamos o modal e mostramos o alerta.
                $('#modalNovoCliente').modal('hide');
                $('#form-novo-cliente')[0].reset();
                
                showAlert('Cliente cadastrado com sucesso!', 'success');
                
                // Limpa o select para o usuário buscar o novo cliente
                $('#cliente_id').val(null).trigger('change');
                
                // Se quiser pré-selecionar o cliente recém criado:
                const nome = formData.get('nome');
                // Mas não temos o ID, então apenas forçamos o usuário a buscar.
            },
            error: function() {
                showAlert('Erro ao comunicar com o servidor.', 'danger');
            }
        });
    });

    /* ================================
       SELECT2 PRODUTOS
       ================================== */
    $('#produto_select').select2({
        placeholder: 'Pesquisar produto pelo nome ou codigo...',
        ajax: {
            url: 'vendas.php?action=search_produtos',
            dataType: 'json',
            delay: 250,
            processResults: data => ({ results: data.results }),
            cache: true
        }
    });

    /* ================================
       ADICIONAR PRODUTO
       ================================== */
    $('#add-produto').on('click', function() {

        const produtoData = $('#produto_select').select2('data')[0];
        if (!produtoData || !produtoData.id)
            return showAlert('Selecione um produto para adicionar.', 'warning');

        const produtoId = produtoData.id;
        const produtoNome = produtoData.text.split(' (Estoque:')[0];
        const precoVenda = parseFloat(produtoData.preco_venda || 0).toFixed(2);
        const estoque = parseInt(produtoData.estoque);

        if ($(`#venda-items tr[data-id="${produtoId}"]`).length > 0)
            return showAlert('Este produto já foi adicionado.', 'warning');

        if (estoque <= 0)
            return showAlert('Este produto está sem estoque.', 'danger');

        const row = `
            <tr data-id="${produtoId}" data-preco="${precoVenda}">
                <td>${produtoNome}</td>
                <td><input type="number" class="form-control quantidade" value="1" min="1" max="${estoque}"></td>
                <td class="preco-unitario">R$ ${precoVenda}</td>
                <td class="subtotal">R$ ${precoVenda}</td>
                <td><button type="button" class="btn btn-danger btn-sm remover-item"><i class="fas fa-trash"></i></button></td>
            </tr>`;

        $('#venda-items').append(row);
        atualizarTotal();
        $('#produto_select').val(null).trigger('change');
    });

    /* ================================
       ALTERAÇÃO DE QUANTIDADE
       ================================== */
    function buscarCodigoDeBarras() {
        const campo = $('#barcode_input');
        const codigo = campo.val().trim();
        if (!codigo) return;
        $.getJSON('vendas.php', { action: 'search_barcode', codigo: codigo })
            .done(function(data) {
                if (!data.found) {
                    showAlert(data.message || 'Produto nao encontrado.', 'warning');
                    campo.select().focus();
                    return;
                }
                const produto = data.product;
                const produtoId = produto.id;
                const produtoNome = produto.text.split(' (Estoque:')[0];
                const precoVenda = parseFloat(produto.preco_venda || 0).toFixed(2);
                const estoque = parseInt(produto.estoque);
                const existente = $(`#venda-items tr[data-id="${produtoId}"]`);
                if (estoque <= 0) {
                    showAlert('Este produto esta sem estoque.', 'danger');
                    campo.val('').focus();
                    return;
                }
                if (existente.length > 0) {
                    const quantidade = existente.find('.quantidade');
                    const atual = parseInt(quantidade.val() || 0);
                    if (atual >= estoque) {
                        showAlert('Quantidade maxima disponivel em estoque atingida.', 'warning');
                    } else {
                        quantidade.val(atual + 1).trigger('input');
                    }
                    campo.val('').focus();
                    return;
                }
                const row = `
                    <tr data-id="${produtoId}" data-preco="${precoVenda}">
                        <td>${produtoNome}</td>
                        <td><input type="number" class="form-control quantidade" value="1" min="1" max="${estoque}"></td>
                        <td class="preco-unitario">R$ ${precoVenda}</td>
                        <td class="subtotal">R$ ${precoVenda}</td>
                        <td><button type="button" class="btn btn-danger btn-sm remover-item"><i class="fas fa-trash"></i></button></td>
                    </tr>`;
                $('#venda-items').append(row);
                atualizarTotal();
                campo.val('').focus();
            })
            .fail(function() {
                showAlert('Nao foi possivel consultar o codigo.', 'danger');
                campo.focus();
            });
    }

    $('#barcode_input').on('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            buscarCodigoDeBarras();
        }
    });
    $('#buscar-barcode').on('click', buscarCodigoDeBarras);

    $('#venda-items').on('input', '.quantidade', function() {

        const tr = $(this).closest('tr');
        let qtd = parseInt($(this).val()) || 1;
        const estoque = parseInt($(this).attr('max'));

        if (qtd < 1) {
            qtd = 1;
            $(this).val(1);
        }

        if (qtd > estoque) {
            showAlert(`âš ï¸ Estoque máximo disponível: ${estoque}`, 'warning');
            $(this).val(estoque);
            qtd = estoque;
        }

        const preco = parseFloat(tr.data('preco'));
        const subtotal = (qtd * preco).toFixed(2);

        tr.find('.subtotal').text('R$ ' + subtotal);

        atualizarTotal();
    });

    /* ================================
       REMOVER ITEM
       ================================== */
    $('#venda-items').on('click', '.remover-item', function() {
        $(this).closest('tr').remove();
        atualizarTotal();
    });

    /* ================================
       MÃSCARA DE DESCONTO
       ================================== */
    $('#desconto').mask('000.000.000,00', {reverse: true});
    $('#desconto').on('input', atualizarTotal);

    /* ================================
       CALCULAR TOTAL
       ================================== */
    function atualizarTotal() {

        let total = 0;

        $('#venda-items tr').each(function() {
            const valor = parseFloat($(this).find('.subtotal').text().replace('R$ ', '')) || 0;
            total += valor;
        });

        let desconto = parseFloat($('#desconto').val().replace(/\./g, '').replace(',', '.')) || 0;

        if (desconto > total)
            desconto = total;

        const totalLiquido = (total - desconto).toFixed(2);

        $('#total-geral').text(totalLiquido.replace('.', ','));
    }

    /* ================================
       BOTÕES FINALIZAR
       ================================== */
    $('#btn-recibo').on('click', function() {
        tipoFinalizacao = 'recibo';
        $('#form-venda').submit();
    });

    $('#btn-nfe').on('click', function() {
        tipoFinalizacao = 'nfe';
        $('#form-venda').submit();
    });

    /* ================================
       SUBMIT DA VENDA
       ================================== */
    $('#form-venda').on('submit', function(e) {

        e.preventDefault();

        if ($('#venda-items tr').length === 0)
            return showAlert('Adicione pelo menos um produto à venda.', 'danger');

        const itens = $('#venda-items tr').map(function() {
            const tr = $(this);
            return {
                id: tr.data('id'),
                quantidade: parseInt(tr.find('.quantidade').val()),
                preco: parseFloat(tr.data('preco'))
            };
        }).get();

        const formData = new FormData(this);

        formData.append('itens', JSON.stringify(itens));
        formData.append('tipo_finalizacao', tipoFinalizacao);

        const descontoFormatado =
            parseFloat($('#desconto').val().replace(/\./g, '').replace(',', '.')) || 0;

        formData.set('desconto', descontoFormatado);

        showAlert('Processando venda...', 'info');

        fetch('../actions/registrar_venda.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {

            if (data.success) {

                showAlert(data.message, 'success');

                if (tipoFinalizacao === 'recibo') {
                    window.open('recibo_venda.php?id=' + data.venda_id, '_blank');
                } else {
                    emitirNFe(data.venda_id);
                }

                limparFormulario();
                carregarMetaVendas();

            } else {
                showAlert('Erro: ' + data.message, 'danger');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            showAlert('Erro ao comunicar com o servidor.', 'danger');
        });

    });

    /* ================================
       EMITIR NFC-e NA SEFAZ
       ================================== */
    function emitirNFe(vendaId) {

        const urlEmissao = '../actions/emitir_nfce.php';

        showAlert('Transmitindo NFC-e à SEFAZ. Aguarde a autorização...', 'info');

        fetch(urlEmissao, {
            method: 'POST',
            body: new URLSearchParams({
                id_venda: vendaId,
                emitir_nfce_sefaz: '1',
                token_validacao_nfce: <?= json_encode($token_nfce, JSON_UNESCAPED_SLASHES) ?>
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showAlert(data.message || 'NFC-e autorizada pela SEFAZ.', 'success');
                setTimeout(() => {
                    window.open('../actions/gerar_danfe.php?chave=' + data.chave, '_blank');
                }, 1500);
            } else
                showAlert('A NFC-e não foi autorizada: ' + data.message, 'danger');
        })
        .catch(err => {
            console.error(err);
            showAlert('Erro de comunicação com o servidor na emissão da NF-e.', 'danger');
        });
    }

    /* ================================
       LIMPAR FORMULÃRIO
       ================================== */
    function limparFormulario() {
        $('#form-venda')[0].reset();
        $('#cliente_id').val(null).trigger('change');
        $('#venda-items').empty();
        atualizarTotal();
    }

});
</script>

</body>
</html>
