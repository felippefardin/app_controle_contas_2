<?php
require_once '../../includes/session_init.php';
require_once '../../database.php';

$isSuperAdmin = !empty($_SESSION['super_admin']);
$isAdmin = ($_SESSION['perfil'] ?? '') === 'admin'
    || in_array($_SESSION['nivel_acesso'] ?? '', ['admin', 'master'], true);
if (!$isSuperAdmin && !$isAdmin) {
    header('Location: ../login.php');
    exit;
}

$admin = $_SESSION['super_admin'] ?? [
    'email' => $_SESSION['email'] ?? $_SESSION['usuario_email'] ?? 'Administrador'
];
$conn = getMasterConnection();
$mensagem = '';
$mensagemTipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare('SELECT caminho_arquivo FROM termos_consentimento WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $documento = $stmt->get_result()->fetch_assoc();

    if ($documento) {
        $nomeArquivo = basename(str_replace('\\', '/', (string)$documento['caminho_arquivo']));
        $arquivo = realpath(__DIR__ . '/../../assets/uploads/contratos_lgpd/' . $nomeArquivo);
        $pastaPermitida = realpath(__DIR__ . '/../../assets/uploads/contratos_lgpd');
        if ($arquivo && $pastaPermitida && str_starts_with($arquivo, $pastaPermitida . DIRECTORY_SEPARATOR)) {
            @unlink($arquivo);
        }
        $del = $conn->prepare('DELETE FROM termos_consentimento WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $mensagem = 'Documento excluído com sucesso.';
    } else {
        $mensagem = 'Documento não encontrado.';
        $mensagemTipo = 'error';
    }
}

$busca = trim($_GET['busca'] ?? '');
$sql = "SELECT tc.*, COALESCE(u.nome, 'Usuário removido') AS nome, COALESCE(u.email, '-') AS email
        FROM termos_consentimento tc
        LEFT JOIN usuarios u ON tc.usuario_id = u.id";
if ($busca !== '') {
    $sql .= ' WHERE u.nome LIKE ? OR u.email LIKE ? OR tc.ip_usuario LIKE ?';
    $sql .= ' ORDER BY tc.data_aceite DESC';
    $stmt = $conn->prepare($sql);
    $termo = '%' . $busca . '%';
    $stmt->bind_param('sss', $termo, $termo, $termo);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql . ' ORDER BY tc.data_aceite DESC');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentos LGPD</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #0e0e0e; color: #eee; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .topbar { width: 100%; background: #1a1a1a; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,.4); position: sticky; top: 0; z-index: 20; }
        .topbar-title { color: #00bfff; font-size: 1.2rem; font-weight: 700; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }
        .topbar-actions a { color: #eee; background: #333; padding: 8px 14px; border-radius: 4px; text-decoration: none; }
        .topbar-actions a:hover { background: #444; }
        .topbar-actions .logout { background: #d13c3c; }
        .container { width: 98%; max-width: 1400px; margin: 22px auto; background: #121212; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,.25); }
        .page-title { display: flex; align-items: center; justify-content: space-between; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 18px; }
        h1 { color: #00bfff; margin: 0; font-size: 1.65rem; }
        .subtitle { color: #aaa; margin: 7px 0 0; }
        .search { display: flex; gap: 10px; margin: 20px 0; }
        .search input { flex: 1; max-width: 480px; padding: 11px 13px; background: #1c1c1c; border: 1px solid #333; border-radius: 5px; color: #fff; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 0; border-radius: 5px; padding: 10px 14px; color: #fff; text-decoration: none; cursor: pointer; font-weight: 600; }
        .btn-blue { background: #00a6dc; } .btn-blue:hover { background: #008fbd; }
        .btn-gray { background: #444; } .btn-gray:hover { background: #555; }
        .btn-red { background: #c0392b; } .btn-red:hover { background: #a93226; }
        .notice { padding: 13px 15px; margin-bottom: 18px; border-radius: 6px; }
        .notice.success { color: #2ecc71; background: rgba(46,204,113,.12); border: 1px solid #2ecc71; }
        .notice.error { color: #ff6b6b; background: rgba(231,76,60,.12); border: 1px solid #e74c3c; }
        .table-wrap { overflow-x: auto; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; background: #1a1a1a; }
        th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #2a2a2a; }
        th { background: #252525; color: #00bfff; text-transform: uppercase; font-size: .82rem; letter-spacing: .4px; }
        tr:hover { background: #202020; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; }
        .empty { text-align: center; padding: 55px 20px; color: #777; border: 1px dashed #3a3a3a; border-radius: 8px; }
        .empty i { display: block; font-size: 3rem; margin-bottom: 12px; }
        @media (max-width: 800px) {
            .topbar { padding: 12px 15px; } .topbar-actions span { display: none; }
            .container { width: 100%; margin: 0; padding: 15px; border-radius: 0; }
            .page-title { align-items: flex-start; flex-direction: column; }
            .search { flex-wrap: wrap; } .search input { min-width: 100%; }
            table, thead, tbody, tr, th, td { display: block; } thead { display: none; }
            tr { margin-bottom: 14px; padding: 12px; border: 1px solid #333; border-radius: 7px; }
            td { border: 0; display: flex; justify-content: space-between; gap: 15px; padding: 8px 0; text-align: right; }
            td::before { content: attr(data-label); color: #00bfff; font-weight: 700; text-align: left; }
            .actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <div class="topbar-title">App Control <span style="color:#fff;font-weight:300">Master</span></div>
    <div class="topbar-actions">
        <span><?= htmlspecialchars($admin['email'] ?? 'Admin') ?></span>
        <a href="dashboard.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>
<main class="container">
    <div class="page-title">
        <div><h1><i class="fas fa-file-signature"></i> Documentos LGPD</h1><p class="subtitle">Termos aceitos e assinados durante o cadastro no sistema.</p></div>
        <a href="dashboard.php" class="btn btn-gray"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <?php if ($mensagem): ?><div class="notice <?= $mensagemTipo ?>"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <form method="get" class="search">
        <input name="busca" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por usuário, e-mail ou IP">
        <button class="btn btn-blue" type="submit"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($busca !== ''): ?><a class="btn btn-gray" href="documento_de_registro_no_sistema.php">Limpar</a><?php endif; ?>
    </form>
    <?php if ($result && $result->num_rows): ?>
    <div class="table-wrap"><table><thead><tr><th>Usuário</th><th>E-mail</th><th>Data do aceite</th><th>IP de origem</th><th style="text-align:right">Ações</th></tr></thead><tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td data-label="Usuário"><strong><?= htmlspecialchars($row['nome']) ?></strong></td>
            <td data-label="E-mail"><?= htmlspecialchars($row['email']) ?></td>
            <td data-label="Data"><?= $row['data_aceite'] ? date('d/m/Y H:i', strtotime($row['data_aceite'])) : '-' ?></td>
            <td data-label="IP"><?= htmlspecialchars($row['ip_usuario'] ?? '-') ?></td>
            <td data-label="Ações"><div class="actions">
                <a class="btn btn-blue" target="_blank" href="../../actions/visualizar_termo_lgpd.php?id=<?= (int)$row['id'] ?>"><i class="fas fa-eye"></i> Visualizar</a>
                <form method="post" onsubmit="return confirm('Excluir permanentemente este documento?')"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-red"><i class="fas fa-trash"></i> Excluir</button></form>
            </div></td>
        </tr>
    <?php endwhile; ?>
    </tbody></table></div>
    <?php else: ?><div class="empty"><i class="fas fa-file-circle-xmark"></i>Nenhum documento encontrado.</div><?php endif; ?>
</main>
</body></html>
