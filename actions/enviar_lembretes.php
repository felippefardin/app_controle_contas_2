<?php
// actions/enviar_lembretes.php
require_once __DIR__ . '/../database.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Carrega as variáveis de ambiente do arquivo .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("ERRO CRÍTICO: Não foi possível encontrar o arquivo .env.");
}

/**
 * Função de envio de e-mail (Mantida conforme original)
 */
function enviarEmail($email, $nome, $assunto, $corpoHtml, $corpoAlt) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port       = (int)$_ENV['MAIL_PORT'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($email, $nome);

        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $corpoHtml;
        $mail->AltBody = $corpoAlt;

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Erro ao enviar e-mail para {$nome}: {$mail->ErrorInfo}<br>";
        return false;
    }
}

// 2. Conecta ao banco MASTER para buscar todos os Tenants ativos
$masterConn = getMasterConnection();
$sqlTenants = "SELECT * FROM tenants WHERE status_assinatura NOT IN ('cancelado', 'inativo')";
$resultTenants = $masterConn->query($sqlTenants);

echo "<h2>Processando Lembretes Automáticos</h2>";

if ($resultTenants && $resultTenants->num_rows > 0) {
    while ($tenant = $resultTenants->fetch_assoc()) {
        echo "<h4>Tenant: {$tenant['db_database']}</h4>";
        
        // 3. Abre conexão com o banco específico deste Tenant
        $conn = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);
        
        if ($conn->connect_error) {
            echo "Erro ao conectar ao banco {$tenant['db_database']}: " . $conn->connect_error . "<br>";
            continue;
        }
        $conn->set_charset("utf8mb4");

        // --------------------- CONTAS A PAGAR (REGRAS: 7 E 3 DIAS) ---------------------
        $stmtPagar = $conn->prepare("
            SELECT cp.*, u.nome AS usuario_nome, u.email AS usuario_email
            FROM contas_pagar cp
            JOIN usuarios u ON cp.usuario_id = u.id
            WHERE cp.status = 'pendente' AND cp.deleted_at IS NULL
            AND (DATEDIFF(cp.data_vencimento, CURDATE()) = 7 
                 OR DATEDIFF(cp.data_vencimento, CURDATE()) = 3)
        ");
        $stmtPagar->execute();
        $resPagar = $stmtPagar->get_result();
        
        while ($conta = $resPagar->fetch_assoc()) {
            $vencimento = date('d/m/Y', strtotime($conta['data_vencimento']));
            $diff = (strtotime($conta['data_vencimento']) - strtotime(date('Y-m-d'))) / 86400;

            $assunto = "🔔 Lembrete: Sua conta com {$conta['fornecedor']} vence em $diff dias";
            $corpo = "Olá {$conta['usuario_nome']},<br><br>Lembramos que a conta de <b>{$conta['fornecedor']}</b> vence em <b>$diff dias</b> ($vencimento).";
            
            if (enviarEmail($conta['usuario_email'], $conta['usuario_nome'], $assunto, $corpo, strip_tags($corpo))) {
                echo "Lembrete de $diff dias enviado para {$conta['usuario_nome']} (Pagar)<br>";
            }
        }
        $stmtPagar->close();

        // --------------------- CONTAS A RECEBER (REGRA ORIGINAL: ATÉ 7 DIAS) ---------------------
        $hoje = date('Y-m-d');
        $data_limite = date('Y-m-d', strtotime('+7 days'));
        $stmtReceber = $conn->prepare("
            SELECT cr.*, u.nome AS cliente_nome, u.email AS cliente_email
            FROM contas_receber cr
            JOIN usuarios u ON cr.usuario_id = u.id
            WHERE cr.data_vencimento BETWEEN ? AND ? AND cr.status = 'pendente' AND cr.deleted_at IS NULL
        ");
        $stmtReceber->bind_param("ss", $hoje, $data_limite);
        $stmtReceber->execute();
        $resReceber = $stmtReceber->get_result();

        while ($conta = $resReceber->fetch_assoc()) {
            $desc = $conta['descricao'] ?? "Conta #" . $conta['id'];
            $assunto = "🔔 Lembrete de Conta a Receber: $desc";
            $corpo = "Olá {$conta['cliente_nome']}, sua conta <b>$desc</b> vence em " . date('d/m/Y', strtotime($conta['data_vencimento'])) . ".";
            
            if (enviarEmail($conta['cliente_email'], $conta['cliente_nome'], $assunto, $corpo, strip_tags($corpo))) {
                echo "Lembrete enviado para {$conta['cliente_nome']} (Receber)<br>";
            }
        }
        $stmtReceber->close();

        // Fecha a conexão do tenant atual para abrir a do próximo no loop
        $conn->close();
    }
}

// --------------------- ENVIO MANUAL (VIA GET) ---------------------
// Para o manual funcionar, ele precisa de um Tenant específico (usamos o da sessão)
if (isset($_GET['tipo'], $_GET['id'])) {
    session_start();
    $conn = getTenantConnection(); // Tenta pegar a conexão do usuário logado no navegador
    
    if ($conn) {
        $tipo = $_GET['tipo'];
        $id = intval($_GET['id']);
        echo "<h3>Processando Envio Manual</h3>";

        if ($tipo === 'pagar') {
            $stmt = $conn->prepare("SELECT cp.*, u.nome, u.email FROM contas_pagar cp JOIN usuarios u ON cp.usuario_id = u.id WHERE cp.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $c = $stmt->get_result()->fetch_assoc();
            if ($c) {
                enviarEmail($c['email'], $c['nome'], "🔔 Lembrete Manual", "Conta {$c['fornecedor']} vencendo.", "Lembrete.");
                echo "Enviado manual Pagar com sucesso.<br>";
            }
            $stmt->close();
        }
        $conn->close();
    } else {
        echo "Erro: Sessão de tenant não encontrada para envio manual.";
    }
}

$masterConn->close();
?>
