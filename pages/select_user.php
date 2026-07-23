<?php
session_start();
include('../database.php');

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$ownerId = $_SESSION['usuario']['id'];
$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Consulta o usuário principal e os usuários secundários
$stmt = $conn->prepare("SELECT id, nome, email, foto FROM usuarios WHERE deleted_at IS NULL AND (id = ? OR owner_id = ?) ORDER BY nome ASC");
$stmt->bind_param("ii", $ownerId, $ownerId);
$stmt->execute();
$result = $stmt->get_result();
$usuarios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_user_id'])) {
    $selectedUserId = $_POST['select_user_id'];
    $_SESSION['movimentacao_usuario_id'] = $selectedUserId;

    // Redireciona para a home
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
    <meta charset="UTF-8">
    <title>Selecionar Usuário</title>
    <style>
        body { background-color: #121212; color: #eee; font-family: Arial, sans-serif; display: flex; height: 100vh; justify-content: center; align-items: center; margin: 0; padding: 10px; }
        .container { background: #222; padding: 25px 30px; border-radius: 8px; width: 320px; box-shadow: 0 0 15px rgba(0, 123, 255, 0.7); display: flex; flex-direction: column; text-align: center; }
        h2 { margin-bottom: 20px; color: #00bfff; }
        .user-list { list-style: none; padding: 0; }
        .user-list li { margin-bottom: 15px; }
        .user-list button { background-color: #007bff; border: none; color: white; padding: 12px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; width: 100%; border-radius: 5px; }
        .user-list button:hover { background-color: #0056b3; }
        .user-list img { width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Selecione o Usuário</h2>
        <form method="POST">
            <ul class="user-list">
                <?php foreach ($usuarios as $usuario_item): ?>
                    <li>
                        <button type="submit" name="select_user_id" value="<?= $usuario_item['id'] ?>">
                            <img src="../img/usuarios/<?= htmlspecialchars($usuario_item['foto']) ?>" alt="Foto de Perfil">
                            <?= htmlspecialchars($usuario_item['nome']) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </form>
    </div>
</body>
</html>
