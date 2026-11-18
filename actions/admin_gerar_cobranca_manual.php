<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_once __DIR__ . '/enviar_email.php';

if (!isset($_SESSION['super_admin'])) {
    http_response_code(403);
    exit('Acesso restrito ao administrador master.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo nao permitido.');
}
require_csrf();

$tenantPk = filter_var($_POST['tenant_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($tenantPk === false) {
    set_flash_message('danger', 'Conta invalida para cobranca.');
    header('Location: ../pages/admin/controle_financeiro_sistema.php#contas-financeiras');
    exit;
}

$db = getMasterConnection();
try {
    $stmt = $db->prepare('SELECT * FROM tenants WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $tenantPk); $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$tenant) throw new RuntimeException('Conta nao encontrada.');

    $status = (string)($tenant['status_assinatura'] ?? '');
    $overdueStmt = $db->prepare("SELECT COUNT(*) total FROM faturas_assinatura WHERE tenant_id=? AND status='pendente' AND data_vencimento<CURDATE()");
    $tenantCode = (string)$tenant['tenant_id'];
    $overdueStmt->bind_param('s', $tenantCode); $overdueStmt->execute();
    $overdueCount = (int)($overdueStmt->get_result()->fetch_assoc()['total'] ?? 0); $overdueStmt->close();
    $permitidos = ['vencido', 'suspenso', 'inativo', 'cancelado', 'pendente', 'trial_expired'];
    if (!in_array($status, $permitidos, true) && $overdueCount < 1) {
        throw new RuntimeException('A cobranca manual e permitida apenas para contas com pendencia financeira.');
    }

    $emailDestino = trim((string)($tenant['admin_email'] ?? ''));
    if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('A conta nao possui e-mail principal valido.');
    if (mp_access_token() === '' || mp_back_url() === '') throw new RuntimeException('Mercado Pago nao configurado.');

    $recent = $db->prepare("SELECT COUNT(*) total FROM cobrancas_manuais WHERE tenant_id=? AND status='enviada' AND criado_em>=DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $recent->bind_param('s', $tenantCode); $recent->execute();
    $recentCount = (int)($recent->get_result()->fetch_assoc()['total'] ?? 0); $recent->close();
    if ($recentCount > 0) throw new RuntimeException('Ja existe uma cobranca enviada para esta conta nos ultimos 10 minutos.');

    $planos = ['basico' => 19.00, 'plus' => 39.00, 'essencial' => 59.00];
    $plano = strtolower((string)($tenant['plano_atual'] ?? 'basico'));
    if (!isset($planos[$plano])) $plano = 'basico';
    $valor = $planos[$plano] + (max(0, (int)($tenant['usuarios_extras'] ?? 0)) * 4.00);
    $payerEmail = mp_mode() === 'sandbox' ? trim((string)($_ENV['MP_TEST_PAYER_EMAIL'] ?? '')) : $emailDestino;
    if ($payerEmail === '') throw new RuntimeException('E-mail pagador de teste nao configurado.');

    $oldSubscription = trim((string)($tenant['id_assinatura_mp'] ?? ''));
    if ($oldSubscription !== '') {
        mp_api_request('PUT', '/preapproval/' . rawurlencode($oldSubscription), ['status' => 'cancelled']);
    }

    $checkoutToken = bin2hex(random_bytes(32));
    $idempotencyKey = bin2hex(random_bytes(32));
    $checkoutStatus = 'criando';
    $requestedBy = null;
    $insert = $db->prepare('INSERT INTO checkout_assinaturas
        (checkout_token, tenant_id, plano, idempotency_key, valor_original, valor_final, status, solicitado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $tenantId = $tenantCode;
    $insert->bind_param('ssssddsi', $checkoutToken, $tenantId, $plano, $idempotencyKey, $valor, $valor, $checkoutStatus, $requestedBy);
    $insert->execute(); $checkoutId = (int)$db->insert_id; $insert->close();

    $payload = [
        'payer_email' => $payerEmail,
        'back_url' => mp_back_url(),
        'reason' => 'Regularizacao da assinatura ' . ucfirst($plano) . ' - App Controle',
        'external_reference' => $checkoutToken,
        'auto_recurring' => [
            'frequency' => 1,
            'frequency_type' => 'months',
            'transaction_amount' => $valor,
            'currency_id' => 'BRL',
        ],
        'status' => 'pending',
    ];
    try {
        $response = mp_api_request('POST', '/preapproval', $payload, $idempotencyKey)['body'];
    } catch (Throwable $mpError) {
        $checkoutError = 'erro';
        $failedCheckout = $db->prepare('UPDATE checkout_assinaturas SET status=? WHERE id=?');
        $failedCheckout->bind_param('si', $checkoutError, $checkoutId); $failedCheckout->execute(); $failedCheckout->close();
        if (mp_mode() === 'sandbox' && $mpError->getMessage() === 'User bad request') {
            throw new RuntimeException('O usuario pagador de teste foi recusado pelo Mercado Pago. Confira se MP_TEST_PAYER_EMAIL pertence a um comprador de teste diferente da conta vendedora.');
        }
        throw new RuntimeException('Mercado Pago recusou a criacao do link: ' . $mpError->getMessage());
    }
    $mpId = trim((string)($response['id'] ?? ''));
    $paymentLink = trim((string)($response['init_point'] ?? ''));
    if ($mpId === '' || $paymentLink === '') throw new RuntimeException('Mercado Pago nao retornou o link de cobranca.');

    $checkoutStatus = 'pendente';
    $update = $db->prepare('UPDATE checkout_assinaturas SET status=?, mp_preapproval_id=?, init_point=? WHERE id=?');
    $update->bind_param('sssi', $checkoutStatus, $mpId, $paymentLink, $checkoutId); $update->execute(); $update->close();

    $adminEmail = (string)($_SESSION['super_admin']['email'] ?? 'master');
    $manualStatus = 'enviada';
    $audit = $db->prepare('INSERT INTO cobrancas_manuais (tenant_id, checkout_id, mp_preapproval_id, email_destino, valor, status, solicitado_por_email) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $audit->bind_param('sissdss', $tenantId, $checkoutId, $mpId, $emailDestino, $valor, $manualStatus, $adminEmail);
    $audit->execute(); $audit->close();

    $name = (string)($tenant['nome'] ?: $tenant['nome_empresa'] ?: 'Cliente');
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($paymentLink, ENT_QUOTES, 'UTF-8');
    $formattedValue = number_format($valor, 2, ',', '.');
    $sent = enviarEmail($emailDestino, $name, 'Link para regularizar sua assinatura', "Ola {$safeName},<br><br>Identificamos uma pendencia ou falha no pagamento automatico da sua assinatura. Use o botao abaixo para regularizar o plano com seguranca pelo Mercado Pago.<br><br><a href=\"{$safeLink}\" style=\"display:inline-block;padding:12px 18px;background:#0878e8;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold\">Regularizar assinatura - R$ {$formattedValue}</a><br><br>Nenhum dado de cartao e solicitado dentro do App Controle.");
    if (!$sent) {
        $mailError = 'erro_email';
        $failedAudit = $db->prepare('UPDATE cobrancas_manuais SET status=? WHERE mp_preapproval_id=?');
        $failedAudit->bind_param('ss', $mailError, $mpId); $failedAudit->execute(); $failedAudit->close();
        throw new RuntimeException('A cobranca foi criada, mas o e-mail nao pode ser enviado.');
    }

    set_flash_message('success', 'Link de regularizacao criado e enviado para ' . $emailDestino . '.');
} catch (Throwable $e) {
    error_log('Cobranca manual master: ' . $e->getMessage());
    set_flash_message('danger', 'Nao foi possivel gerar a cobranca manual: ' . $e->getMessage());
} finally {
    $db->close();
}

header('Location: ../pages/admin/controle_financeiro_sistema.php#contas-financeiras');
exit;
