<?php
require_once '../includes/config/config.php';
$token = $_GET['token'] ?? '';

$conn = getMasterConnection();

// Validar token
$sql = "SELECT id FROM usuarios WHERE token_recovery = ? AND token_expiry > NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Link inválido ou expirado.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
    <meta charset="UTF-8">
    <title>Reativar Conta</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Reativar Conta</h3>
                    </div>
                    <div class="card-body">
                        <form action="../actions/confirmar_reativacao.php" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <input type="hidden" name="token" value="<?php echo $token; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Nova Senha</label>
                                <input type="password" name="nova_senha" required class="form-control" minlength="6">
                            </div>
                            
                            <hr>
                            <h4 class="mb-3">Escolha seu Plano</h4>
                            <select name="novo_plano" class="form-select mb-3">
                                <option value="mensal">Plano Mensal</option>
                                <option value="anual">Plano Anual</option>
                            </select>
                            
                            <button type="submit" class="btn btn-success w-100">Salvar Senha e Ir para Pagamento</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>