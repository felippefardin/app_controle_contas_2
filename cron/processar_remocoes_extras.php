<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('APP_SKIP_SESSION', true);
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/audit.php';
$db = getMasterConnection();
$ids = array_column($db->query('SELECT id FROM remocoes_usuarios_extras WHERE concluido_em IS NULL AND efetivar_em<=UTC_TIMESTAMP() ORDER BY id LIMIT 100')->fetch_all(MYSQLI_ASSOC), 'id');
if (in_array('--verificar', $argv, true)) { echo count($ids) . " remoções prontas para processamento.\n"; exit; }
foreach ($ids as $id) {
    try {
        $db->begin_transaction();
        $stmt = $db->prepare('SELECT r.*, t.db_host,t.db_user,t.db_password,t.db_database,t.usuarios_extras FROM remocoes_usuarios_extras r JOIN tenants t ON BINARY t.tenant_id=BINARY r.tenant_id WHERE r.id=? AND r.concluido_em IS NULL FOR UPDATE');
        $stmt->bind_param('i', $id); $stmt->execute(); $job = $stmt->get_result()->fetch_assoc();
        if (!$job) { $db->rollback(); continue; }
        if ((int) $job['usuarios_extras'] < 1) throw new RuntimeException('Quantidade de vagas inconsistente.');
        $usuario = (int) $job['usuario_id'];
        if ($usuario > 0) {
            $empresa = new mysqli($job['db_host'], $job['db_user'], $job['db_password'], $job['db_database']);
            $empresa->set_charset('utf8mb4');
            $empresa->begin_transaction();
            try {
                $conta = $empresa->prepare('SELECT COALESCE(is_master,0), deleted_at FROM usuarios WHERE id=? FOR UPDATE');
                $conta->bind_param('i', $usuario); $conta->execute(); $registro = $conta->get_result()->fetch_row();
                if ($registro && (int) $registro[0] !== 0) throw new RuntimeException('Usuário protegido.');
                if ($registro && $registro[1] === null) {
                    $_SESSION['usuario_id'] = (int) $job['solicitado_por'];
                    if (!soft_delete_record($empresa, 'usuarios', $usuario)) throw new RuntimeException('Falha ao remover usuário.');
                }
                $empresa->commit();
            } catch (Throwable $erro) { $empresa->rollback(); throw $erro; }
            finally { $empresa->close(); }
        }
        $update = $db->prepare('UPDATE tenants SET usuarios_extras=usuarios_extras-1 WHERE tenant_id=?');
        $update->bind_param('s', $job['tenant_id']); $update->execute();
        $done = $db->prepare('UPDATE remocoes_usuarios_extras SET concluido_em=UTC_TIMESTAMP() WHERE id=?');
        $done->bind_param('i', $id); $done->execute(); $db->commit();
    } catch (Throwable $e) { $db->rollback(); error_log('Remoção agendada de extra #' . $id . ': ' . $e->getMessage()); }
}
