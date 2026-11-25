<?php
require_once __DIR__ . '/account_deletion_consent.php';
function account_cycle_record_consent(mysqli $conn, string $tenant_id_string, int $id_usuario, string $nome_snapshot, string $email_snapshot, ?string $documento_snapshot): void
{
    // Guarda uma prova independente da conta antes de apagar os dados. Nao ha
    // chave estrangeira para o usuario, pois o registro deve sobreviver a exclusao.
    $protocolo = 'EXC-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $versaoTermo = account_deletion_consent_version();
    $textoAceito = account_deletion_consent_text();
    $textoHash = account_deletion_consent_hash();
    $documentoHash = trim((string)$documento_snapshot) !== '' ? hash('sha256', preg_replace('/\D+/', '', (string)$documento_snapshot)) : null;
    $ipUsuario = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
    $userAgentHash = !empty($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', (string)$_SERVER['HTTP_USER_AGENT']) : null;
    $excluirApos = date('Y-m-d H:i:s', strtotime('+5 years'));
    $requestDate = $conn->execute_query('SELECT desativada_em FROM tenants WHERE tenant_id=?', [$tenant_id_string])->fetch_row()[0] ?? null;
    if ($requestDate === null) {
        throw new RuntimeException('O agendamento não foi registrado.');
    }
    $aceite = $conn->prepare('INSERT INTO aceites_exclusao_conta
        (protocolo, usuario_id_original, tenant_id_snapshot, nome_snapshot, email_snapshot, documento_hash,
         versao_termo, texto_aceito, texto_hash, ip_usuario, user_agent_hash, data_aceite, excluir_apos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $aceite->bind_param('sisssssssssss', $protocolo, $id_usuario, $tenant_id_string, $nome_snapshot, $email_snapshot, $documentoHash, $versaoTermo, $textoAceito, $textoHash, $ipUsuario, $userAgentHash, $requestDate, $excluirApos);
    $aceite->execute();
    $aceite->close();
}
