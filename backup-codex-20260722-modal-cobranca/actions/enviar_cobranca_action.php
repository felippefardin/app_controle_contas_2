<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. VERIFICA LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header('Location: ../pages/login.php');
    exit;
}

// 2. VERIFICA DADOS VIA POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('error', "Método inválido.");
    header('Location: ../pages/contas_receber.php');
    exit;
}

$id_conta = filter_input(INPUT_POST, 'id_conta', FILTER_VALIDATE_INT);
$chave_pix = $_POST['chave_pix'] ?? '';
$mensagem_extra = $_POST['mensagem'] ?? '';

if (!$id_conta) {
    set_flash_message('error', "Conta não identificada.");
    header('Location: ../pages/contas_receber.php');
    exit;
}

// 3. CONEXÃO
$conn = getTenantConnection();
if ($conn === null) {
    set_flash_message('error', "Erro de conexão.");
    header('Location: ../pages/contas_receber.php');
    exit;
}

try {
    // 4. BUSCA DADOS
    $sql = "SELECT cr.*, pf.nome as nome_cliente, pf.email as email_cliente 
            FROM contas_receber cr 
            LEFT JOIN pessoas_fornecedores pf ON cr.id_pessoa_fornecedor = pf.id 
            WHERE cr.id = ? LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_conta);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $conta = $resultado->fetch_assoc();
    $stmt->close();

    if (!$conta) throw new Exception("Conta não encontrada.");
    if (empty($conta['email_cliente'])) throw new Exception("Cliente sem e-mail cadastrado.");

    // 5. CONFIGURA E-MAIL
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    
    $mail->isSMTP();
    $mail->Host       = $_ENV['MAIL_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['MAIL_USERNAME'];
    $mail->Password   = $_ENV['MAIL_PASSWORD'];
    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
    $mail->Port       = (int)$_ENV['MAIL_PORT'];

    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    $mail->addAddress($conta['email_cliente'], $conta['nome_cliente']);

    // 6. TRATA O ARQUIVO ANEXADO (Se houver)
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        $mail->addAttachment($_FILES['arquivo']['tmp_name'], $_FILES['arquivo']['name']);
    }

    // Formatação
    $valor_formatado = number_format($conta['valor'], 2, ',', '.');
    $data_vencimento = date('d/m/Y', strtotime($conta['data_vencimento']));
    $nome_empresa = $_ENV['MAIL_FROM_NAME'];

    // Monta HTML do Pix se houver
    $html_pix = "";
    if (!empty($chave_pix)) {
        $html_pix = "
        <div style='background-color: #e9ecef; padding: 10px; margin-top: 15px; border-radius: 5px;'>
            <p style='margin:0; font-weight:bold; color:#333;'>Pague via Pix:</p>
            <p style='margin:5px 0; font-size: 1.1em; color:#000;'>$chave_pix</p>
        </div>";
    }

    // Mensagem extra
    $html_msg = !empty($mensagem_extra) ? "<p><em>Nota: " . nl2br(htmlspecialchars($mensagem_extra)) . "</em></p>" : "";

    // Corpo do E-mail
    $mail->isHTML(true);
    $mail->Subject = "Cobrança / Fatura - $nome_empresa";
    
    $mail->Body = "
    <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; border: 1px solid #ddd;'>
        <div style='background-color: #ffc107; color: #333; padding: 20px; text-align: center;'>
            <h2 style='margin:0;'>Aviso de Cobrança</h2>
        </div>
        <div style='padding: 30px;'>
            <p>Olá, <strong>{$conta['nome_cliente']}</strong>.</p>
            <p>Seguem os dados da fatura pendente:</p>
            
            <div style='border-left: 4px solid #ffc107; padding-left: 15px; margin: 20px 0;'>
                <p><strong>Descrição:</strong> {$conta['descricao']}</p>
                <p><strong>Valor:</strong> R$ {$valor_formatado}</p>
                <p><strong>Vencimento:</strong> {$data_vencimento}</p>
            </div>

            $html_msg
            $html_pix

            <p style='font-size:0.9em; color:#666; margin-top:30px;'>
                Se já efetuou o pagamento, por favor desconsidere este e-mail.
                Em anexo, caso necessário, segue o documento relacionado.
            </p>
        </div>
    </div>";

    $mail->send();
    // SUCESSO
    set_flash_message('success', "Cobrança enviada com sucesso!");

} catch (Exception $e) {
    // ERRO
    set_flash_message('error', "Erro ao enviar: " . $e->getMessage());
}

header('Location: ../pages/contas_receber.php');
exit;
?>