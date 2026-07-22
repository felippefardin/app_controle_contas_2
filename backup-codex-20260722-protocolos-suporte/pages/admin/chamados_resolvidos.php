<?php
require_once '../../includes/session_init.php';
include('../../database.php');

// Proteção: Apenas super admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$master_conn = getMasterConnection();

// --- CONSULTA APENAS OS RESOLVIDOS ---
$sql_chamados = "
    SELECT c.*, t.nome_empresa, t.nome as nome_proprietario
    FROM chamados_suporte c
    JOIN tenants t ON c.tenant_id COLLATE utf8mb4_unicode_ci = t.tenant_id COLLATE utf8mb4_unicode_ci
    WHERE c.status = 'concluido'
    ORDER BY c.criado_em DESC 
    LIMIT 50"; 
    
$result_chamados = $master_conn->query($sql_chamados);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Chamados Resolvidos</title>
    <!-- <link rel="stylesheet" href="../../assets/css/style.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #0e0e0e; color: #eee; font-family: Arial, sans-serif; margin: 0; }
        .container { max-width: 1300px; margin: 30px auto; background: #121212; padding: 30px 40px; border-radius: 10px; box-shadow: 0 0 10px rgba(255,255,255,0.05); }
        h1 { margin-bottom: 20px; color: #2ecc71; text-align: center; }
        table { width: 100%; border-collapse: collapse; border-radius: 8px; overflow: hidden; margin-bottom: 40px; }
        th { background-color: #1e1e1e; color: #2ecc71; padding: 12px; text-align: left; font-size: 14px; }
        td { padding: 12px; border-bottom: 1px solid #2a2a2a; vertical-align: top; }
        tr:hover { background-color: rgba(255,255,255,0.03); }
        
        .btn-voltar { background-color: #333; color: #fff; padding: 10px 15px; border-radius: 4px; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .btn-voltar:hover { background-color: #444; }

        /* Status Colors */
        .status-ball { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 5px; }
        .status-concluido { background-color: #2ecc71; box-shadow: 0 0 5px #2ecc71; } /* Verde */

        .btn-action { border: none; border-radius: 4px; padding: 6px 10px; cursor: pointer; font-size: 12px; color: white; text-decoration: none; }
        .btn-excluir { background-color: #c0392b; }
        
        /* Timeline */
        .history-mini { font-size: 13px; margin-top: 10px; border-top: 1px solid #444; padding-top: 8px; }
        .hist-row { margin-bottom: 6px; border-bottom: 1px dashed #333; padding-bottom: 4px; }
        .hist-meta { color: #00bfff; font-size: 11px; font-weight: bold; }
        .hist-msg { color: #ccc; display: block; }
    </style>
</head>
<body>

    <div class="container">
        <a href="dashboard.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar ao Dashboard</a>
        
        <h1><i class="fas fa-archive"></i> Arquivo de Chamados Resolvidos</h1>

        <?php if(isset($_SESSION['msg_suporte'])): ?>
            <div style="padding: 10px; background: rgba(40,167,69,0.2); color: #2ecc71; border-radius: 4px; margin-bottom: 15px;">
                <?= $_SESSION['msg_suporte'] ?>
            </div>
            <?php unset($_SESSION['msg_suporte']); ?>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Empresa</th>
                    <th>Solicitante</th>
                    <th>Tipo</th>
                    <th style="width: 40%;">Detalhes e Histórico</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result_chamados && $result_chamados->num_rows > 0): ?>
                <?php while ($c = $result_chamados->fetch_assoc()): 
                    // Busca histórico para exibir inline
                    $id_chamado = $c['id'];
                    $res_hist = $master_conn->query("SELECT * FROM chamados_historico WHERE chamado_id = $id_chamado ORDER BY criado_em ASC");
                ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center;">
                            <span class="status-ball status-concluido"></span>
                            <span style="font-size: 12px; color: #ccc;">Resolvido</span>
                        </div>
                    </td>
                    <td style="font-weight: bold; color: #fff;">
                        <?= htmlspecialchars($c['nome_empresa'] ?: $c['nome_proprietario']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['usuario_nome']) ?><br>
                        <small style="color:#888;"><?= htmlspecialchars($c['usuario_email']) ?></small>
                    </td>
                    <td><?= ($c['tipo'] == 'chat_aovivo') ? 'Ao Vivo' : 'Online' ?></td>
                    <td>
                        <div style="margin-bottom: 8px;">
                            <strong style="color: #fff;">Descrição Inicial:</strong><br>
                            <?= htmlspecialchars($c['descricao']) ?>
                        </div>

                        <?php if($res_hist && $res_hist->num_rows > 0): ?>
                            <div class="history-mini">
                                <strong style="color: #aaa;">Histórico:</strong>
                                <?php while($h = $res_hist->fetch_assoc()): ?>
                                    <div class="hist-row">
                                        <span class="hist-meta"><?= date('d/m H:i', strtotime($h['criado_em'])) ?> - <?= htmlspecialchars($h['autor_nome']) ?>:</span>
                                        <span class="hist-msg"><?= htmlspecialchars($h['mensagem']) ?></span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Botão Excluir -->
                        <form action="../../actions/admin_suporte.php" method="POST" style="display:inline;" onsubmit="return confirm('Excluir permanentemente?');">
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="origem" value="resolvidos">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-action btn-excluir"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center; padding: 20px; color: #777;">Nenhum chamado arquivado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    </div>
</body>
</html>