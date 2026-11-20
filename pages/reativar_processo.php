<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';

$token = trim((string)($_GET['token'] ?? ''));
$valid = false;
$deadline = '';
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $db = getMasterConnection();
    $stmt = $db->prepare("SELECT t.exclusao_programada_em FROM usuarios u JOIN tenants t ON t.tenant_id=u.tenant_id AND LOWER(t.admin_email)=LOWER(u.email) WHERE u.token_reset=? AND u.token_expira_em>NOW() AND t.status_assinatura='desativado_temporario' AND t.exclusao_programada_em>NOW() LIMIT 1");
    $stmt->bind_param('s', $hash); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); $db->close();
    $valid = (bool)$row;
    if ($row) $deadline = date('d/m/Y', strtotime((string)$row['exclusao_programada_em']));
}
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reativar conta</title><link rel="stylesheet" href="../assets/css/universal-theme.css"><script src="../assets/js/global-theme.js"></script><link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css"><style>body{min-height:100vh;margin:0;display:grid;place-items:center;padding:20px;font-family:Segoe UI,Arial,sans-serif}.reactivation-card{width:min(520px,100%);padding:32px;border:1px solid var(--app-border);border-radius:18px;background:var(--app-surface);color:var(--app-text);box-shadow:var(--app-shadow);text-align:center}.reactivation-card i{font-size:2.4rem;color:var(--app-accent)}.reactivation-card h1{margin:16px 0 10px}.reactivation-card p{color:var(--app-muted);line-height:1.6}.reactivation-card button,.reactivation-card a{display:inline-flex;justify-content:center;width:100%;box-sizing:border-box;margin-top:14px;padding:13px;border:0;border-radius:9px;background:#0b8f55;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}</style></head><body><main class="reactivation-card"><?php if($valid): ?><i class="fas fa-box-open"></i><h1>Seus dados estão preservados</h1><p>Ao confirmar, todos os usuários e cadastros da conta serão disponibilizados novamente. Para impedir novas cobranças sem autorização, você escolherá o plano na próxima entrada.</p><p>Prazo atual de preservação: <strong><?= htmlspecialchars($deadline) ?></strong>.</p><form action="../actions/confirmar_reativacao.php" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token,ENT_QUOTES,'UTF-8') ?>"><button type="submit"><i class="fas fa-rotate-right"></i>&nbsp; Confirmar reativação</button></form><?php else: ?><i class="fas fa-link-slash"></i><h1>Link inválido ou expirado</h1><p>Solicite um novo link na tela de login. Contas que ultrapassaram o prazo de um ano não podem ser recuperadas.</p><a href="login.php">Voltar ao login</a><?php endif; ?></main></body></html>
