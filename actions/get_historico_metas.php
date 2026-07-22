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
    $sql = "SELECT m.id,m.valor_meta,m.inicio_em,m.atingida_em,m.encerrada_em,m.valor_final,m.status,m.criado_em,u.nome AS criado_por_nome,
                   CASE WHEN m.status='ativa' THEN (SELECT COALESCE(SUM(v.valor_total),0) FROM vendas v WHERE v.data_venda>=m.inicio_em) ELSE COALESCE(m.valor_final,0) END AS realizado
              FROM metas_vendas m
         LEFT JOIN usuarios u ON u.id=m.criado_por
          ORDER BY m.id DESC LIMIT 50";
    $itens = [];
    foreach ($conn->query($sql) as $row) {
        $realizado = (float)$row['realizado'];
        $meta = (float)$row['valor_meta'];
        $itens[] = [
            'id' => (int)$row['id'],
            'meta' => number_format($meta, 2, ',', '.'),
            'realizado' => number_format($realizado, 2, ',', '.'),
            'percentual' => $meta > 0 ? number_format(($realizado / $meta) * 100, 1, ',', '.') : '0,0',
            'inicio' => date('d/m/Y H:i', strtotime($row['inicio_em'])),
            'atingida' => $row['atingida_em'] ? date('d/m/Y H:i', strtotime($row['atingida_em'])) : null,
            'encerramento' => $row['encerrada_em'] ? date('d/m/Y H:i', strtotime($row['encerrada_em'])) : null,
            'status' => $row['status'],
            'criador' => $row['criado_por_nome'] ?: 'Sistema'
        ];
    }
    echo json_encode(['success' => true, 'metas' => $itens]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
