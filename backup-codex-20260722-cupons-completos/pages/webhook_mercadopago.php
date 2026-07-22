<?php
// pages/webhook_mercadopago.php
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// 🔹 CONFIGURAÇÃO
$modo = $_ENV['MERCADOPAGO_MODE'] ?? 'sandbox';
$mp_access_token = ($modo === 'producao') ? $_ENV['MP_ACCESS_TOKEN_PRODUCAO'] : $_ENV['MP_ACCESS_TOKEN_SANDBOX'];

$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Captura o TIPO (Pode vir em 'type' ou 'action')
$tipoNotificacao = $data['type'] ?? $data['action'] ?? 'desconhecido';

// Captura o ID do recurso (Pode vir em 'data.id' ou no 'id' principal)
$idRecurso = $data['data']['id'] ?? $data['id'] ?? 'n/a';

// Log para debug aprimorado
$log_entry = date('Y-m-d H:i:s') . " - TIPO: " . $tipoNotificacao . " - ID: " . $idRecurso . PHP_EOL;
file_put_contents(__DIR__ . "/../logs/webhook_assinatura.log", $log_entry, FILE_APPEND);

// Se não houver dados válidos, apenas responde 200 para o Mercado Pago
if (!$data || $tipoNotificacao === 'desconhecido' || $idRecurso === 'n/a') {
    http_response_code(200); 
    exit;
}

$conn = getMasterConnection();

// ==============================================================================
// CASO 1: ATUALIZAÇÃO DE ASSINATURA (Ativa/Suspende o Tenant)
// ==============================================================================
if (in_array($tipoNotificacao, ['subscription_preapproval', 'subscription_preapproval.updated'])) {
    $url = "https://api.mercadopago.com/preapproval/" . $idRecurso;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mp_access_token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $subData = json_decode($response, true);
        $statusMp = $subData['status']; 
        $tenant_ref = $subData['external_reference']; // ID do Tenant

        $statusSistema = ($statusMp === 'authorized') ? 'ativo' : (($statusMp === 'pending') ? 'pendente' : 'cancelado');

        $stmt = $conn->prepare("UPDATE tenants SET status_assinatura = ?, id_assinatura_mp = ? WHERE tenant_id = ?");
        $stmt->bind_param("sss", $statusSistema, $idRecurso, $tenant_ref);
        $stmt->execute();
        $stmt->close();
    }
}

// ==============================================================================
// CASO 2: PAGAMENTO RECEBIDO (Gera Financeiro e Baixa Automática no Tenant)
// ==============================================================================
if (in_array($tipoNotificacao, ['payment', 'payment.created', 'payment.updated'])) {
    $url = "https://api.mercadopago.com/v1/payments/" . $idRecurso;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $mp_access_token]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $payData = json_decode($response, true);
        
        // Esperado no external_reference: "TENANT_ID|ID_CONTA"
        $ref_parts = explode('|', ($payData['external_reference'] ?? ''));
        $tenant_id = $ref_parts[0] ?? null;
        $id_conta_tenant = $ref_parts[1] ?? null;

        $status_mp = $payData['status'];
        $valor = $payData['transaction_amount'];
        $forma_pagamento = $payData['payment_type_id'];
        $data_pagamento = date('Y-m-d', strtotime($payData['date_approved'] ?? 'now'));
        $data_vencimento = date('Y-m-d', strtotime($payData['date_created']));

        $status_db = ($status_mp === 'approved') ? 'pago' : (($status_mp === 'cancelled' || $status_mp === 'rejected') ? 'cancelado' : 'pendente');

        // A. Registro no Banco MASTER (Dashboard da aplicação)
        $check = $conn->prepare("SELECT id FROM faturas_assinatura WHERE transacao_id = ?");
        $check->bind_param("s", $idRecurso);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            $ins = $conn->prepare("INSERT INTO faturas_assinatura (tenant_id, valor, data_vencimento, data_pagamento, status, forma_pagamento, transacao_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param("sdsssss", $tenant_id, $valor, $data_vencimento, $data_pagamento, $status_db, $forma_pagamento, $idRecurso);
            $ins->execute();
            $ins->close();
        }
        $check->close();

        // B. Baixa Automática no Banco do TENANT (Financeiro do Cliente)
        if ($status_mp === 'approved' && $tenant_id && $id_conta_tenant) {
            $stmtT = $conn->prepare("SELECT db_host, db_database, db_user, db_password FROM tenants WHERE tenant_id = ?");
            $stmtT->bind_param("s", $tenant_id);
            $stmtT->execute();
            $resT = $stmtT->get_result()->fetch_assoc();
            
            if ($resT) {
                // Conexão dinâmica com o banco de dados do tenant
                $tenantConn = new mysqli($resT['db_host'], $resT['db_user'], $resT['db_password'], $resT['db_database']);
                if (!$tenantConn->connect_error) {
                    $obs = "Baixa automática via Mercado Pago (Transação: $idRecurso)";
                    // Atualiza a tabela contas_receber no banco do cliente
                    $updateSql = "UPDATE contas_receber SET status='baixada', data_baixa=?, forma_pagamento=?, observacao=? WHERE id=?";
                    $stmtU = $tenantConn->prepare($updateSql);
                    $stmtU->bind_param("sssi", $data_pagamento, $forma_pagamento, $obs, $id_conta_tenant);
                    $stmtU->execute();
                    $stmtU->close();
                    $tenantConn->close();
                }
            }
            $stmtT->close();
        }
    }
}

$conn->close();
http_response_code(200);
?>