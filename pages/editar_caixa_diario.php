<?php
// 1. Inicia sessão e banco de dados ANTES de qualquer HTML
require_once '../includes/session_init.php';
require_once '../database.php';

if (empty($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}
$id_usuario = get_data_owner_id();

// CORREÇÃO: Inicializa a conexão com o banco do tenant
$conn = getTenantConnection();

// Verifica se a conexão foi bem-sucedida
if (!$conn) {
    die("Erro: Não foi possível conectar ao banco de dados.");
}

// 2. Verifica se o ID foi passado. Se não, redireciona.
if (!isset($_GET['id'])) {
    header('Location: relatorios.php');
    exit;
}

$id = intval($_GET['id']);
$mensagem_sucesso = '';
$mensagem_erro = '';

// 3. Processa o formulário (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $valor = $_POST['valor'];

    // É boa prática validar se os dados não estão vazios
    if(!empty($data) && is_numeric($valor)) {
        $stmt = $conn->prepare("UPDATE caixa_diario SET valor = ? WHERE id = ? AND usuario_id = ? AND tipo IN ('manual_entrada','manual_saida') AND data = CURDATE()");
        $stmt->bind_param("dii", $valor, $id, $id_usuario);

        if ($stmt->execute()) {
            $mensagem_sucesso = "Lançamento editado com sucesso!";
        } else {
            $mensagem_erro = "Erro ao editar o lançamento: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $mensagem_erro = "Por favor, preencha todos os campos corretamente.";
    }
}

// 4. Busca os dados atuais do lançamento
$stmt = $conn->prepare("SELECT * FROM caixa_diario WHERE id = ? AND usuario_id = ? AND tipo IN ('manual_entrada','manual_saida') AND data = CURDATE()");
$stmt->bind_param("ii", $id, $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$lancamento = $result->fetch_assoc();
$stmt->close();

// 5. Se o lançamento não existir, redireciona
if (!$lancamento) {
    header('Location: relatorios.php');
    exit;
}

// 6. AGORA que toda a lógica de redirecionamento passou, carregamos o HTML do header
require_once '../includes/header.php';
?>

<style>
/* Mantendo seus estilos originais */
body {
    background-color: #121212;
    color: #eee;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 700px;
    margin: auto;
    background-color: #222;
    padding: 25px;
    border-radius: 8px;
}

h2 {
    color: #00bfff;
    border-bottom: 2px solid #00bfff;
    padding-bottom: 10px;
    margin-bottom: 2rem;
    text-align: center;
}

.alert {
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    text-align: center;
    color: white;
    opacity: 1;
    transition: opacity 0.5s ease-out;
}

.alert-success { background-color: #28a745; }
.alert-danger { background-color: #cc4444; }

form .form-group {
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 10px 2px;
    border: 1px solid #444;
    border-radius: 6px;
    background-color: #333;
    color: #eee;
}

.form-control:focus {
    border-color: #00bfff;
    outline: none;
    box-shadow: 0 0 5px rgba(0, 191, 255, 0.5);
}

.btn {
    padding: 10px 18px;
    font-size: 14px;
    font-weight: bold;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    margin-right: 10px;
    transition: background-color 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background-color: #00bfff;
    color: #fff;
}
.btn-primary:hover { background-color: #0099cc; }

.btn-secondary {
    background-color: #6c757d;
    color: #fff;
}
.btn-secondary:hover { background-color: #5a6268; }

/* RESPONSIVIDADE MOBILE */
@media (max-width: 768px) {
    .container {
        padding: 15px;
        margin: 10px;
    }
    .btn {
        display: block;
        width: 100%;
        margin-bottom: 10px;
    }
}
</style>

<div class="container">
    <h2>Editar Lançamento</h2>

    <?php if ($mensagem_sucesso): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensagem_sucesso) ?></div>
    <?php endif; ?>
    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mensagem_erro) ?></div>
    <?php endif; ?>

    <form action="" method="post">
        <div class="form-group">
            <label for="data">Data:</label>
            <input type="date" class="form-control" name="data" value="<?= htmlspecialchars($lancamento['data']) ?>" required>
        </div>
        <div class="form-group">
            <label for="valor">Valor:</label>
            <input type="number" step="0.01" class="form-control" name="valor" value="<?= htmlspecialchars($lancamento['valor']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="relatorios.php" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<script>
// Esconde automaticamente as mensagens após 3 segundos
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => { alert.style.display = 'none'; }, 500);
        }, 3000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
