<?php
// pages/admin/documento_de_registro_no_sistema.php

// 1. Inicia Sessão e Banco ANTES do HTML
require_once '../../includes/session_init.php';
require_once '../../database.php';

// 2. Verificação de Segurança Corrigida (Aceita Super Admin OU Admin Comum)
$isSuperAdmin = isset($_SESSION['super_admin']);
$isAdmin = isset($_SESSION['perfil']) && $_SESSION['perfil'] === 'admin';

if (!$isSuperAdmin && !$isAdmin) {
    // Redireciona em vez de matar o script, para evitar tela branca
    header("Location: ../login.php");
    exit;
}

$conn = getMasterConnection();

// 3. Lógica de Exclusão Manual
$msg_sistema = '';
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    
    // Busca o caminho do arquivo antes de deletar
    $stmt = $conn->prepare("SELECT caminho_arquivo FROM termos_consentimento WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($doc = $res->fetch_assoc()) {
        $arquivoFisico = __DIR__ . '/../../' . $doc['caminho_arquivo'];
        
        // Deleta arquivo físico
        if (file_exists($arquivoFisico)) {
            unlink($arquivoFisico);
        }
        
        // Deleta do banco
        $del = $conn->prepare("DELETE FROM termos_consentimento WHERE id = ?");
        $del->bind_param("i", $id);
        
        if($del->execute()) {
            $msg_sistema = "<div class='alert-success'>Documento excluído com sucesso!</div>";
        }
    }
}

// 4. Listagem dos Documentos
$sql = "SELECT tc.*, u.nome, u.email 
        FROM termos_consentimento tc 
        JOIN usuarios u ON tc.usuario_id = u.id 
        ORDER BY tc.data_aceite DESC";
$result = $conn->query($sql);

// 5. Inclui o Header Visual
require_once '../../includes/header.php'; 
?>

<style>
    /* Container e Global */
    .admin-container { width: 98%; max-width: 1200px; margin: 20px auto; background: #121212; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
    
    /* Ajuste para o cabeçalho com botão */
    .page-header-flex { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
    .page-header-flex h2 { margin: 0; padding: 0; border: none; color: #00bfff; }
    
    p.desc { color: #aaa; margin-bottom: 20px; }

    /* Tabela Dark */
    table { width: 100%; border-collapse: collapse; background: #1a1a1a; border-radius: 8px; overflow: hidden; }
    th { background-color: #252525; color: #00bfff; padding: 15px; text-align: left; text-transform: uppercase; font-size: 0.85rem; border-bottom: 1px solid #333; }
    td { padding: 15px; border-bottom: 1px solid #2a2a2a; color: #ddd; vertical-align: middle; }
    tr:hover { background-color: #2a2a2a; }

    /* Botões */
    .btn-sm { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; border: none; cursor: pointer; transition: 0.2s; color: white; margin-right: 5px; }
    .btn-primary { background-color: #3498db; }
    .btn-primary:hover { background-color: #2980b9; }
    .btn-danger { background-color: #e74c3c; }
    .btn-danger:hover { background-color: #c0392b; }
    
    /* Botão Voltar */
    .btn-back { background-color: #444; color: #eee; border: 1px solid #555; }
    .btn-back:hover { background-color: #555; color: #fff; border-color: #777; }

    /* Alerta */
    .alert-success { background: rgba(40,167,69,0.2); color: #2ecc71; border: 1px solid #2ecc71; padding: 10px; border-radius: 6px; margin-bottom: 20px; }

    /* Responsivo */
    @media (max-width: 768px) {
        .page-header-flex { flex-direction: column; align-items: flex-start; gap: 10px; }
        .btn-back { width: 100%; justify-content: center; }
        
        table, thead, tbody, th, td, tr { display: block; }
        thead { display: none; }
        tr { margin-bottom: 15px; border: 1px solid #333; padding: 10px; background: #202020; border-radius: 8px; }
        td { display: flex; justify-content: space-between; border: none; padding: 8px 0; text-align: right; }
        td::before { content: attr(data-label); font-weight: bold; color: #00bfff; }
    }
</style>

<div class="admin-container">
    <div class="page-header-flex">
        <h2>Gestão de Termos LGPD</h2>
        <a href="dashboard.php" class="btn-sm btn-back">
            <i class="fas fa-arrow-left"></i> Voltar para Dashboard
        </a>
    </div>

    <p class="desc">Documentos de consentimento e termos de uso aceitos pelos usuários no momento do cadastro.</p>
    
    <?= $msg_sistema ?>

    <?php if ($result && $result->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Usuário</th>
                <th>E-mail</th>
                <th>Data Aceite</th>
                <th>IP Origem</th>
                <th style="text-align: center;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td data-label="Usuário">
                    <strong style="color: #fff;"><?= htmlspecialchars($row['nome']) ?></strong>
                </td>
                <td data-label="E-mail"><?= htmlspecialchars($row['email']) ?></td>
                <td data-label="Data"><?= date('d/m/Y H:i', strtotime($row['data_aceite'])) ?></td>
                <td data-label="IP"><?= htmlspecialchars($row['ip_usuario']) ?></td>
                <td data-label="Ações" style="text-align: center;">
                    <a href="../../<?= $row['caminho_arquivo'] ?>" target="_blank" class="btn-sm btn-primary">
                        <i class="fas fa-file-pdf"></i> Baixar PDF
                    </a>
                    <a href="?delete_id=<?= $row['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('Tem certeza? A exclusão é irreversível e remove o arquivo do servidor.');">
                        <i class="fas fa-trash"></i> Excluir
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #777; border: 1px dashed #333; border-radius: 8px;">
            <i class="fas fa-file-contract" style="font-size: 3rem; margin-bottom: 15px;"></i><br>
            Nenhum termo de consentimento registrado ainda.
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>