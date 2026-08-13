<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/session_init.php';
require_once __DIR__ . '/../../database.php';

require_super_admin();

$conn = getMasterConnection();
$mensagem = '';
$mensagemTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $status = trim((string) ($_POST['status'] ?? ''));
    $resposta = trim((string) ($_POST['resposta_admin'] ?? ''));
    $statusPermitidos = ['nova', 'em_analise', 'concluida', 'recusada'];

    if (!$id || !in_array($status, $statusPermitidos, true) || mb_strlen($resposta) > 4000) {
        $mensagem = 'Não foi possível atualizar: revise os dados informados.';
        $mensagemTipo = 'error';
    } else {
        $adminEmail = (string) ($_SESSION['super_admin']['email'] ?? 'Master');
        $stmt = $conn->prepare('UPDATE solicitacoes_lgpd SET status = ?, resposta_admin = ?, atualizado_por = ? WHERE id = ?');
        $stmt->bind_param('sssi', $status, $resposta, $adminEmail, $id);
        $stmt->execute();
        $stmt->close();
        $mensagem = 'Solicitação atualizada com sucesso.';
    }
}

$filtro = trim((string) ($_GET['status'] ?? ''));
$busca = trim((string) ($_GET['busca'] ?? ''));
$statusPermitidos = ['nova', 'em_analise', 'concluida', 'recusada'];
$temFiltro = in_array($filtro, $statusPermitidos, true);
$temBusca = $busca !== '';
$wherePartes = [];
if ($temFiltro) $wherePartes[] = 'status = ?';
if ($temBusca) $wherePartes[] = '(protocolo LIKE ? OR email LIKE ? OR telefone LIKE ? OR descricao LIKE ?)';
$where = $wherePartes ? ' WHERE ' . implode(' AND ', $wherePartes) : '';

$sql = 'SELECT * FROM solicitacoes_lgpd' . $where . " ORDER BY FIELD(status, 'nova', 'em_analise', 'concluida', 'recusada'), criado_em DESC";
$stmt = $conn->prepare($sql);
$termoBusca = '%' . $busca . '%';
if ($temFiltro && $temBusca) {
    $stmt->bind_param('sssss', $filtro, $termoBusca, $termoBusca, $termoBusca, $termoBusca);
} elseif ($temFiltro) {
    $stmt->bind_param('s', $filtro);
} elseif ($temBusca) {
    $stmt->bind_param('ssss', $termoBusca, $termoBusca, $termoBusca, $termoBusca);
}
$stmt->execute();
$solicitacoes = $stmt->get_result();

$totais = ['nova' => 0, 'em_analise' => 0, 'concluida' => 0, 'recusada' => 0];
$resTotais = $conn->query('SELECT status, COUNT(*) total FROM solicitacoes_lgpd GROUP BY status');
while ($row = $resTotais->fetch_assoc()) $totais[$row['status']] = (int) $row['total'];

$rotulosTipo = ['acesso' => 'Acesso', 'correcao' => 'Correção', 'exclusao' => 'Exclusão'];
$rotulosStatus = ['nova' => 'Nova', 'em_analise' => 'Em análise', 'concluida' => 'Concluída', 'recusada' => 'Recusada'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../assets/css/universal-theme.css?v=20260813-admin">
    <script src="../../assets/js/global-theme.js?v=20260813-admin"></script>
    <title>Solicitações LGPD | Painel Master</title>
    <link rel="stylesheet" href="../../assets/vendor/fontawesome/css/all.min.css">
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <style>
        :root{--bg:#0d0f13;--card:#171a20;--card2:#20252e;--line:#303744;--text:#edf3fa;--muted:#aab6c5;--blue:#13b9f0;--green:#35c878;--orange:#f1a33b;--red:#ef5b65}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:"Segoe UI",Arial,sans-serif}.master-top{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 24px;background:#171717;border-bottom:1px solid #242a33;position:sticky;top:0;z-index:20}.master-top strong{color:var(--blue);font-size:1.12rem}.back{display:inline-flex;align-items:center;gap:7px;padding:10px 14px;border:1px solid #415067;border-radius:8px;color:#fff;text-decoration:none}.page{width:min(1450px,calc(100% - 28px));margin:28px auto}.heading{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;flex-wrap:wrap}.heading h1{margin:0;font-size:clamp(1.8rem,4vw,2.6rem)}.heading p{margin:7px 0 0;color:var(--muted)}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:24px 0}.metric{padding:18px;border:1px solid var(--line);border-radius:13px;background:var(--card)}.metric span{display:block;color:var(--muted);font-size:.86rem}.metric strong{display:block;margin-top:6px;font-size:1.8rem}.filters{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 20px}.filters a{padding:9px 13px;border:1px solid var(--line);border-radius:999px;color:var(--muted);text-decoration:none;font-weight:700;font-size:.86rem}.filters a.active,.filters a:hover{background:#0c78c8;color:#fff;border-color:#0c78c8}.notice{padding:13px 16px;margin:16px 0;border-radius:10px;background:rgba(53,200,120,.12);border:1px solid var(--green);color:#8de6b6}.notice.error{background:rgba(239,91,101,.12);border-color:var(--red);color:#ff9ca3}.request{margin-bottom:14px;border:1px solid var(--line);border-radius:15px;background:var(--card);overflow:hidden}.request-head{display:grid;grid-template-columns:1.1fr .85fr .85fr .8fr auto;gap:14px;align-items:center;padding:17px 19px;background:var(--card2)}.request-head small{display:block;color:var(--muted);margin-bottom:4px}.request-head strong{overflow-wrap:anywhere}.badge{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:.76rem;font-weight:800}.badge-nova{background:rgba(239,91,101,.16);color:#ff8991}.badge-em_analise{background:rgba(241,163,59,.16);color:#ffc56f}.badge-concluida{background:rgba(53,200,120,.16);color:#72e4a6}.badge-recusada{background:#303744;color:#bdc6d2}.request-toggle{border:0;border-radius:8px;background:#0878d1;color:#fff;padding:10px 13px;cursor:pointer;font-weight:800}.request-body{display:none;padding:20px}.request.open .request-body{display:grid;grid-template-columns:1.1fr .9fr;gap:20px}.description{white-space:pre-wrap;line-height:1.65;color:#d4dde8}.contact-data{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:16px}.contact-data div{padding:12px;border:1px solid var(--line);border-radius:9px;background:#13171d}.contact-data span{display:block;color:var(--muted);font-size:.76rem;margin-bottom:4px}.admin-form{padding:17px;border:1px solid var(--line);border-radius:11px;background:#13171d}.admin-form label{display:block;margin-bottom:7px;font-weight:700}.admin-form select,.admin-form textarea{width:100%;border:1px solid #3c4657;border-radius:8px;background:#202630;color:#fff;padding:11px;font:inherit}.admin-form textarea{min-height:120px;resize:vertical;margin-bottom:12px}.admin-form select{margin-bottom:12px}.save{border:0;border-radius:8px;background:var(--green);color:#07150d;padding:11px 15px;font-weight:900;cursor:pointer}.empty{padding:55px;text-align:center;border:1px dashed var(--line);border-radius:14px;color:var(--muted)}
        .search-form{display:flex;gap:9px;margin:0 0 14px}.search-form input{flex:1;min-width:0;padding:12px 14px;border:1px solid var(--line);border-radius:9px;background:var(--card);color:var(--text);font:inherit}.search-form input:focus{outline:none;border-color:#0878d1;box-shadow:0 0 0 3px rgba(8,120,209,.14)}.search-form button,.search-clear{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 16px;border:0;border-radius:9px;background:#0878d1;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.search-clear{background:#343d4b}
        @media(max-width:900px){.metrics{grid-template-columns:repeat(2,1fr)}.request-head{grid-template-columns:1fr 1fr}.request-head>div:nth-child(3),.request-head>div:nth-child(4){display:none}.request.open .request-body{grid-template-columns:1fr}}
        @media(max-width:560px){.master-top{padding:13px}.page{width:min(100% - 18px,1450px);margin:18px auto}.metrics{grid-template-columns:1fr 1fr}.metric{padding:14px}.contact-data{grid-template-columns:1fr}.request-head{grid-template-columns:1fr auto}.request-head>div:nth-child(2){display:none}}
    </style>
</head>
<body>
<header class="master-top"><strong><i class="fas fa-user-shield"></i> App Control Master</strong><a class="back" href="dashboard.php"><i class="fas fa-arrow-left"></i> Voltar ao painel</a></header>
<main class="page">
    <div class="heading"><div><h1>Solicitações de Privacidade</h1><p>Acompanhe pedidos de acesso, correção e exclusão de dados.</p></div></div>
    <?php if ($mensagem !== ''): ?><div class="notice <?= $mensagemTipo === 'error' ? 'error' : '' ?>"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <section class="metrics" aria-label="Resumo das solicitações">
        <div class="metric"><span>Novas</span><strong><?= $totais['nova'] ?></strong></div>
        <div class="metric"><span>Em análise</span><strong><?= $totais['em_analise'] ?></strong></div>
        <div class="metric"><span>Concluídas</span><strong><?= $totais['concluida'] ?></strong></div>
        <div class="metric"><span>Recusadas</span><strong><?= $totais['recusada'] ?></strong></div>
    </section>
    <form class="search-form" method="get" role="search">
        <?php if ($temFiltro): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filtro, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        <input type="search" name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por protocolo, e-mail, telefone ou descrição" aria-label="Buscar solicitações">
        <button type="submit"><i class="fas fa-search" aria-hidden="true"></i> Buscar</button>
        <?php if ($temBusca): ?><a class="search-clear" href="solicitacoes_lgpd.php<?= $temFiltro ? '?status=' . urlencode($filtro) : '' ?>"><i class="fas fa-xmark" aria-hidden="true"></i> Limpar</a><?php endif; ?>
    </form>
    <nav class="filters" aria-label="Filtrar solicitações">
        <a class="<?= $filtro === '' ? 'active' : '' ?>" href="solicitacoes_lgpd.php<?= $temBusca ? '?busca=' . urlencode($busca) : '' ?>">Todas</a>
        <?php foreach ($rotulosStatus as $valor => $rotulo): ?><a class="<?= $filtro === $valor ? 'active' : '' ?>" href="?status=<?= urlencode($valor) ?><?= $temBusca ? '&amp;busca=' . urlencode($busca) : '' ?>"><?= htmlspecialchars($rotulo) ?></a><?php endforeach; ?>
    </nav>
    <?php if ($solicitacoes->num_rows === 0): ?><div class="empty"><i class="fas fa-inbox fa-2x"></i><p>Nenhuma solicitação encontrada.</p></div><?php endif; ?>
    <?php while ($s = $solicitacoes->fetch_assoc()): ?>
    <article class="request">
        <div class="request-head">
            <div><small>Protocolo</small><strong><?= htmlspecialchars($s['protocolo']) ?></strong></div>
            <div><small>Tipo</small><strong><?= htmlspecialchars($rotulosTipo[$s['tipo']] ?? $s['tipo']) ?></strong></div>
            <div><small>Solicitante</small><strong><?= htmlspecialchars($s['email']) ?></strong></div>
            <div><small>Recebida em</small><strong><?= date('d/m/Y H:i', strtotime($s['criado_em'])) ?></strong></div>
            <div><span class="badge badge-<?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars($rotulosStatus[$s['status']] ?? $s['status']) ?></span> <button class="request-toggle" type="button" aria-expanded="false">Detalhes</button></div>
        </div>
        <div class="request-body">
            <div>
                <div class="contact-data"><div><span>E-mail</span><?= htmlspecialchars($s['email']) ?></div><div><span>Telefone/WhatsApp</span><?= htmlspecialchars($s['telefone']) ?></div><div><span>Usuário vinculado</span><?= $s['usuario_id'] ? '#' . (int)$s['usuario_id'] : 'Não identificado' ?></div><div><span>Tenant</span><?= htmlspecialchars($s['tenant_id'] ?: 'Não identificado') ?></div></div>
                <h2>Descrição</h2><div class="description"><?= htmlspecialchars($s['descricao']) ?></div>
            </div>
            <form class="admin-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <label for="status-<?= (int)$s['id'] ?>">Status</label>
                <select id="status-<?= (int)$s['id'] ?>" name="status"><?php foreach ($rotulosStatus as $valor => $rotulo): ?><option value="<?= $valor ?>" <?= $s['status'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option><?php endforeach; ?></select>
                <label for="resposta-<?= (int)$s['id'] ?>">Anotação ou resposta do Master</label>
                <textarea id="resposta-<?= (int)$s['id'] ?>" name="resposta_admin" maxlength="4000" placeholder="Registre a análise e as providências adotadas."><?= htmlspecialchars($s['resposta_admin'] ?? '') ?></textarea>
                <button class="save" type="submit"><i class="fas fa-save"></i> Salvar atualização</button>
            </form>
        </div>
    </article>
    <?php endwhile; ?>
</main>
<script>
document.querySelectorAll('.request-toggle').forEach(button=>button.addEventListener('click',()=>{
    const card=button.closest('.request'); const open=card.classList.toggle('open');
    button.setAttribute('aria-expanded',open?'true':'false'); button.textContent=open?'Fechar':'Detalhes';
}));
</script>
</body>
</html>
