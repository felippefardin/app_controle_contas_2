<?php
require_once '../../includes/session_init.php';
include('../../database.php');

// 🔒 Proteção: Apenas super admin pode acessar
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$master_conn = getMasterConnection();
$msg_feedback = '';
$msg_tipo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf();

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_POST['acao']) && $_POST['acao'] == 'excluir') {
    $id_user = intval($_POST['id_usuario']);
    // Evita que o admin se exclua
    // Usa o ID da sessão do super admin
    $my_id = $_SESSION['user_id'] ?? 0; 

    if ($id_user != $my_id) {
        // Remove admin
        $stmt = $master_conn->prepare("DELETE FROM usuarios WHERE id = ? AND (is_master = 1 OR role = 'super_admin')");
        $stmt->bind_param("i", $id_user);
        
        if ($stmt->execute()) {
            $msg_feedback = "Super Admin #$id_user excluído com sucesso!";
            $msg_tipo = 'sucesso';
        } else {
            $msg_feedback = "Erro ao excluir: " . $master_conn->error;
            $msg_tipo = 'erro';
        }
        $stmt->close();
    } else {
        $msg_feedback = "Você não pode excluir sua própria conta por aqui.";
        $msg_tipo = 'erro';
    }
}

// --- LÓGICA DE EDIÇÃO ---
if (isset($_POST['acao']) && $_POST['acao'] == 'editar') {
    $id_user = intval($_POST['id_usuario']);
    $nome = $master_conn->real_escape_string($_POST['nome']);
    $email = $master_conn->real_escape_string($_POST['email']);
    $status = $master_conn->real_escape_string($_POST['status']);
    $nova_senha = $_POST['nova_senha'];

    $sql_update = "UPDATE usuarios SET nome = '$nome', email = '$email', status = '$status' WHERE id = $id_user";
    $master_conn->query($sql_update);

    // Se preencheu senha, atualiza hash
    if (!empty($nova_senha)) {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $master_conn->query("UPDATE usuarios SET senha = '$hash' WHERE id = $id_user");
    }

    $msg_feedback = "Admin #$id_user atualizado com sucesso!";
    $msg_tipo = 'sucesso';
}

// --- LÓGICA DE BUSCA (FILTRANDO APENAS SUPER ADMINS) ---
$busca = trim($_GET['busca'] ?? '');

// Filtro principal: is_master = 1 OU role = 'super_admin'
$sql = "SELECT id, nome, email, role, status, criado_em, is_master 
        FROM usuarios 
        WHERE (is_master = 1 OR role = 'super_admin')";

if (!empty($busca)) {
    $termo = $master_conn->real_escape_string($busca);
    // Adiciona as condições de busca com AND
    $sql .= " AND (nome LIKE '%$termo%' OR email LIKE '%$termo%' OR id = '$termo')";
}

$sql .= " ORDER BY id DESC LIMIT 100";
$result = $master_conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Admins</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #0e0e0e; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding-bottom: 40px; }
        .topbar { background: #1a1a1a; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.4); }
        .topbar-title { font-size: 1.2rem; color: #00bfff; font-weight: bold; }
        .container { width: 95%; max-width: 1200px; margin: 30px auto; background: #121212; padding: 25px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.2); }
        
        h1 { color: #00bfff; text-align: center; margin-bottom: 30px; }
        
        /* Form de Busca */
        .search-box { display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; }
        .search-input { padding: 10px; width: 300px; background: #1c1c1c; border: 1px solid #333; color: #fff; border-radius: 4px; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; color: #fff; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .btn-blue { background: #00bfff; } .btn-blue:hover { background: #009acd; }
        .btn-red { background: #c0392b; } .btn-red:hover { background: #a93226; }
        .btn-green { background: #27ae60; } .btn-green:hover { background: #219150; }
        .btn-gray { background: #555; } .btn-gray:hover { background: #666; }
        .btn-disabled { background: #333; color: #777; cursor: not-allowed; }

        /* Tabela */
        table { width: 100%; border-collapse: collapse; background: #1a1a1a; border-radius: 8px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #2a2a2a; }
        th { background-color: #252525; color: #00bfff; text-transform: uppercase; font-size: 0.85rem; }
        tr:hover { background-color: #202020; }
        
        /* Status Badges */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .bg-ativo { background: rgba(39, 174, 96, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .bg-inativo { background: rgba(192, 57, 43, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }

        /* Feedback Msg */
        .msg-box { padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .sucesso { background: rgba(39, 174, 96, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .erro { background: rgba(192, 57, 43, 0.2); color: #e74c3c; border: 1px solid #e74c3c; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(2px); }
        .modal-content { background-color: #1e1e1e; margin: 5% auto; padding: 25px; border: 1px solid #444; width: 90%; max-width: 500px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #ccc; }
        .form-group input, .form-group select { width: 100%; padding: 10px; background: #2c2c2c; border: 1px solid #444; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .close { float: right; font-size: 24px; cursor: pointer; color: #aaa; } .close:hover { color: #fff; }

        @media(max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead { display: none; }
            tr { margin-bottom: 15px; border: 1px solid #333; padding: 10px; }
            td { display: flex; justify-content: space-between; border: none; padding: 8px 0; }
            td::before { content: attr(data-label); font-weight: bold; color: #00bfff; }
        }
    </style>
</head>
<body>

    <div class="topbar">
        <div class="topbar-title"><i class="fas fa-user-shield"></i> Gerenciar Super Admins</div>
        <a href="dashboard.php" class="btn btn-gray"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <div class="container">
        <h1>Administradores Cadastrados</h1>

        <?php if($msg_feedback): ?>
            <div class="msg-box <?= $msg_tipo ?>"><?= $msg_feedback ?></div>
        <?php endif; ?>

        <form method="GET" class="search-box">
            <input type="text" name="busca" class="search-input" placeholder="Buscar Admin (Nome, Email ou ID)..." value="<?= htmlspecialchars($busca) ?>">
            <button type="submit" class="btn btn-blue"><i class="fas fa-search"></i> Buscar</button>
            <?php if(!empty($busca)): ?>
                <a href="usuarios_sistema.php" class="btn btn-gray">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Tipo</th>
                    <th>Data Cadastro</th>
                    <th style="text-align: right;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $is_me = ($row['id'] == ($_SESSION['user_id'] ?? 0));
                    ?>
                        <tr>
                            <td data-label="ID">#<?= $row['id'] ?></td>
                            <td data-label="Nome">
                                <?= htmlspecialchars($row['nome']) ?>
                                <?php if($is_me) echo " <span style='color:#2ecc71; font-size:0.8rem;'>(Você)</span>"; ?>
                            </td>
                            <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
                            <td data-label="Status">
                                <span class="badge <?= ($row['status'] == 'ativo') ? 'bg-ativo' : 'bg-inativo' ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td data-label="Tipo"><i class="fas fa-crown" style="color:#ffc107"></i> Master</td>
                            <td data-label="Cadastro"><?= date('d/m/Y', strtotime($row['criado_em'])) ?></td>
                            <td data-label="Ações" style="text-align: right;">
                                <button class="btn btn-blue" onclick="abrirModalEditar(<?= $row['id'] ?>, '<?= addslashes($row['nome']) ?>', '<?= addslashes($row['email']) ?>', '<?= $row['status'] ?>')" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if(!$is_me): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja remover o acesso ADMIN de <?= addslashes($row['nome']) ?>?');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id_usuario" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-red" title="Excluir"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-disabled" title="Você não pode se excluir" disabled><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 20px; color: #777;">Nenhum outro administrador encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="modalEditar" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h2 style="color: #00bfff; margin-top:0;">Editar Admin #<span id="editIdDisplay"></span></h2>
            
            <form method="POST">
                <input type="hidden" name="acao" value="editar">
                <input type="hidden" name="id_usuario" id="editIdInput">

                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" id="editNome" required>
                </div>
                
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="editStatus">
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Alterar Senha (Opcional)</label>
                    <input type="password" name="nova_senha" placeholder="Deixe em branco para manter a atual">
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="fecharModal()" class="btn btn-gray">Cancelar</button>
                    <button type="submit" class="btn btn-green">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalEditar(id, nome, email, status) {
            document.getElementById('editIdDisplay').innerText = id;
            document.getElementById('editIdInput').value = id;
            document.getElementById('editNome').value = nome;
            document.getElementById('editEmail').value = email;
            document.getElementById('editStatus').value = status;
            document.getElementById('modalEditar').style.display = 'block';
        }

        function fecharModal() {
            document.getElementById('modalEditar').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('modalEditar')) {
                fecharModal();
            }
        }
    </script>
</body>
</html>
