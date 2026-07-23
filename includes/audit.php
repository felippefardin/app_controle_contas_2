<?php

declare(strict_types=1);

const SOFT_DELETE_ENTITIES = [
    'contas_pagar' => ['owner' => 'usuario_id', 'label' => 'Conta a pagar'],
    'contas_receber' => ['owner' => 'usuario_id', 'label' => 'Conta a receber'],
    'produtos' => ['owner' => 'id_usuario', 'label' => 'Produto'],
    'usuarios' => ['owner' => null, 'label' => 'Usuário'],
];

function audit_sanitize(?array $data): ?array
{
    if ($data === null) return null;
    foreach (['senha', 'password', 'db_password', 'token_reset', 'token_expira_em', 'token_recovery', 'token_expiry'] as $field) {
        unset($data[$field]);
    }
    return $data;
}

function audit_write(mysqli $db, string $action, string $entityType, int|string|null $entityId, ?array $before = null, ?array $after = null, array $metadata = []): void
{
    $actorId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    $actorName = trim((string) ($_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['email'] ?? 'Sistema'));
    $entityId = $entityId === null ? null : (string) $entityId;
    $before = audit_sanitize($before);
    $after = audit_sanitize($after);
    $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
    $agentHash = !empty($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', (string) $_SERVER['HTTP_USER_AGENT']) : null;
    $stmt = $db->prepare('INSERT INTO audit_logs
        (actor_id, actor_name, action, entity_type, entity_id, before_data, after_data, metadata, ip_address, user_agent_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isssssssss', $actorId, $actorName, $action, $entityType, $entityId, $beforeJson, $afterJson, $metadataJson, $ip, $agentHash);
    $stmt->execute();
    $stmt->close();
}

function soft_delete_record(mysqli $db, string $entity, int $id, ?int $ownerId = null): bool
{
    if (!isset(SOFT_DELETE_ENTITIES[$entity])) throw new InvalidArgumentException('Tipo de registro não permitido.');
    $config = SOFT_DELETE_ENTITIES[$entity];
    $ownerColumn = $config['owner'];
    $sql = "SELECT * FROM `$entity` WHERE id=? AND deleted_at IS NULL";
    if ($ownerColumn !== null) $sql .= " AND `$ownerColumn`=?";
    $select = $db->prepare($sql . ' LIMIT 1');
    if ($ownerColumn !== null) $select->bind_param('ii', $id, $ownerId); else $select->bind_param('i', $id);
    $select->execute(); $before = $select->get_result()->fetch_assoc(); $select->close();
    if (!$before) return false;

    $actorId = (int) ($_SESSION['usuario_id'] ?? 0);
    $sql = "UPDATE `$entity` SET deleted_at=NOW(), deleted_by=? WHERE id=? AND deleted_at IS NULL";
    if ($ownerColumn !== null) $sql .= " AND `$ownerColumn`=?";
    $update = $db->prepare($sql);
    if ($ownerColumn !== null) $update->bind_param('iii', $actorId, $id, $ownerId); else $update->bind_param('ii', $actorId, $id);
    $update->execute(); $changed = $update->affected_rows === 1; $update->close();
    if ($changed) audit_write($db, 'soft_delete', $entity, $id, $before, ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $actorId]);
    return $changed;
}

function restore_soft_deleted_record(mysqli $db, string $entity, int $id, ?int $ownerId = null): bool
{
    if (!isset(SOFT_DELETE_ENTITIES[$entity])) throw new InvalidArgumentException('Tipo de registro não permitido.');
    $ownerColumn = SOFT_DELETE_ENTITIES[$entity]['owner'];
    $sql = "SELECT * FROM `$entity` WHERE id=? AND deleted_at IS NOT NULL";
    if ($ownerColumn !== null) $sql .= " AND `$ownerColumn`=?";
    $select = $db->prepare($sql . ' LIMIT 1');
    if ($ownerColumn !== null) $select->bind_param('ii', $id, $ownerId); else $select->bind_param('i', $id);
    $select->execute(); $before = $select->get_result()->fetch_assoc(); $select->close();
    if (!$before) return false;
    $sql = "UPDATE `$entity` SET deleted_at=NULL, deleted_by=NULL WHERE id=?";
    if ($ownerColumn !== null) $sql .= " AND `$ownerColumn`=?";
    $update = $db->prepare($sql);
    if ($ownerColumn !== null) $update->bind_param('ii', $id, $ownerId); else $update->bind_param('i', $id);
    $update->execute(); $changed = $update->affected_rows === 1; $update->close();
    if ($changed) audit_write($db, 'restore', $entity, $id, $before, ['deleted_at' => null, 'deleted_by' => null]);
    return $changed;
}
