<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/account_lifecycle.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_once __DIR__ . '/enviar_email.php';

require_tenant_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
require_csrf();

$tenantId = (string) ($_SESSION['tenant_id'] ?? '');
if ($tenantId === '' || (string)($_POST['opcao_cancelamento'] ?? '') !== 'desativar') {
    set_flash_message('danger', 'Selecione a desativação temporária para continuar.');
    header('Location: ../pages/minha_assinatura.php'); exit;
}

require_once __DIR__ . '/../includes/account_cycle_transition.php';
require_once __DIR__ . '/../includes/account_cycle_consent.php';
$db = getMasterConnection();
try {
    $id = (int) ($_SESSION['usuario_id_master'] ?? 0);
    $owner = $db->execute_query('SELECT nome, email, documento FROM usuarios WHERE id=? AND tenant_id=?', [$id, $tenantId])->fetch_assoc();
    if (!$owner) throw new DomainException('Titular não encontrado.');
    account_cycle_change($db, $tenantId, false, function (mysqli $db, array $tenant) use ($tenantId, $id, $owner) {
        if (strcasecmp((string) $tenant['admin_email'], $owner['email']) !== 0) {
            throw new DomainException('O titular da conta deve confirmar o pedido.');
        }
        account_cycle_record_consent($db, $tenantId, $id, $owner['nome'], $owner['email'], $owner['documento']);
    });
    set_flash_message('success', 'Encerramento agendado para o fim do ciclo.');
} catch (Throwable $error) {
    error_log('Falha ao agendar cancelamento: ' . $error->getMessage());
    set_flash_message('danger', 'Não foi possível agendar o encerramento. Entre em contato com o suporte.');
}
header('Location: ../pages/minha_assinatura.php');
exit;
