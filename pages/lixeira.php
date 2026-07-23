<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php';
require_once '../includes/audit.php';
require_tenant_admin();
$conn = getTenantConnection();
$ownerId = get_data_owner_id();
$items = [];
$queries = [
    'contas_pagar' => ['name' => "COALESCE(descricao, fornecedor, CONCAT('Conta #', id))", 'owner' => 'usuario_id'],
    'contas_receber' => ['name' => "COALESCE(descricao, CONCAT('Conta #', id))", 'owner' => 'usuario_id'],
    'produtos' => ['name' => 'nome', 'owner' => 'id_usuario'],
    'usuarios' => ['name' => "CONCAT(nome, ' - ', email)", 'owner' => null],
];
foreach ($queries as $entity => $config) {
    $sql = "SELECT id, {$config['name']} AS nome, deleted_at, deleted_by FROM `$entity` WHERE deleted_at IS NOT NULL";
    if ($config['owner']) $sql .= " AND `{$config['owner']}`=?";
    $sql .= ' ORDER BY deleted_at DESC';
    $stmt = $conn->prepare($sql);
    if ($config['owner']) $stmt->bind_param('i', $ownerId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $row['entity'] = $entity; $row['tipo'] = SOFT_DELETE_ENTITIES[$entity]['label']; $items[] = $row;
    }
    $stmt->close();
}
usort($items, static fn(array $a, array $b): int => strcmp((string) $b['deleted_at'], (string) $a['deleted_at']));
include '../includes/header.php';
display_flash_message();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Lixeira</title><style>
body{background:#111;color:#eee;font-family:Arial,sans-serif}.trash-wrap{max-width:1100px;margin:35px auto;padding:24px;background:#202020;border-radius:12px}
h1{color:#00bfff}.notice{background:#173047;border-left:4px solid #00bfff;padding:12px;margin-bottom:20px}table{width:100%;border-collapse:collapse}
th,td{padding:12px;border-bottom:1px solid #444;text-align:left}th{color:#00bfff}.restore{border:0;border-radius:6px;padding:9px 13px;background:#28a745;color:white;cursor:pointer}.empty{padding:30px;text-align:center;color:#aaa}
</style></head><body><main class="trash-wrap"><h1>Lixeira</h1><div class="notice">Os registros abaixo não foram apagados definitivamente e podem ser restaurados.</div>
<p><a href="auditoria.php" style="color:#62d4ff">Consultar trilha de auditoria</a></p><?php if (!$items): ?><div class="empty">A lixeira está vazia.</div><?php else: ?><table><thead><tr><th>Tipo</th><th>Registro</th><th>Excluído em</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($items as $item): ?><tr><td><?= htmlspecialchars($item['tipo']) ?></td><td><?= htmlspecialchars($item['nome']) ?></td><td><?= htmlspecialchars($item['deleted_at']) ?></td><td>
<form method="post" action="../actions/restaurar_registro.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>"><input type="hidden" name="entity" value="<?= htmlspecialchars($item['entity']) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="restore">Restaurar</button></form>
</td></tr><?php endforeach; ?></tbody></table><?php endif; ?></main></body></html>
<?php include '../includes/footer.php'; ?>
