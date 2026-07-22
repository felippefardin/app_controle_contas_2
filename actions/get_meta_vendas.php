<?php
require_once '../includes/session_init.php';
require_once '../database.php';

header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['usuario_logado'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não logado.']);
    exit;
}

$conn = getTenantConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com a empresa.']);
    exit;
}

try {
    $meta = $conn->query("SELECT id,valor_meta,inicio_em,atingida_em,criado_em FROM metas_vendas WHERE status='ativa' ORDER BY id DESC LIMIT 1")->fetch_assoc();
    $valorMeta = (float)($meta['valor_meta'] ?? 0);
    $valorAtual = 0.0;

    if ($meta) {
        $stmt = $conn->prepare('SELECT COALESCE(SUM(valor_total),0) AS total FROM vendas WHERE data_venda >= ?');
        $stmt->bind_param('s', $meta['inicio_em']);
        $stmt->execute();
        $valorAtual = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
    }

    $percentual = $valorMeta > 0 ? ($valorAtual / $valorMeta) * 100 : 0;
    echo json_encode([
        'success' => true,
        'meta_id' => (int)($meta['id'] ?? 0),
        'meta' => $valorMeta,
        'atual' => $valorAtual,
        'percentual' => $percentual,
        'atingida' => $valorMeta > 0 && $valorAtual >= $valorMeta,
        'inicio_em' => $meta['inicio_em'] ?? null,
        'atingida_em' => $meta['atingida_em'] ?? null,
        'meta_formatada' => number_format($valorMeta, 2, ',', '.'),
        'atual_formatado' => number_format($valorAtual, 2, ',', '.'),
        'excedente_formatado' => number_format(max(0, $valorAtual - $valorMeta), 2, ',', '.')
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
