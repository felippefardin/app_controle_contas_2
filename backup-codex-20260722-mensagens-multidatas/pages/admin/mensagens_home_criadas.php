<?php
require_once '../../includes/session_init.php';
include('../../database.php');

// Proteção: Apenas super admin
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$master_conn = getMasterConnection();
$msg_feedback = "";

// --- LOGICA DE EXCLUSÃO ---
if (isset($_GET['delete'])) {
    $id_delete = intval($_GET['delete']);
    
    // Buscar arquivo para deletar fisicamente
    $busca_arq = $master_conn->query("SELECT arquivo FROM mensagens_home WHERE id = $id_delete");
    if($row = $busca_arq->fetch_assoc()){
        if(!empty($row['arquivo']) && file_exists("../../assets/uploads/mensagens/" . $row['arquivo'])){
            unlink("../../assets/uploads/mensagens/" . $row['arquivo']);
        }
    }

    $master_conn->query("DELETE FROM mensagens_home WHERE id = $id_delete");
    header("Location: mensagens_home_criadas.php?msg=deleted");
    exit;
}

// --- LÓGICA DE CADASTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_salvar'])) {
    $titulo = $master_conn->real_escape_string($_POST['titulo']);
    $mensagem = $master_conn->real_escape_string($_POST['mensagem']);
    $data_exibicao = $_POST['data_exibicao'];
    $qtd_logins = intval($_POST['quantidade_logins']);
    $link_botao = $master_conn->real_escape_string($_POST['link_botao']);
    $texto_botao = $master_conn->real_escape_string($_POST['texto_botao']);

    // Upload de Arquivo
    $arquivo_nome = NULL;
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === 0) {
        $ext = pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION);
        $novo_nome = uniqid('msg_') . "." . $ext;
        $diretorio = "../../assets/uploads/mensagens/";
        
        if (!is_dir($diretorio)) mkdir($diretorio, 0755, true);

        if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $diretorio . $novo_nome)) {
            $arquivo_nome = $novo_nome;
        }
    }

    $sql = "INSERT INTO mensagens_home (titulo, mensagem, data_exibicao, quantidade_logins, arquivo, link_botao, texto_botao) 
            VALUES ('$titulo', '$mensagem', '$data_exibicao', $qtd_logins, " . ($arquivo_nome ? "'$arquivo_nome'" : "NULL") . ", '$link_botao', '$texto_botao')";

    if ($master_conn->query($sql)) {
        $msg_feedback = "<div class='alert success'><i class='fas fa-check'></i> Mensagem agendada com sucesso!</div>";
    } else {
        $msg_feedback = "<div class='alert error'><i class='fas fa-times'></i> Erro ao salvar: " . $master_conn->error . "</div>";
    }
}

// Listar Mensagens
$mensagens = $master_conn->query("SELECT * FROM mensagens_home ORDER BY data_exibicao DESC, criado_em DESC");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensagens do Sistema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #0e0e0e; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }
        .container { max-width: 1200px; margin: 40px auto; padding: 20px; }
        
        .card { background: #1e1e1e; padding: 25px; border-radius: 8px; border: 1px solid #333; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        h1 { color: #00bfff; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        h2 { color: #fff; border-bottom: 1px solid #333; padding-bottom: 10px; margin-top: 0; font-size: 1.2rem; }

        /* Form Elements */
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #aaa; font-size: 0.9rem; }
        input, textarea, select { width: 100%; padding: 10px; background: #2c2c2c; border: 1px solid #444; color: white; border-radius: 4px; box-sizing: border-box; }
        input:focus, textarea:focus { border-color: #00bfff; outline: none; }
        
        .row { display: flex; gap: 15px; }
        .col { flex: 1; }

        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: white; transition: 0.2s; }
        .btn-primary { background-color: #00bfff; }
        .btn-primary:hover { background-color: #009acd; }
        .btn-danger { background-color: #e74c3c; padding: 5px 10px; font-size: 0.8rem; }
        
        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #252525; color: #00bfff; text-transform: uppercase; font-size: 0.8rem; }
        tr:hover { background: #252525; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; }
        .error { background: rgba(231, 76, 60, 0.2); border: 1px solid #e74c3c; color: #e74c3c; }

        .preview-img { max-width: 50px; max-height: 50px; border-radius: 4px; vertical-align: middle; }
        .btn-voltar { color: #aaa; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .btn-voltar:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn-voltar"><i class="fas fa-arrow-left"></i> Voltar ao Dashboard</a>
        
        <h1><i class="fas fa-bullhorn"></i> Mensagens de Home (Pop-up)</h1>
        <?= $msg_feedback ?>

        <!-- FORMULÁRIO -->
        <div class="card">
            <h2>Nova Mensagem</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col form-group">
                        <label>Título da Mensagem</label>
                        <input type="text" name="titulo" required placeholder="Ex: Manutenção Programada">
                    </div>
                    <div class="col form-group">
                        <label>Data de Exibição</label>
                        <input type="date" name="data_exibicao" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col form-group">
                        <label>Limite de Visualizações por Usuário</label>
                        <input type="number" name="quantidade_logins" value="1" min="1" required title="Quantas vezes o usuário verá este pop-up?">
                    </div>
                </div>

                <div class="form-group">
                    <label>Conteúdo da Mensagem</label>
                    <textarea name="mensagem" rows="4" required placeholder="Digite o texto que aparecerá no modal..."></textarea>
                </div>

                <div class="row">
                    <div class="col form-group">
                        <label>Imagem/Anexo (Opcional)</label>
                        <input type="file" name="arquivo" accept="image/*,application/pdf">
                    </div>
                    <div class="col form-group">
                        <label>Texto do Botão (Ação)</label>
                        <input type="text" name="texto_botao" value="Entendi" placeholder="Ex: Ver Detalhes">
                    </div>
                    <div class="col form-group">
                        <label>Link do Botão (Opcional)</label>
                        <input type="text" name="link_botao" placeholder="https://...">
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" name="btn_salvar" class="btn btn-primary"><i class="fas fa-save"></i> Agendar Mensagem</button>
                </div>
            </form>
        </div>

        <!-- LISTAGEM -->
        <div class="card">
            <h2>Mensagens Cadastradas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Data Exibição</th>
                        <th>Imagem</th>
                        <th>Título</th>
                        <th>Mensagem</th>
                        <th>Limite Views</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($m = $mensagens->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($m['data_exibicao'])) ?></td>
                        <td>
                            <?php if($m['arquivo']): ?>
                                <a href="../../assets/uploads/mensagens/<?= $m['arquivo'] ?>" target="_blank">
                                    <img src="../../assets/uploads/mensagens/<?= $m['arquivo'] ?>" class="preview-img" alt="Anexo">
                                </a>
                            <?php else: ?>
                                <span style="color:#555;">Sem img</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($m['titulo']) ?></td>
                        <td><?= mb_strimwidth(htmlspecialchars($m['mensagem']), 0, 50, "...") ?></td>
                        <td><?= $m['quantidade_logins'] ?>x</td>
                        <td>
                            <a href="?delete=<?= $m['id'] ?>" class="btn btn-danger" onclick="return confirm('Excluir esta mensagem?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>