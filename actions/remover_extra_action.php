<?php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/mercadopago_security.php';
require_tenant_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();
$db = getMasterConnection();
$tenantDb = getTenantConnection();
$tenant = (string) ($_SESSION['tenant_id'] ?? '');
$ator = (int) ($_SESSION['usuario_id'] ?? 0);
$owner = (int) get_data_owner_id();
$usuario = filter_input(INPUT_POST, 'usuario_remover', FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
$anterior = null;
$atualizou = false;
try {
    if (!$db || !$tenantDb || $tenant === '' || $usuario === false || $usuario === null) throw new DomainException('Selecione uma vaga válida.');
    $db->begin_transaction();
    $stmt = $db->prepare('SELECT usuarios_extras, id_assinatura_mp, status_assinatura, plano_atual FROM tenants WHERE tenant_id=? FOR UPDATE');
    $stmt->bind_param('s', $tenant); $stmt->execute(); $conta = $stmt->get_result()->fetch_assoc();
    $trial = ($conta['status_assinatura'] ?? '') === 'trial' && empty($conta['id_assinatura_mp']);
    if (!$trial && (($conta['status_assinatura'] ?? '') !== 'ativo' || empty($conta['id_assinatura_mp']))) {
        throw new DomainException('A remoção programada exige assinatura recorrente ativa.');
    }
    if ($trial && $usuario > 0) {
        throw new DomainException('Durante o teste, libere a vaga em Gerenciar Usuários e depois remova a vaga sem usuário.');
    }
    $trocaPendente = $db->prepare("SELECT id FROM trocas_planos WHERE tenant_id=? AND estado<>'concluida' LIMIT 1");
    $trocaPendente->bind_param('s', $tenant); $trocaPendente->execute();
    if ($trocaPendente->get_result()->fetch_row()) throw new DomainException('Aguarde a conclusão da troca de plano para alterar os extras.');
    $pendentes = $db->prepare('SELECT usuario_id FROM remocoes_usuarios_extras WHERE tenant_id=? AND concluido_em IS NULL');
    $pendentes->bind_param('s', $tenant); $pendentes->execute();
    $agendados = array_map('intval', array_column($pendentes->get_result()->fetch_all(MYSQLI_ASSOC), 'usuario_id'));
    if (count($agendados) >= (int) $conta['usuarios_extras']) throw new DomainException('Todas as vagas extras já estão programadas para remoção.');
    if ($usuario > 0) {
        $validar = $tenantDb->prepare("SELECT id FROM usuarios WHERE id=? AND id NOT IN (?,?) AND COALESCE(is_master,0)=0 AND deleted_at IS NULL AND status='ativo'");
        $validar->bind_param('iii', $usuario, $ator, $owner); $validar->execute();
        if (!$validar->get_result()->fetch_row() || in_array($usuario, $agendados, true)) throw new DomainException('Este usuário não pode ter a remoção agendada.');
    } else {
        $ativos = (int) $tenantDb->query("SELECT COUNT(*) FROM usuarios WHERE status='ativo' AND deleted_at IS NULL")->fetch_row()[0];
        $base = ['basico'=>3,'plus'=>6,'essencial'=>16][$conta['plano_atual']] ?? 3;
        if ($ativos >= $base + (int) $conta['usuarios_extras'] - count(array_filter($agendados, fn ($id) => $id === 0))) {
            if ($trial) throw new DomainException('Durante o teste, libere uma vaga em Gerenciar Usuários antes de removê-la.');
            throw new DomainException('A vaga está ocupada. Selecione o usuário cujo acesso deve terminar ao fim do ciclo.');
        }
    }
    if ($trial) {
        $db->execute_query('UPDATE tenants SET usuarios_extras=usuarios_extras-1 WHERE tenant_id=? AND usuarios_extras>0', [$tenant]);
        if ($db->affected_rows !== 1) throw new RuntimeException('A vaga extra não foi removida.');
        $db->commit();
        set_flash_message('success', 'Vaga extra livre removida do período de teste.');
        header('Location: ../pages/minha_assinatura.php');
        exit;
    }
    $path = '/preapproval/' . rawurlencode($conta['id_assinatura_mp']);
    $mp = mp_api_request('GET', $path)['body'] ?? [];
    $rec = $mp['auto_recurring'] ?? [];
    if (($mp['status'] ?? '') !== 'authorized' || ($rec['currency_id'] ?? '') !== 'BRL' || empty($mp['next_payment_date']) || !is_numeric($rec['transaction_amount'] ?? null)) {
        throw new DomainException('Não foi possível confirmar a data e o valor da próxima cobrança.');
    }
    $fim = new DateTimeImmutable($mp['next_payment_date']);
    if ($fim->getTimestamp() <= time()) throw new DomainException('A próxima cobrança está em processamento. Tente novamente após a renovação.');
    $anterior = (int) round((float) $rec['transaction_amount'] * 100);
    if ($anterior < 400 * ((int) $conta['usuarios_extras'] - count($agendados))) {
        throw new DomainException('O valor no Mercado Pago diverge das vagas contratadas. A remoção não foi agendada.');
    }
    $novo = max(1, $anterior - 400);
    $fimUtc = $fim->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $insert = $db->prepare('INSERT INTO remocoes_usuarios_extras (tenant_id,usuario_id,solicitado_por,efetivar_em) VALUES (?,?,?,?)');
    $insert->bind_param('siis', $tenant, $usuario, $ator, $fimUtc); $insert->execute();
    $put = mp_api_request('PUT', $path, ['auto_recurring'=>['transaction_amount'=>$novo/100,'currency_id'=>'BRL']]);
    $atualizou = true;
    if ((int) round((float) ($put['body']['auto_recurring']['transaction_amount'] ?? 0) * 100) !== $novo) throw new RuntimeException('Valor de renovação não confirmado.');
    $db->commit();
    $atualizou = false;
    set_flash_message('success', 'Remoção agendada para ' . $fim->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i') . '. Próxima mensalidade: R$ ' . number_format($novo/100, 2, ',', '.') . '.');
} catch (Throwable $e) {
    if ($atualizou) {
        try { mp_api_request('PUT', $path, ['auto_recurring'=>['transaction_amount'=>$anterior/100,'currency_id'=>'BRL']]); }
        catch (Throwable $falha) { error_log('Falha ao restaurar recorrência do extra para ' . $tenant); }
    }
    if ($db) $db->rollback();
    error_log('Agendamento de extra: ' . $e->getMessage());
    set_flash_message('danger', $e instanceof DomainException ? $e->getMessage() : 'Não foi possível confirmar a remoção programada.');
}
header('Location: ../pages/minha_assinatura.php', true, 303);
exit;
