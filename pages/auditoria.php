<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/audit.php';
require_tenant_admin();
$conn = getTenantConnection();
$action = trim((string) ($_GET['action'] ?? ''));
$entity = trim((string) ($_GET['entity'] ?? ''));
$where = []; $types = ''; $values = [];
if ($action !== '') { $where[] = 'action=?'; $types .= 's'; $values[] = $action; }
if ($entity !== '') { $where[] = 'entity_type=?'; $types .= 's'; $values[] = $entity; }
$sql = 'SELECT id, actor_name, action, entity_type, entity_id, ip_address, created_at FROM audit_logs';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY id DESC LIMIT 500';
$stmt = $conn->prepare($sql);
if (count($values) === 1) $stmt->bind_param($types, $values[0]);
if (count($values) === 2) $stmt->bind_param($types, $values[0], $values[1]);
$stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
include '../includes/header.php';
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auditoria</title>
<style>body{background:#111;color:#eee;font-family:Arial}.audit{max-width:1200px;margin:35px auto;padding:24px;background:#202020;border-radius:12px}h1{color:#00bfff}form.filters{display:flex;gap:10px;margin:20px 0}select,button{padding:10px;border-radius:6px;border:1px solid #555;background:#303030;color:#fff}table{width:100%;border-collapse:collapse}th,td{padding:11px;border-bottom:1px solid #444;text-align:left}th{color:#00bfff}.links a{color:#62d4ff;margin-right:15px}</style></head><body><main class="audit"><h1>Trilha de auditoria</h1><div class="links"><a href="lixeira.php">Abrir lixeira</a><a href="usuarios.php">Voltar</a></div>
<form class="filters"><select name="action"><option value="">Todas as ações</option><option value="soft_delete" <?= $action==='soft_delete'?'selected':'' ?>>Exclusões</option><option value="restore" <?= $action==='restore'?'selected':'' ?>>Restaurações</option></select><select name="entity"><option value="">Todos os tipos</option><?php foreach(SOFT_DELETE_ENTITIES as $key=>$config): ?><option value="<?= $key ?>" <?= $entity===$key?'selected':'' ?>><?= htmlspecialchars($config['label']) ?></option><?php endforeach; ?></select><button>Filtrar</button></form>
<table><thead><tr><th>Data</th><th>Responsável</th><th>Ação</th><th>Tipo</th><th>ID</th><th>IP</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= htmlspecialchars($row['created_at']) ?></td><td><?= htmlspecialchars($row['actor_name']) ?></td><td><?= $row['action']==='restore'?'Restauração':'Exclusão' ?></td><td><?= htmlspecialchars(SOFT_DELETE_ENTITIES[$row['entity_type']]['label'] ?? $row['entity_type']) ?></td><td><?= htmlspecialchars($row['entity_id']) ?></td><td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></main></body></html><?php include '../includes/footer.php'; ?>
