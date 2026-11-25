<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_once __DIR__ . '/../includes/cupom_service.php';
require_tenant_admin();

$planos = ['basico' => ['Básico', 1900], 'plus' => ['Plus', 3900], 'essencial' => ['Essencial', 5900]];
$destino = (string) ($_GET['plano'] ?? '');
$tenantId = (string) ($_SESSION['tenant_id'] ?? '');
$erro = null;
$formatar = static fn (int $centavos): string => 'R$ ' . number_format($centavos / 100, 2, ',', '.');
try {
    if (!isset($planos[$destino]) || $tenantId === '') throw new RuntimeException('Selecione um plano válido.');
    $db = getMasterConnection();
    $stmt = $db->prepare('SELECT plano_atual, id_assinatura_mp, status_assinatura, usuarios_extras, cupom_ativo_id, promocao_ativa_id FROM tenants WHERE tenant_id=? LIMIT 1');
    $stmt->bind_param('s', $tenantId);
    $stmt->execute();
    $conta = $stmt->get_result()->fetch_assoc();
    $atual = (string) ($conta['plano_atual'] ?? '');
    $assinaturaId = (string) ($conta['id_assinatura_mp'] ?? '');
    if ($assinaturaId === '' || ($conta['status_assinatura'] ?? '') !== 'ativo' || !isset($planos[$atual]) || $atual === $destino) {
        throw new RuntimeException('A prévia exige uma assinatura ativa e outro plano selecionado.');
    }
    $resposta = mp_api_request('GET', '/preapproval/' . rawurlencode($assinaturaId));
    $recorrencia = $resposta['body'] ?? [];
    $ciclo = $recorrencia['auto_recurring'] ?? [];
    if (($recorrencia['status'] ?? '') !== 'authorized' || empty($recorrencia['next_payment_date'])
        || (int) ($ciclo['frequency'] ?? 0) !== 1 || ($ciclo['frequency_type'] ?? '') !== 'months'
        || ($ciclo['currency_id'] ?? '') !== 'BRL') {
        throw new RuntimeException('Não foi possível confirmar o próximo vencimento mensal com o Mercado Pago.');
    }
    $fatura = $db->prepare("SELECT valor, data_vencimento, transacao_id FROM faturas_assinatura WHERE tenant_id=? AND status='pago' ORDER BY data_pagamento DESC, id DESC LIMIT 1");
    $fatura->bind_param('s', $tenantId);
    $fatura->execute();
    $paga = $fatura->get_result()->fetch_assoc();
    if (!$paga || empty($paga['data_vencimento']) || !ctype_digit((string) $paga['transacao_id'])) {
        throw new RuntimeException('A prévia fica disponível após a primeira mensalidade paga.');
    }
    $fuso = new DateTimeZone('America/Sao_Paulo');
    $hoje = new DateTimeImmutable('today', $fuso);
    $inicio = new DateTimeImmutable($paga['data_vencimento'], $fuso);
    $fim = (new DateTimeImmutable($recorrencia['next_payment_date']))->setTimezone($fuso)->setTime(0, 0);
    $dias = (int) $inicio->diff($fim)->format('%r%a');
    if ($dias < 27 || $dias > 32 || $hoje < $inicio || $hoje > $fim) {
        throw new RuntimeException('O ciclo atual ainda não pôde ser conciliado com a última fatura paga.');
    }
    $diasRestantes = (int) $hoje->diff($fim)->format('%a');
    $extras = max(0, (int) ($conta['usuarios_extras'] ?? 0)) * 400;
    $valorCatalogoAtual = $planos[$atual][1] + $extras;
    $valorAtual = (int) round((float) $paga['valor'] * 100);
    $valorRecorrencia = (int) round((float) ($ciclo['transaction_amount'] ?? 0) * 100);
    $cupom = null;
    if (!empty($conta['promocao_ativa_id'])) {
        $promocao = buscarPromocaoInterna($db, $tenantId);
        if ((int) ($promocao['promocao_id'] ?? 0) === (int) $conta['promocao_ativa_id']) {
            $cupom = $promocao;
        }
    } elseif (!empty($conta['cupom_ativo_id'])) {
        $cupomId = (int) $conta['cupom_ativo_id'];
        $consultaCupom = $db->prepare("SELECT tipo_desconto, valor FROM cupons_desconto WHERE id=? AND ativo=1 AND (data_inicio IS NULL OR data_inicio<=CURDATE()) AND (tempo_indeterminado=1 OR data_fim IS NULL OR data_fim>=CURDATE()) LIMIT 1");
        $consultaCupom->bind_param('i', $cupomId);
        $consultaCupom->execute();
        $cupom = $consultaCupom->get_result()->fetch_assoc() ?: null;
    }
    // O ajuste de plano compara apenas as partes fixas da mensalidade.
    $fixoAtual = $planos[$atual][1];
    $fixoNovo = $planos[$destino][1];
    if ($cupom !== null) {
        $fixoAtual = (int) round(calcularDescontoCupom($fixoAtual / 100, 0, $cupom)['final'] * 100);
        $fixoNovo = (int) round(calcularDescontoCupom($fixoNovo / 100, 0, $cupom)['final'] * 100);
    }
    $diferenca = (int) round(($fixoNovo - $fixoAtual) * $diasRestantes / $dias, 0, PHP_ROUND_HALF_UP);
    $novoValor = $fixoNovo + $extras;
    $proxima = max(0, $fixoNovo + $diferenca) + $extras;
    $saldo = max(0, -($fixoNovo + $diferenca));
    $job = $db->prepare("SELECT id FROM trocas_planos WHERE tenant_id=? AND estado<>'concluida' LIMIT 1");
    $job->bind_param('s', $tenantId); $job->execute();
    $emProcessamento = (bool) $job->get_result()->fetch_row();
    $cobrancaBase = max(1, $novoValor);
    $recebimento = (string) $paga['transacao_id'];
    $renovacao = (new DateTimeImmutable($recorrencia['next_payment_date']))->getTimestamp();
    $ref = (string) ($recorrencia['external_reference'] ?? '');
    $ofertaHash = hash('sha256', json_encode([$atual,$destino,$diferenca,$cobrancaBase,$extras,$renovacao,$recebimento,$ref]));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf();
        if (!hash_equals($ofertaHash, (string) ($_POST['oferta'] ?? ''))) throw new RuntimeException('Os valores mudaram. Confira a prévia atualizada.');
        $modo = $diferenca < 0 ? (string) ($_POST['modo'] ?? '') : 'fatura';
        if (!in_array($modo, ['fatura','estorno'], true) || $ref === '' || $renovacao <= time()) throw new RuntimeException('Não foi possível confirmar as condições da troca.');
        if ($modo === 'estorno') {
            $origem = mp_api_request('GET', '/v1/payments/' . rawurlencode($recebimento))['body'] ?? [];
            $disponivel = (int) round(((float) ($origem['transaction_amount'] ?? 0) - (float) ($origem['transaction_amount_refunded'] ?? 0)) * 100);
            if (($origem['status'] ?? '') !== 'approved' || ($origem['external_reference'] ?? '') !== $ref || $disponivel < -$diferenca) {
                throw new RuntimeException('O pagamento do ciclo não permite este estorno. Você pode escolher crédito na fatura.');
            }
        }
        $db->begin_transaction();
        try {
            $lock = $db->prepare('SELECT plano_atual,id_assinatura_mp,usuarios_extras,status_assinatura FROM tenants WHERE tenant_id=? FOR UPDATE');
            $lock->bind_param('s', $tenantId); $lock->execute(); $agora = $lock->get_result()->fetch_assoc();
            $job->execute();
            if ($job->get_result()->fetch_row()) throw new RuntimeException('Já existe uma troca em processamento.');
            if (($agora['status_assinatura'] ?? '') !== 'ativo' || $agora['plano_atual'] !== $atual || $agora['id_assinatura_mp'] !== $assinaturaId || (int) $agora['usuarios_extras'] * 400 !== $extras) throw new RuntimeException('A assinatura mudou. Abra uma nova prévia.');
            $pend = $db->prepare('SELECT COUNT(*) FROM remocoes_usuarios_extras WHERE tenant_id=? AND concluido_em IS NULL');
            $pend->bind_param('s', $tenantId); $pend->execute();
            if ((int) $pend->get_result()->fetch_row()[0] > 0) throw new RuntimeException('Há uma remoção de extra programada para este ciclo.');
            $tenantDb = getTenantConnection();
            if (!$tenantDb) throw new RuntimeException('Não foi possível conferir o limite de usuários.');
            $cap = ['basico'=>3,'plus'=>6,'essencial'=>16][$destino] + (int) $agora['usuarios_extras'];
            $usados = (int) $tenantDb->query("SELECT COUNT(*) FROM usuarios WHERE status='ativo' AND deleted_at IS NULL")->fetch_row()[0];
            if ($usados > $cap) throw new RuntimeException('O novo plano comporta ' . $cap . ' usuários. Ajuste os acessos antes da troca.');
            $piso = max(1, $extras);
            $temporario = $modo === 'estorno' ? $cobrancaBase : max($piso, $cobrancaBase + $diferenca);
            $remanescente = $modo === 'fatura' ? max(0, $temporario - ($cobrancaBase + $diferenca)) : 0;
            $valorEstorno = $modo === 'estorno' ? -$diferenca : 0;
            $chave = bin2hex(random_bytes(16));
            $insert = $db->prepare("INSERT INTO trocas_planos (tenant_id,assinatura_mp,referencia,plano_origem,plano_destino,pagamento_origem,chave,valor_normal,valor_programado,piso,credito_restante,estorno,renovacao) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $insert->bind_param('sssssssiiiiii', $tenantId,$assinaturaId,$ref,$atual,$destino,$recebimento,$chave,$cobrancaBase,$temporario,$piso,$remanescente,$valorEstorno,$renovacao);
            $insert->execute(); $db->commit();
        } catch (Throwable $falha) { $db->rollback(); throw $falha; }
        set_flash_message('success', 'Troca solicitada. O processamento automático vai atualizar sua assinatura existente.');
        header('Location: minha_assinatura.php', true, 303); exit;
    }
} catch (RuntimeException $e) {
    $erro = $e->getMessage();
} catch (Throwable $e) {
    error_log('Falha na prévia da assinatura: ' . get_class($e));
    $erro = 'Não foi possível calcular a prévia agora.';
}
require __DIR__ . '/../includes/header.php';
?>
<main class="container" style="max-width:720px;margin:32px auto;padding:20px">
    <h1>Prévia de troca de plano</h1>
    <?php if ($erro !== null): ?>
        <p role="status"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <p><?= htmlspecialchars($planos[$atual][0]) ?> → <?= htmlspecialchars($planos[$destino][0]) ?></p>
        <p><?= $diasRestantes ?> de <?= $dias ?> dias restantes. Renovação prevista para <?= $fim->format('d/m/Y') ?>.</p>
        <p>Mensalidade já paga neste ciclo: <?= $formatar($valorAtual) ?>.</p>
        <?php if ($valorRecorrencia !== $valorAtual || $valorCatalogoAtual !== $valorAtual): ?>
            <p>Recorrência atual no Mercado Pago: <?= $formatar($valorRecorrencia) ?>. Preço atual no catálogo, com extras: <?= $formatar($valorCatalogoAtual) ?>.</p>
        <?php endif; ?>
        <?php if ($cupom !== null): ?><p>A prévia aplica o desconto ativo ao preço do plano de destino, preservando o valor dos extras.</p><?php endif; ?>
        <p>Valor fixo atual: <?= $formatar($fixoAtual) ?>. Valor fixo do novo plano: <strong><?= $formatar($fixoNovo) ?></strong>.</p>
        <p>Usuários extras cadastrados: <?= (int) ($conta['usuarios_extras'] ?? 0) ?> · <?= $formatar($extras) ?> por mês.</p>
        <p>Mensalidade do novo plano com os extras atuais: <strong><?= $formatar($novoValor) ?></strong>.</p>
        <?php if ($diferenca > 0): ?>
            <p>Diferença proporcional na próxima mensalidade: <?= $formatar($diferenca) ?>. Total estimado: <strong><?= $formatar($proxima) ?></strong>.</p>
        <?php elseif ($diferenca < 0): ?>
            <p>Crédito: <?= $formatar(-$diferenca) ?>. Próxima mensalidade estimada: <strong><?= $formatar($proxima) ?></strong>.</p>
            <?php if ($saldo > 0): ?><p>Crédito remanescente: <?= $formatar($saldo) ?>.</p><?php endif; ?>
            <p>Com estorno, a devolução estimada é de <?= $formatar(-$diferenca) ?> e a próxima mensalidade fica em <?= $formatar($novoValor) ?>.</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($erro === null && !$emProcessamento): ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="oferta" value="<?= htmlspecialchars($ofertaHash, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($diferenca < 0): ?>
            <label><input type="radio" name="modo" value="fatura" required> Usar crédito na próxima fatura</label><br>
            <label><input type="radio" name="modo" value="estorno" required> Estornar a diferença</label><br>
        <?php endif; ?>
        <button type="submit">Confirmar troca de plano</button>
    </form>
    <?php elseif (!empty($emProcessamento)): ?><p role="status">Já existe uma troca em processamento.</p><?php endif; ?>
    <a href="minha_assinatura.php">Voltar para Minha assinatura</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
