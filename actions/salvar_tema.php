<?php
// ARQUIVO: actions/salvar_tema.php
include '../includes/session_init.php';
include '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Não logado']);
    exit;
}

$tema = $_POST['tema'] ?? 'dark';
if (!in_array($tema, ['dark', 'light'])) $tema = 'dark';

// 1. Salva na Sessão PHP
$_SESSION['tema_preferencia'] = $tema;

// 2. O cookie de preferência só pode ser criado após autorização específica.
$cookieConsent = json_decode((string) ($_COOKIE['app_cookie_consent'] ?? ''), true);
if (is_array($cookieConsent)
    && (int) ($cookieConsent['version'] ?? 0) === 1
    && ($cookieConsent['preferences'] ?? false) === true) {
    setcookie('tema_preferencia', $tema, [
        'expires' => time() + (86400 * 365),
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

// 3. Salva no Banco de Dados
$conn = getTenantConnection(); 
if (!$conn) $conn = getMasterConnection();

if ($conn) {
    $stmt = $conn->prepare("UPDATE usuarios SET tema_preferencia = ? WHERE id = ?");
    $stmt->bind_param("si", $tema, $_SESSION['usuario_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Erro BD']);
    }
} else {
    echo json_encode(['status' => 'success', 'msg' => 'Salvo apenas sessão/cookie']);
}
?>
