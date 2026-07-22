<?php
require_once '../../includes/session_init.php';
require_once '../../database.php';

if (empty($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$conn = getMasterConnection();
$busca = trim($_GET['busca'] ?? '');
$tipoFiltro = $_GET['tipo'] ?? 'todos';
$tiposValidos = ['todos', 'interno', 'externo', 'online'];
if (!in_array($tipoFiltro, $tiposValidos, true)) $tipoFiltro = 'todos';
$like = '%' . $busca . '%';
$registros = [];

if ($tipoFiltro === 'todos' || $tipoFiltro === 'interno') {
    $sql = "SELECT c.id,c.protocolo,c.usuario_nome AS nome,c.usuario_email AS contato,c.descricao,
                   c.encerrado_em AS encerrado,t.nome_empresa
              FROM chamados_suporte c
         LEFT JOIN tenants t ON t.tenant_id COLLATE utf8mb4_unicode_ci=c.tenant_id COLLATE utf8mb4_unicode_ci
             WHERE c.status='concluido'
               AND (?='' OR c.protocolo LIKE ? OR c.usuario_nome LIKE ? OR c.usuario_email LIKE ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $busca, $like, $like, $like);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $r['tipo'] = 'interno'; $r['canal'] = 'Chat interno'; $registros[] = $r;
    }
}

if ($tipoFiltro === 'todos' || $tipoFiltro === 'externo') {
    $sql = "SELECT id,protocolo,nome,COALESCE(NULLIF(email,''),whatsapp) AS contato,descricao,
                   resolvido_em AS encerrado,canal_preferido AS canal,NULL AS nome_empresa
              FROM suporte_login
             WHERE status IN ('resolvido','fechado')
               AND (?='' OR protocolo LIKE ? OR nome LIKE ? OR email LIKE ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $busca, $like, $like, $like);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $r['tipo'] = 'externo'; $registros[] = $r;
    }
}

if ($tipoFiltro === 'todos' || $tipoFiltro === 'online') {
    $sql = "SELECT cs.id,cs.protocolo,u.nome,COALESCE(u.email,'') AS contato,
                   'Conversa de atendimento online' AS descricao,cs.closed_at AS encerrado,
                   'Chat online' AS canal,NULL AS nome_empresa
              FROM chat_sessions cs
         LEFT JOIN usuarios u ON u.id=cs.usuario_id
             WHERE cs.status='closed' AND cs.closed_at IS NOT NULL
               AND (?='' OR cs.protocolo LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $busca, $like, $like, $like);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $r['tipo'] = 'online'; $registros[] = $r;
    }
}

usort($registros, fn($a, $b) => strcmp((string)$b['encerrado'], (string)$a['encerrado']));
$msg = $_GET['msg'] ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Arquivo de Suportes</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{margin:0;background:#0e0e0e;color:#eee;font-family:Arial,sans-serif}.container{max-width:1200px;margin:28px auto;padding:26px;background:#151515;border-radius:12px}h1{color:#00bfff}.toolbar{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}input,select{background:#222;color:#fff;border:1px solid #444;padding:11px;border-radius:6px}.grow{flex:1;min-width:230px}.btn{border:0;border-radius:6px;padding:10px 14px;color:#fff;text-decoration:none;cursor:pointer;background:#087ea4}.back{background:#444}.pdf{background:#1769aa}.trash{background:#a93226}.trash:disabled{opacity:.35;cursor:not-allowed}table{width:100%;border-collapse:collapse;background:#1c1c1c}th,td{padding:12px;border-bottom:1px solid #333;text-align:left}th{color:#00bfff;background:#222}.badge{padding:5px 8px;border-radius:12px;font-size:12px}.interno{background:#6f42c1}.externo{background:#198754}.online{background:#0d6efd}.muted{color:#aaa;font-size:12px}.ok{padding:12px;background:#173f29;color:#5fe08a;margin-bottom:12px;border-radius:6px}@media(max-width:800px){table{display:block;overflow:auto}}
</style>
</head>
<body><main class="container">
<a class="btn back" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
<h1><i class="fas fa-box-archive"></i> Arquivo unificado de suporte</h1>
<p>Atendimentos internos, externos e conversas do chat online.</p>
<?php if ($msg === 'excluido'): ?><div class="ok">Registro excluído conforme a política de retenção.</div><?php endif; ?>
<form class="toolbar" method="get">
<input class="grow" name="busca" value="<?=htmlspecialchars($busca, ENT_QUOTES, 'UTF-8')?>" placeholder="Buscar protocolo, nome ou contato">
<select name="tipo">
<option value="todos">Todos</option>
<option value="interno" <?=$tipoFiltro==='interno'?'selected':''?>>Internos</option>
<option value="externo" <?=$tipoFiltro==='externo'?'selected':''?>>Externos</option>
<option value="online" <?=$tipoFiltro==='online'?'selected':''?>>Chats online</option>
</select>
<button class="btn"><i class="fas fa-search"></i> Buscar</button>
</form>
<table><thead><tr><th>Tipo</th><th>Protocolo</th><th>Solicitante</th><th>Canal</th><th>Encerrado</th><th>Retenção</th><th>Ações</th></tr></thead><tbody>
<?php if (!$registros): ?><tr><td colspan="7" style="text-align:center;padding:30px">Nenhum atendimento arquivado.</td></tr><?php endif; ?>
<?php foreach ($registros as $r):
    $enc = $r['encerrado'] ? new DateTime($r['encerrado']) : null;
    $exp = $enc ? (clone $enc)->modify('+5 years') : null;
    $pode = $exp && $exp <= new DateTime();
    $rotulo = $r['tipo'] === 'interno' ? 'Assinante' : ($r['tipo'] === 'externo' ? 'Não assinante' : 'Chat online');
?>
<tr>
<td><span class="badge <?=$r['tipo']?>"><?=$rotulo?></span></td>
<td><strong><?=htmlspecialchars($r['protocolo'] ?: '-', ENT_QUOTES, 'UTF-8')?></strong></td>
<td><?=htmlspecialchars($r['nome'] ?: 'Usuário não localizado', ENT_QUOTES, 'UTF-8')?><div class="muted"><?=htmlspecialchars($r['nome_empresa'] ?: $r['contato'], ENT_QUOTES, 'UTF-8')?></div></td>
<td><?=htmlspecialchars(ucfirst($r['canal']), ENT_QUOTES, 'UTF-8')?></td>
<td><?=$enc ? $enc->format('d/m/Y H:i') : '-'?></td>
<td><?=$exp ? $exp->format('d/m/Y') : '-'?><div class="muted"><?=$pode ? 'Exclusão permitida' : 'Protegido'?></div></td>
<td><a class="btn pdf" target="_blank" href="../../actions/gerar_pdf_chat.php?tipo=<?=$r['tipo']?>&amp;id=<?=$r['id']?>" title="Baixar conversa em PDF"><i class="fas fa-file-pdf"></i></a>
<form method="post" action="../../actions/excluir_arquivo_suporte.php" style="display:inline" onsubmit="return confirm('Excluir permanentemente este histórico?')"><input type="hidden" name="id" value="<?=$r['id']?>"><input type="hidden" name="tipo" value="<?=$r['tipo']?>"><button class="btn trash" <?=$pode?'':'disabled title="Disponível somente após cinco anos"'?>> <i class="fas fa-trash"></i></button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></main></body></html>
