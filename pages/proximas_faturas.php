<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';

$tenantId = (string) ($_SESSION['tenant_id'] ?? '');
if (($_SESSION['usuario_logado'] ?? false) !== true || $tenantId === '') {
    header('Location: login.php');
    exit;
}
header('Cache-Control: private, no-store');

$planChange = null;
$adjustment = 0;
$amount = null;
$chargeDate = null;
$message = 'Não há cobrança recorrente agendada para esta assinatura.';
try {
    $db = getMasterConnection();
    if (!$db) {
        throw new RuntimeException('Banco principal indisponível.');
    }
    $stmt = $db->prepare('SELECT id_assinatura_mp, tipo_cancelamento FROM tenants WHERE tenant_id = ? LIMIT 1');
    $stmt->bind_param('s', $tenantId);
    $stmt->execute();
    $subscription = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($subscription['id_assinatura_mp']) && empty($subscription['tipo_cancelamento'])) {
        try {
            $stmt = $db->prepare("SELECT plano_origem, plano_destino, valor_normal, valor_programado, renovacao
                FROM trocas_planos WHERE tenant_id = ? AND assinatura_mp = ? AND estado = 'aguardando_fatura'
                ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('ss', $tenantId, $subscription['id_assinatura_mp']);
            $stmt->execute();
            $planChange = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } catch (Throwable $error) {
            error_log('Falha ao consultar a composição da próxima fatura.');
        }
    }
    $db->close();

    if (!empty($subscription['tipo_cancelamento'])) {
        $message = 'A assinatura tem encerramento agendado para o fim do ciclo atual.';
    } elseif (!empty($subscription['id_assinatura_mp'])) {
        $path = '/preapproval/' . rawurlencode($subscription['id_assinatura_mp']);
        $remote = mp_api_request('GET', $path)['body'];
        $recurring = $remote['auto_recurring'] ?? [];
        if (($remote['status'] ?? '') === 'authorized') {
            $nextPayment = $remote['next_payment_date'] ?? '';
            $value = $recurring['transaction_amount'] ?? null;
            if (is_string($nextPayment) && strtotime($nextPayment) !== false && ($recurring['currency_id'] ?? '') === 'BRL' && is_numeric($value) && (float) $value >= 0) {
                $chargeDate = (new DateTimeImmutable($nextPayment))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                $amount = (float) $value;
                $total = (int) round($amount * 100);
                if ($planChange && (int) $planChange['renovacao'] === $chargeDate->getTimestamp()
                    && (int) $planChange['valor_programado'] === $total) {
                    $adjustment = $total - (int) $planChange['valor_normal'];
                }
            } else {
                $message = 'O Mercado Pago ainda não disponibilizou a data e o valor da próxima cobrança.';
            }
        }
    }
} catch (Throwable $error) {
    error_log('Não foi possível consultar a próxima fatura da assinatura.');
    $message = 'Não foi possível consultar a próxima fatura agora. Atualize a página para tentar novamente.';
}

$planNames = ['basico' => 'Básico', 'plus' => 'Plus', 'essencial' => 'Essencial'];
$formatCents = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');
$pageTitle = 'Próximas faturas | App Controle de Contas';
include __DIR__ . '/../includes/header.php';
?>
<style>
    .invoice-preview { width: min(100% - 2rem, 72rem); margin: 1.5rem auto; color: #edf4fb; }
    .invoice-preview__header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding-bottom: .75rem; border-bottom: 1px solid #00bfff; margin-bottom: 1rem; }
    .invoice-preview__header h1 { margin: 0; font-size: 1.45rem; color: #00bfff; }
    .invoice-preview__back { color: inherit; text-decoration: none; border: 1px solid currentColor; border-radius: .4rem; padding: .5rem .85rem; }
    .invoice-preview__back:hover { text-decoration: underline; }
    .invoice-preview__card { background: #1e1e1e; border: 1px solid #444; border-radius: .65rem; padding: 1.25rem; }
    .invoice-preview__card h2 { font-size: 1.1rem; margin: 0 0 1rem; }
    .invoice-preview__details { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 1rem; margin: 0; }
    .invoice-preview__details dd { font-size: 1.35rem; font-weight: 700; margin: .25rem 0 0; }
    .invoice-preview__note { margin: 1rem 0 0; }
    html[data-app-theme="light"] .invoice-preview, body.light-mode .invoice-preview { color: #172033; }
    html[data-app-theme="light"] .invoice-preview__header h1, body.light-mode .invoice-preview__header h1 { color: #075985; }
    html[data-app-theme="light"] .invoice-preview__card, body.light-mode .invoice-preview__card { background: #fff; border-color: #d4deec; box-shadow: 0 4px 15px rgb(23 32 51 / 8%); }
    .invoice-breakdown { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid rgb(128 145 165 / 40%); }
    .invoice-breakdown h3 { font-size: 1.05rem; }
    .invoice-breakdown dl { margin: 0; }
    .invoice-breakdown dl div { display: flex; justify-content: space-between; gap: 1rem; padding: .4rem 0; }
    .invoice-breakdown dd { margin: 0; font-weight: 700; white-space: nowrap; }
</style>
<div class="invoice-preview">
    <div class="invoice-preview__header">
        <h1>Próximas faturas</h1>
        <a class="invoice-preview__back" href="minha_assinatura.php">Voltar para Gestão da Assinatura</a>
    </div>
    <section class="invoice-preview__card" aria-labelledby="proxima-cobranca">
        <h2 id="proxima-cobranca">Próxima cobrança</h2>
        <?php if ($amount !== null && $chargeDate !== null): ?>
            <dl class="invoice-preview__details">
                <div><dt>Data prevista</dt><dd><time datetime="<?= $chargeDate->format('Y-m-d') ?>"><?= $chargeDate->format('d/m/Y') ?></time></dd></div>
                <div><dt>Valor previsto</dt><dd>R$ <?= number_format($amount, 2, ',', '.') ?></dd></div>
            </dl>
            <?php if ($adjustment !== 0): ?>
                <section class="invoice-breakdown" aria-labelledby="composicao-fatura">
                    <h3 id="composicao-fatura">Composição da próxima fatura</h3>
                    <dl>
                        <div><dt>Mensalidade após a troca</dt><dd><?= $formatCents((int) $planChange['valor_normal']) ?></dd></div>
                        <div><dt><?= $adjustment > 0 ? 'Diferença proporcional do ciclo anterior' : 'Crédito da troca de plano' ?></dt><dd><?= $adjustment > 0 ? '+' : '−' ?> <?= $formatCents(abs($adjustment)) ?></dd></div>
                        <div><dt>Total previsto</dt><dd><?= $formatCents($total) ?></dd></div>
                    </dl>
                    <?php if ($adjustment > 0): ?>
                        <p class="invoice-preview__note">O plano <?= $planNames[$planChange['plano_destino']] ?? 'novo' ?> foi ativado antes da renovação. O adicional cobre a diferença entre os planos pelos dias restantes do ciclo já pago do plano <?= $planNames[$planChange['plano_origem']] ?? 'anterior' ?>.</p>
                    <?php else: ?>
                        <p class="invoice-preview__note">O crédito desta fatura reduz temporariamente o valor da mensalidade.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <p class="invoice-preview__note">O valor é consultado no Mercado Pago ao abrir esta página e reflete as alterações já registradas na assinatura.</p>
        <?php else: ?>
            <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
