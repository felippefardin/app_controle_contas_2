<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';

if (!isset($_SESSION['super_admin'])) {
    header('Location: ../pages/login.php');
    exit;
}

$destinos = [
    'usuarios' => 'usuarios_sistema.php',
    'cupons' => 'cupom_desconto.php',
    'email' => 'email_marketing.php',
    'arquivo' => 'chamados_resolvidos.php',
    'logs' => 'arquivos_suportes.php',
    'suporte' => 'suporte_via_login.php',
    'feedback' => 'feedback.php',
    'documentos_lgpd' => 'documento_de_registro_no_sistema.php',
    'solicitacoes_lgpd' => 'solicitacoes_lgpd.php',
    'financeiro' => 'controle_financeiro_sistema.php#contas-financeiras',
    'mensagens' => 'mensagens_home_criadas.php',
];

$categoria = (string)($_GET['categoria'] ?? '');
if (!isset($destinos[$categoria])) {
    header('Location: ../pages/admin/dashboard.php');
    exit;
}

$currentCount = (int)($_SESSION['master_nav_contagens_atuais'][$categoria] ?? 0);
$_SESSION['master_nav_alertas_vistos'][$categoria] = $currentCount;
header('Location: ../pages/admin/' . $destinos[$categoria]);
exit;
