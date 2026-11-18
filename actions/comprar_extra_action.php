<?php

session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';

if (!isset($_SESSION['usuario_logado']) || empty($_SESSION['tenant_id'])) {
    header('Location: ../pages/login.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$conn = getMasterConnection();
$tenantId = (string) $_SESSION['tenant_id'];
$quantity = max(1, (int) ($_POST['qtd_extra'] ?? 1));
$unitPrice = 4.00;
$nextPage = '../pages/minha_assinatura.php';
$recurringBefore = null;

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT id_assinatura_mp, status_assinatura, plano_atual, usuarios_extras FROM tenants WHERE tenant_id = ? FOR UPDATE');
    $stmt->bind_param('s', $tenantId);
    $stmt->execute();
    $tenant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tenant) {
        throw new RuntimeException('Conta não encontrada.');
    }

    $totalExtras = (int) $tenant['usuarios_extras'] + $quantity;
    $trial = $tenant['status_assinatura'] === 'trial';
    if (!$trial && empty($tenant['id_assinatura_mp'])) {
        $conn->rollback();
        $_SESSION['erro_assinatura'] = 'Assinatura não localizada. Reative seu plano.';
        header('Location: ../pages/assinar.php?plano_selecionado=' . rawurlencode($tenant['plano_atual'] ?? 'basico'));
        exit;
    }

    if (!$trial) {
        $path = '/preapproval/' . rawurlencode($tenant['id_assinatura_mp']);
        $subscription = mp_api_request('GET', $path)['body'];
        $recurring = $subscription['auto_recurring'] ?? [];
        if (($subscription['status'] ?? '') !== 'authorized'
            || ($recurring['currency_id'] ?? '') !== 'BRL'
            || !is_numeric($recurring['transaction_amount'] ?? null)
            || (float) $recurring['transaction_amount'] < 0
        ) {
            throw new RuntimeException('O valor recorrente autorizado em reais não pôde ser confirmado.');
        }

        // Preserva o desconto já refletido na assinatura e cobra os novos extras pelo preço integral.
        $currentAmount = (float) $recurring['transaction_amount'];
        $newAmount = round($currentAmount + $quantity * $unitPrice, 2);
        mp_api_request('PUT', $path, ['auto_recurring' => ['transaction_amount' => $newAmount, 'currency_id' => 'BRL']]);
        $recurringBefore = $currentAmount;
    }

    $stmt = $conn->prepare('UPDATE tenants SET usuarios_extras = ? WHERE tenant_id = ?');
    $stmt->bind_param('is', $totalExtras, $tenantId);
    $stmt->execute();
    $stmt->close();
    $conn->commit();

    set_flash_message('success', $trial
        ? "Adicionado $quantity usuário(s) ao teste grátis."
        : 'Sucesso! Valor atualizado para R$ ' . number_format($newAmount, 2, ',', '.'));
} catch (Throwable $error) {
    if ($recurringBefore !== null) {
        try {
            mp_api_request('PUT', $path, ['auto_recurring' => [
                'transaction_amount' => $recurringBefore,
                'currency_id' => 'BRL',
            ]]);
        } catch (Throwable $restoreError) {
            error_log('Falha ao restaurar valor recorrente após a compra de extras.');
        }
    }
    $conn->rollback();
    error_log('Falha na compra de usuários extras: ' . $error->getMessage());
    set_flash_message('danger', 'Não foi possível adicionar usuários extras.');
}

$conn->close();
header('Location: ' . $nextPage);
exit;