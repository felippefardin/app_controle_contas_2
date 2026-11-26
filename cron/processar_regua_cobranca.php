<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Somente CLI.\n");
}
// Carrega dependências e inicializa banco de dados
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Carrega Dotenv e PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Carrega variáveis de ambiente (.env) para usar os dados de SMTP existentes
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
} catch (Exception $e) {
    die("Erro: Arquivo .env não encontrado. Não foi possível carregar as configurações de SMTP.");
}

// 2. Conexão com a base Master para buscar os Clientes (Tenants) ativos
$masterConn = getMasterConnection();
$tenants = $masterConn->query("SELECT * FROM tenants WHERE status_assinatura != 'cancelado'");

if ($tenants->num_rows > 0) {
    while ($tenant = $tenants->fetch_assoc()) {
        // Conecta ao banco de dados específico de cada Tenant
        $conn = new mysqli($tenant['db_host'], $tenant['db_user'], $tenant['db_password'], $tenant['db_database']);

        if ($conn->connect_error) {
            error_log("Falha ao conectar no banco do Tenant ID {$tenant['id']}: " . $conn->connect_error);
            continue;
        }

        // 3. Busca contas que vencem HOJE ou em 3 DIAS
        $query = "SELECT cr.*, pf.nome as cliente_nome, pf.email as cliente_email 
                  FROM contas_receber cr
                  JOIN pessoas_fornecedores pf ON cr.id_pessoa_fornecedor = pf.id
                  WHERE cr.status = 'pendente' 
                  AND (cr.data_vencimento = CURDATE() OR cr.data_vencimento = DATE_ADD(CURDATE(), INTERVAL 3 DAY))";
        
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($conta = $result->fetch_assoc()) {
                // Envia o e-mail usando os dados globais de SMTP do sistema
                enviarEmailCobranca($tenant, $conta);
            }
        }
        $conn->close();
    }
}

/**
 * Função para enviar o e-mail de cobrança usando os parâmetros do .env
 */
function enviarEmailCobranca($tenant, $conta) {
    $mail = new PHPMailer(true);
    try {
        // Configurações SMTP (Baseadas no seu código de marketing)
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port       = (int)$_ENV['MAIL_PORT'];
        $mail->CharSet    = 'UTF-8';

        // O remetente aparece como o nome da empresa do Tenant, mas usa o e-mail do sistema
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $tenant['nome_empresa']);
        $mail->addAddress($conta['cliente_email'], $conta['cliente_nome']);

        // Conteúdo do E-mail
        $mail->isHTML(true);
        $mail->Subject = "Lembrete de Pagamento: " . $tenant['nome_empresa'];
        
        $valorFormatado = number_format($conta['valor'], 2, ',', '.');
        $dataVencimento = date('d/m/Y', strtotime($conta['data_vencimento']));

        $corpoHTML = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <h2>Olá, {$conta['cliente_nome']}!</h2>
                <p>Este é um lembrete automático sobre sua conta em <strong>{$tenant['nome_empresa']}</strong>.</p>
                <hr>
                <p><strong>Descrição:</strong> {$conta['descricao']}<br>
                <strong>Valor:</strong> R$ {$valorFormatado}<br>
                <strong>Vencimento:</strong> {$dataVencimento}</p>
                <hr>
                <p>Caso já tenha efetuado o pagamento, por favor, desconsidere este e-mail.</p>
                <p>Atenciosamente,<br>Equipe {$tenant['nome_empresa']}</p>
            </div>
        ";

        $mail->Body = $corpoHTML;
        $mail->AltBody = strip_tags($corpoHTML);

        $mail->send();
        
        // Pequena pausa para evitar bloqueios de SPAM do servidor SMTP
        usleep(200000); // 0.2 segundos

    } catch (Exception $e) {
        error_log("Erro ao enviar cobrança para ID {$conta['id']} no banco do tenant {$tenant['nome_empresa']}: {$mail->ErrorInfo}");
    }
}
