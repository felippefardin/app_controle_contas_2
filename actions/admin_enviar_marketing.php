<?php
// actions/admin_enviar_marketing.php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../vendor/autoload.php'; // Carrega o PHPMailer e Dotenv

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Verificação de Segurança (Apenas Super Admin)
if (!isset($_SESSION['super_admin'])) {
    header('Location: ../pages/login.php');
    exit;
}
require_csrf();

// Carrega variáveis de ambiente
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
    die("Erro: Arquivo .env não encontrado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aumenta o tempo de execução para envios em massa
    set_time_limit(0); 

    $assunto = trim($_POST['assunto']);
    $mensagem = trim($_POST['mensagem']);
    $conn = getMasterConnection();

    // 2. Buscar todos os e-mails únicos da tabela usuarios
    $sql = "SELECT DISTINCT email, nome FROM usuarios WHERE email IS NOT NULL AND email != '' AND status = 'ativo'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $enviados = 0;
        $falhas = 0;

        // Configuração base do PHPMailer
        $mail = new PHPMailer(true);
        
        try {
            // Configurações SMTP
            $mail->isSMTP();
            $mail->Host       = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['MAIL_USERNAME'];
            $mail->Password   = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
            $mail->Port       = (int)$_ENV['MAIL_PORT'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);

            // Tratamento do Anexo
            if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] == UPLOAD_ERR_OK) {
                $mail->addAttachment($_FILES['anexo']['tmp_name'], $_FILES['anexo']['name']);
            }

            $mail->isHTML(true);
            $mail->Subject = $assunto;

            // Loop de envio
            while ($row = $result->fetch_assoc()) {
                try {
                    $mail->clearAddresses(); // Limpa destinatários anteriores
                    $mail->addAddress($row['email'], $row['nome']);
                    
                    // Personaliza a mensagem (opcional)
                    $corpoFinal = "Olá <strong>" . htmlspecialchars($row['nome']) . "</strong>,<br><br>" . nl2br($mensagem);
                    
                    $mail->Body = $corpoFinal;
                    $mail->AltBody = strip_tags(str_replace("<br>", "\n", $corpoFinal));

                    $mail->send();
                    $enviados++;
                    
                    // Pequena pausa para não sobrecarregar o SMTP
                    usleep(100000); // 0.1 segundo

                } catch (Exception $e) {
                    $falhas++;
                    // Log de erro se necessário
                }
            }

            $msg = "Disparo concluído! Enviados: $enviados. Falhas: $falhas.";
            header("Location: ../pages/admin/email_marketing.php?sucesso=1&msg=" . urlencode($msg));

        } catch (Exception $e) {
            header("Location: ../pages/admin/email_marketing.php?erro=1&msg=Erro na configuração do e-mail: " . $mail->ErrorInfo);
        }

    } else {
        header("Location: ../pages/admin/email_marketing.php?erro=1&msg=Nenhum usuário encontrado para envio.");
    }
    
    $conn->close();
}
?>
