<?php
require_once '../includes/session_init.php';
require_once '../database.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_logado'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado.']);
    exit;
}

$perfil = $_SESSION['nivel_acesso'] ?? 'padrao';
if (!in_array($perfil, ['admin', 'master', 'proprietario'], true)) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$conn = getTenantConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com a empresa.']);
    exit;
}

$valorInformado = trim((string)($_POST['meta'] ?? ''));
$valorMeta = (float)str_replace(',', '.', str_replace('.', '', $valorInformado));
if ($valorMeta <= 0) {
    echo json_encode(['success' => false, 'message' => 'Informe um valor de meta maior que zero.']);
    exit;
}

$usuarioCriador = (int)($_SESSION['usuario_id'] ?? 0);

try {
    $conn->begin_transaction();

    $ativa = $conn->query("SELECT id,inicio_em FROM metas_vendas WHERE status='ativa' ORDER BY id DESC LIMIT 1 FOR UPDATE")->fetch_assoc();
    if ($ativa) {
        $stmtTotal = $conn->prepare('SELECT COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE data_venda >= ? AND data_venda < NOW()');
        $stmtTotal->bind_param('s', $ativa['inicio_em']);
        $stmtTotal->execute();
        $totalFinal = (float)($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtTotal->close();

        $stmtFecha = $conn->prepare("UPDATE metas_vendas SET status='encerrada',encerrada_em=NOW(),valor_final=? WHERE id=?");
        $stmtFecha->bind_param('di', $totalFinal, $ativa['id']);
        $stmtFecha->execute();
        $stmtFecha->close();
    }

    $stmtNova = $conn->prepare("INSERT INTO metas_vendas (valor_meta,inicio_em,status,criado_por) VALUES (?,NOW(),'ativa',?)");
    $stmtNova->bind_param('di', $valorMeta, $usuarioCriador);
    $stmtNova->execute();
    $novaId = $conn->insert_id;
    $stmtNova->close();

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => $ativa ? 'Meta anterior arquivada e novo ciclo iniciado!' : 'Meta criada e ciclo iniciado!',
        'meta_id' => $novaId
    ]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar a meta: '.$e->getMessage()]);
} finally {
    $conn->close();
}
