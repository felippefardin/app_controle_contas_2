<?php 
// pages/registro_processa.php

// 1. Inicia o buffer de saída para evitar vazamento de avisos/HTML antes do JSON
ob_start(); 

require_once __DIR__ . '/../includes/session_init.php';

// Captura erros de inclusão de banco de dados de forma segura para o AJAX
try {
    require_once __DIR__ . '/../database.php'; 
} catch (Exception $e) {
    if (isset($_POST['ajax_check']) && $_POST['ajax_check'] === '1') {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Erro ao carregar banco de dados.']);
        exit;
    }
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/migration_runner.php';
require_once __DIR__ . '/../includes/cupom_service.php';
require_once __DIR__ . '/../includes/indicacao_reward_service.php';
require_once __DIR__ . '/../includes/lgpd_registration_terms.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Dompdf\Dompdf;

define('TURNSTILE_SECRET_KEY', $_ENV['TURNSTILE_SECRET_KEY'] ?? '');

// Inicializa a conexão com o banco master
$conn = getMasterConnection();

// --- VERIFICAÇÃO AJAX DE DUPLICIDADE ---
if (isset($_POST['ajax_check']) && $_POST['ajax_check'] === '1') {
    // Garante que o buffer de saída seja limpo de qualquer warning inesperado
    ob_clean(); 
    header('Content-Type: application/json');
    
    $email_check = trim($_POST['email'] ?? '');
    $doc_check = trim($_POST['documento'] ?? '');
    
    $email_exists = false;
    $doc_exists = false;

    if ($conn) {
        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email_check);
            $stmt->execute();
            $email_exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }

        $stmt = $conn->prepare("SELECT id FROM usuarios WHERE documento = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $doc_check);
            $stmt->execute();
            $doc_exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
        }
    }

    echo json_encode(['email_exists' => $email_exists, 'doc_exists' => $doc_exists]);
    exit;
}

// --- PROCESSAMENTO DO CADASTRO (VIA FORM POST NORMAL) ---
function verificarTurnstile($token) {
    if (!$token) return false;
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = ['secret' => TURNSTILE_SECRET_KEY, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR']];
    $options = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data)]];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    $response = json_decode($result);
    return $response->success ?? false;
}

$nome         = trim($_POST['nome'] ?? '');
$email        = trim($_POST['email'] ?? '');
$senha        = trim($_POST['senha'] ?? '');
$tipo_pessoa  = trim($_POST['tipo_pessoa'] ?? 'fisica');
$documento    = trim($_POST['documento'] ?? '');
$telefone     = trim($_POST['telefone'] ?? '');
$plano_post   = trim($_POST['plano'] ?? 'basico');
$aceite_lgpd  = $_POST['aceite_lgpd'] ?? '0';
$aceite_termos = $_POST['aceite_termos'] ?? '0';
$aceite_privacidade = $_POST['aceite_privacidade'] ?? '0';
$aceite_comunicacoes = $_POST['aceite_comunicacoes'] ?? '0';
$turnstileToken = $_POST['cf-turnstile-response'] ?? ''; 
$cupom_codigo = isset($_POST['cupom']) && !empty($_POST['cupom']) ? strtoupper(trim($_POST['cupom'])) : null;
$codigo_indicacao_recebido = isset($_POST['codigo_indicacao']) && !empty($_POST['codigo_indicacao']) ? strtoupper(trim($_POST['codigo_indicacao'])) : null;

$form_data = [
    'nome' => $nome, 'email' => $email, 'tipo_pessoa' => $tipo_pessoa, 
    'documento' => $documento, 'telefone' => $telefone, 'plano' => $plano_post,
    'cupom' => $_POST['cupom'] ?? '', 'codigo_indicacao' => $_POST['codigo_indicacao'] ?? ''
];

function return_error($msg, $data) {
    global $conn;
    if(isset($conn) && $conn) $conn->close();
    $_SESSION['form_data'] = $data; 
    set_flash_message('danger', $msg);
    header("Location: ../pages/registro.php");
    exit;
}

if (!verificarTurnstile($turnstileToken)) return_error("Verificação de segurança falhou (Captcha).", $form_data);
if ($aceite_lgpd !== '1' || $aceite_termos !== '1' || $aceite_privacidade !== '1') {
    return_error('É necessário confirmar separadamente os Termos de Uso e o Aviso de Privacidade.', $form_data);
}

$plano_escolhido = ($plano_post === 'essencial') ? 'essencial' : (($plano_post === 'plus') ? 'plus' : 'basico');
$dias_teste = ($plano_post === 'essencial') ? 30 : 15;

if (!$nome || !$email || !$senha || !$documento) return_error("Preencha todos os campos.", $form_data);

if ($cupom_codigo !== null) {
    $validacaoCupom = validarCupomPassivo($conn, $cupom_codigo, 0);
    if (!$validacaoCupom['ok']) {
        return_error($validacaoCupom['msg'], $form_data);
    }
    $cupom_codigo = $validacaoCupom['cupom']['codigo'];
}

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);
$arquivoLgpdFisico = null;
$termoLgpdId = 0;
$conn->begin_transaction();

try {
    $stmtUser = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo_pessoa, documento, telefone, nivel_acesso, perfil, tipo, status, is_master) VALUES (?, ?, ?, ?, ?, ?, 'proprietario', 'admin', 'admin', 'ativo', 1)");
    $stmtUser->bind_param("ssssss", $nome, $email, $senha_hash, $tipo_pessoa, $documento, $telefone);
    $stmtUser->execute();
    $new_usuario_id = $conn->insert_id;
    $stmtUser->close();
    $conn->query("UPDATE usuarios SET codigo_indicacao = CONCAT('IND', LPAD(id, 6, '0')) WHERE id = $new_usuario_id");

    // Registra imediatamente quem indicou o novo usuário. O ranking master lê esta tabela.
    if ($codigo_indicacao_recebido !== null) {
        $stmtIndicador = $conn->prepare('SELECT id FROM usuarios WHERE codigo_indicacao = ? LIMIT 1');
        $stmtIndicador->bind_param('s', $codigo_indicacao_recebido);
        $stmtIndicador->execute();
        $indicador = $stmtIndicador->get_result()->fetch_assoc();
        $stmtIndicador->close();

        if (!$indicador) {
            throw new RuntimeException('O código de indicação informado não existe.');
        }

        $idIndicador = (int)$indicador['id'];
        if ($idIndicador === (int)$new_usuario_id) {
            throw new RuntimeException('Não é permitido indicar a própria conta.');
        }

        $stmtIndicacao = $conn->prepare('INSERT INTO indicacoes (id_indicador, id_indicado) VALUES (?, ?)');
        $stmtIndicacao->bind_param('ii', $idIndicador, $new_usuario_id);
        $stmtIndicacao->execute();
        $stmtIndicacao->close();
        sincronizarRecompensasIndicador($conn, $idIndicador);
    }

    $tenantId = 'T' . substr(bin2hex(random_bytes(16)), 0, 31);
    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbDatabase = 'tenant_db_' . $new_usuario_id;
    $dbUser = 'dbuser_' . $new_usuario_id;
    $dbPassword = bin2hex(random_bytes(16));

    $stmtTenant = $conn->prepare("INSERT INTO tenants (tenant_id, usuario_id, nome, nome_empresa, admin_email, senha, status_assinatura, data_inicio_teste, plano_atual, db_host, db_database, db_user, db_password, cupom_registro) VALUES (?, ?, ?, ?, ?, ?, 'trial', NOW(), ?, ?, ?, ?, ?, ?)");
    $stmtTenant->bind_param("sissssssssss", $tenantId, $new_usuario_id, $nome, $nome, $email, $senha_hash, $plano_escolhido, $dbHost, $dbDatabase, $dbUser, $dbPassword, $cupom_codigo);
    $stmtTenant->execute();
    $new_tenant_id = $conn->insert_id;
    $stmtTenant->close();

    if ($codigo_indicacao_recebido !== null) {
        $stmtBeneficioIndicacao = $conn->prepare(
            'UPDATE tenants SET indicacao_meses_gratis = 2 WHERE id = ?'
        );
        $stmtBeneficioIndicacao->bind_param('i', $new_tenant_id);
        $stmtBeneficioIndicacao->execute();
        $stmtBeneficioIndicacao->close();
    }

    $conn->query("UPDATE usuarios SET tenant_id = '$tenantId' WHERE id = $new_usuario_id");

    $provisionConn = new mysqli($dbHost, $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    try {
        $provisionStmt = $provisionConn->prepare('CALL appcontrolepro_provisionamento.criar_empresa(?, ?)');
        $provisionStmt->bind_param('is', $new_usuario_id, $dbPassword);
        $provisionStmt->execute();
        $provisionStmt->close();
    } finally {
        $provisionConn->close();
    }

    // Todo tenant precisa receber sua estrutura antes do primeiro login.
    $tenantSchemaPath = __DIR__ . '/../includes/tenant_schema._sql';
    if (!is_readable($tenantSchemaPath)) {
        throw new RuntimeException('Arquivo de estrutura do tenant não encontrado.');
    }
    $tenantSchema = file_get_contents($tenantSchemaPath);
    $tenantConn = new mysqli($dbHost, $dbUser, $dbPassword, $dbDatabase);
    $tenantConn->set_charset('utf8mb4');
    if (!$tenantConn->multi_query($tenantSchema)) {
        throw new RuntimeException('Não foi possível criar as tabelas do tenant: ' . $tenantConn->error);
    }
    while ($tenantConn->more_results() && $tenantConn->next_result()) {
        // Consome os resultados das instruções do arquivo SQL.
    }
    run_migrations_for_connection($tenantConn, 'tenant');
    $tenantConn->query("DELETE FROM usuarios WHERE email = 'admin@cliente.com'");
    $stmtTenantUser = $tenantConn->prepare(
        "INSERT INTO usuarios (nome, tipo_pessoa, documento, telefone, email, senha, perfil, tipo, nivel_acesso, status, is_master, tenant_id)
         VALUES (?, ?, ?, ?, ?, ?, 'admin', 'admin', 'proprietario', 'ativo', 1, ?)"
    );
    $stmtTenantUser->bind_param("ssssssi", $nome, $tipo_pessoa, $documento, $telefone, $email, $senha_hash, $new_tenant_id);
    $stmtTenantUser->execute();
    $stmtTenantUser->close();
    $tenantConn->close();

    // Gera e registra o comprovante do consentimento LGPD aceito no cadastro.
    $pastaLgpd = __DIR__ . '/../assets/uploads/contratos_lgpd';
    if (!is_dir($pastaLgpd) && !mkdir($pastaLgpd, 0775, true) && !is_dir($pastaLgpd)) {
        throw new RuntimeException('Não foi possível criar a pasta dos termos LGPD.');
    }

    $agoraBrasil = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $dataAceite = $agoraBrasil->format('Y-m-d H:i:s');
    $dataAceiteExibicao = $agoraBrasil->format('d/m/Y H:i:s') . ' (horário de Brasília)';
    $ipUsuario = $_SERVER['REMOTE_ADDR'] ?? 'não identificado';
    $userAgentHash = !empty($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', (string)$_SERVER['HTTP_USER_AGENT']) : null;
    $versaoDocumento = lgpd_registration_version();
    $preferenciasAceite = json_encode([
        'termos_uso' => true,
        'aviso_privacidade' => true,
        'titularidade_dados' => true,
        'comunicacoes_promocionais' => $aceite_comunicacoes === '1',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $nomeArquivo = sprintf('lgpd_%d_%s_%s.pdf', $new_usuario_id, date('YmdHis'), bin2hex(random_bytes(4)));
    $arquivoLgpdFisico = $pastaLgpd . DIRECTORY_SEPARATOR . $nomeArquivo;
    $caminhoLgpdBanco = 'assets/uploads/contratos_lgpd/' . $nomeArquivo;
    $hashConsentimento = hash('sha256', implode('|', [$new_usuario_id, $nome, $email, $documento, $ipUsuario, $dataAceite, $versaoDocumento, $preferenciasAceite, $userAgentHash]));

    $secoesHtml = '';
    foreach (lgpd_registration_sections() as $section) {
        $secoesHtml .= '<section><h2>' . htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') . '</h2><p>'
            . htmlspecialchars($section['body'], ENT_QUOTES, 'UTF-8') . '</p></section>';
    }

    $htmlLgpd = '<!doctype html><html lang="pt-BR"><head>
<?php session_idle_script(); ?><meta charset="UTF-8"><style>'
        . '@page{margin:32px 42px 42px}body{font-family:DejaVu Sans,Arial,sans-serif;color:#243244;font-size:10.5px;line-height:1.5}'
        . 'h1{color:#087ea4;font-size:21px;border-bottom:2px solid #087ea4;padding-bottom:9px;margin-bottom:5px}h2{font-size:12.5px;color:#0b6685;margin:12px 0 3px}'
        . '.version{color:#66758a;font-size:9px;margin-bottom:13px}.intro{background:#eaf7fc;border-left:4px solid #087ea4;padding:11px 13px;margin-bottom:13px}'
        . '.dados{background:#f4f7f8;border:1px solid #d8e1e5;padding:12px;margin:12px 0}.confirmacoes{background:#f7f9fc;border:1px solid #cfd9e4;padding:12px;margin-top:14px}'
        . '.check{margin:5px 0}.check strong{color:#087ea4}.rodape{margin-top:18px;font-size:8px;color:#66758a;word-break:break-all;border-top:1px solid #d8e1e5;padding-top:8px}'
        . '.page-footer{position:fixed;bottom:-25px;left:0;right:0;text-align:center;color:#8591a1;font-size:8px}section p{margin:0;text-align:left}'
        . '</style></head><body>'
        . '<div class="page-footer">App Controle de Contas - Termos de Uso e Aviso de Privacidade - versão ' . htmlspecialchars($versaoDocumento, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<h1>Termos de Uso e Aviso de Privacidade - LGPD</h1>'
        . '<div class="version">Documento versão ' . htmlspecialchars($versaoDocumento, ENT_QUOTES, 'UTF-8') . ' - Lei nº 13.709/2018</div>'
        . '<div class="intro"><strong>Resumo:</strong> este comprovante registra, de forma clara e verificável, as condições do serviço, o tratamento dos dados pessoais e as escolhas feitas pelo titular no momento da criação da conta.</div>'
        . '<div class="dados"><strong>Titular:</strong> ' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>E-mail:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Documento:</strong> ' . htmlspecialchars($documento, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>Data e hora do aceite:</strong> ' . htmlspecialchars($dataAceiteExibicao, ENT_QUOTES, 'UTF-8') . '<br>'
        . '<strong>IP de origem:</strong> ' . htmlspecialchars($ipUsuario, ENT_QUOTES, 'UTF-8') . '</div>'
        . $secoesHtml
        . '<div class="confirmacoes"><strong>Manifestação registrada:</strong><p>' . htmlspecialchars(lgpd_registration_manifestation(), ENT_QUOTES, 'UTF-8') . '</p>'
        . '<div class="check"><strong>[CONFIRMADO]</strong> Termos de Uso.</div><div class="check"><strong>[CONFIRMADO]</strong> Aviso de Privacidade e direitos LGPD.</div>'
        . '<div class="check"><strong>[CONFIRMADO]</strong> Veracidade e autorização para cadastro.</div>'
        . '<div class="check"><strong>[' . ($aceite_comunicacoes === '1' ? 'ACEITO' : 'NÃO ACEITO') . ']</strong> Comunicações promocionais opcionais.</div></div>'
        . '<div class="rodape"><strong>Identificador do aceite:</strong> ' . $hashConsentimento . '<br><strong>Identificador do navegador:</strong> ' . htmlspecialchars((string)$userAgentHash, ENT_QUOTES, 'UTF-8') . '</div>'
        . '</body></html>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($htmlLgpd, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfLgpd = $dompdf->output();
    $hashDocumento = hash('sha256', $pdfLgpd);
    if (file_put_contents($arquivoLgpdFisico, $pdfLgpd) === false) {
        throw new RuntimeException('Não foi possível salvar o PDF do termo LGPD.');
    }

    $tipoDocumento = 'LGPD_CADASTRO';
    $statusArquivo = 'ATIVO';
    $stmtLgpd = $conn->prepare('INSERT INTO termos_consentimento
        (usuario_id, tipo_documento, caminho_arquivo, ip_usuario, data_aceite, status_arquivo, versao_documento, hash_documento, preferencias_json, user_agent_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmtLgpd->bind_param('isssssssss', $new_usuario_id, $tipoDocumento, $caminhoLgpdBanco, $ipUsuario, $dataAceite, $statusArquivo, $versaoDocumento, $hashDocumento, $preferenciasAceite, $userAgentHash);
    $stmtLgpd->execute();
    $termoLgpdId = $conn->insert_id;
    $stmtLgpd->close();

    $conn->commit();
    unset($_SESSION['form_data']);
    set_flash_message('success', "Cadastro realizado com sucesso!");
    header("Location: ../pages/login.php");
    exit;
} catch (Exception $e) {
    if(isset($conn) && $conn) $conn->rollback();
    // termos_consentimento usa MyISAM e não participa da transação.
    if ($termoLgpdId > 0 && isset($conn) && $conn) {
        $conn->query('DELETE FROM termos_consentimento WHERE id = ' . (int)$termoLgpdId);
    }
    if ($arquivoLgpdFisico && is_file($arquivoLgpdFisico)) {
        @unlink($arquivoLgpdFisico);
    }
    // Isso vai mostrar na tela o que está acontecendo de verdade
    die("ERRO DETALHADO: " . $e->getMessage()); 
}
?>
