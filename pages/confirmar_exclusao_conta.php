<?php
require_once '../includes/session_init.php';
include('../database.php');
require_once '../includes/utils.php'; // Importa Flash Messages
require_once '../includes/account_deletion_consent.php';

$conn = getMasterConnection(); 
if ($conn === null) {
    die("Erro ao conectar com o banco de dados."); 
}

include('../includes/header.php'); 

// Exibe Flash Message
display_flash_message();

$token = $_GET['token'] ?? '';
$token_valido = false;

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id_usuario, expira_em FROM solicitacoes_exclusao WHERE token = ? AND expira_em > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $solicitacao = $result->fetch_assoc();
        $token_valido = true;
    } else {
        // Token inválido ou expirado -> Redireciona com aviso
        set_flash_message('danger', "Este link de exclusão é inválido ou expirou.");
        echo "<script>window.location.href='../pages/perfil.php';</script>";
        exit;
    }
    $stmt->close();
} else {
    set_flash_message('danger', "Token não fornecido.");
    echo "<script>window.location.href='../pages/perfil.php';</script>";
    exit;
}
?>

<style>
    body { background-color: #121212; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .exclusion-wrapper { display: flex; justify-content: center; align-items: center; min-height: calc(100vh - 150px); padding: 20px; }
    .card-confirm { background-color: #1e1e1e; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; max-width: 550px; width: 100%; border: 1px solid #333; animation: fadeIn 0.5s ease-in-out; }
    .card-confirm h1 { color: #dc3545; margin-bottom: 20px; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .card-confirm p { margin-bottom: 25px; line-height: 1.6; color: #ccc; font-size: 1rem; }
    .icon-big { font-size: 3.5rem; color: #dc3545; margin-bottom: 20px; display: block; }
    .aviso-backup { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 30px; font-size: 0.95rem; border: 1px solid #ffc107; text-align: left; }
    .aviso-backup strong { display: block; margin-bottom: 5px; font-size: 1.1rem; }
    .consent-box { text-align:left; background:#28181b; border:1px solid #7d303a; border-radius:8px; padding:16px; margin:20px 0; color:#f4d8dc; line-height:1.55; }
    .consent-check { display:flex; align-items:flex-start; gap:11px; margin-top:14px; font-weight:700; color:#fff; cursor:pointer; }
    .consent-check input { width:20px; height:20px; flex:0 0 auto; margin-top:2px; }
    .btn-area { display: flex; gap: 15px; justify-content: center; margin-top: 30px; }
    .btn-action { padding: 12px 24px; font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: all 0.3s ease; text-decoration: none; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-danger-confirm { background-color: #dc3545; color: white; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); }
    .btn-danger-confirm:hover { background-color: #c82333; transform: translateY(-2px); }
    .btn-cancel { background-color: #2c3e50; color: white; border: 1px solid #444; }
    .btn-cancel:hover { background-color: #34495e; border-color: #555; }
    @media (max-width: 600px) { .btn-area { flex-direction: column; gap: 10px; } .btn-action { width: 100%; } }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="exclusion-wrapper">
    <div class="card-confirm">
        
        <i class="fa-solid fa-triangle-exclamation icon-big"></i>
        <h1>Confirmar Exclusão</h1>
        
        <div class="aviso-backup">
            <strong><i class="fas fa-save"></i> ATENÇÃO AO BACKUP</strong>
            Antes de prosseguir, certifique-se de ter exportado tudo o que deseja preservar.
            Esta ação apagará permanentemente todos os dados vinculados à conta.
        </div>
        
        <p>Você tem certeza absoluta? <strong>Esta ação não pode ser desfeita.</strong></p>
        
        <form action="../actions/executar_exclusao.php" method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="consent-box">
                <strong>Leia antes de confirmar:</strong>
                <p style="margin:10px 0 0;color:inherit;"><?= htmlspecialchars(account_deletion_consent_text()) ?></p>
                <label class="consent-check">
                    <input type="checkbox" name="aceite_exclusao" value="1" required>
                    <span>Li, compreendi e confirmo a exclusão permanente de todos os dados da conta.</span>
                </label>
            </div>
            
            <div class="btn-area">
                <button type="button" class="btn-action btn-cancel" onclick="window.location.href='../pages/perfil.php'">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn-action btn-danger-confirm">
                    <i class="fas fa-trash-alt"></i> Sim, Excluir Conta
                </button>
            </div>
        </form>

    </div>
</div>

<?php include('../includes/footer.php'); ?>
