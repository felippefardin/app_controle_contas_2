<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Somente CLI.\n");
}
// cron/limpeza_lgpd.php
// Script para remover documentos LGPD com mais de 5 anos de contas inativas/deletadas

require_once __DIR__ . '/../database.php';

$conn = getMasterConnection();

echo "Iniciando rotina de limpeza LGPD - " . date('Y-m-d H:i:s') . "\n";

// 1. Seleciona documentos de usuários inativos ou deletados onde o aceite foi há mais de 5 anos
// NOTA: Ajuste a lógica de 'usuario inativo' conforme sua tabela (ex: status = 'inativo' ou coluna deleted_at)
$sql = "
    SELECT tc.id, tc.caminho_arquivo 
    FROM termos_consentimento tc
    JOIN usuarios u ON tc.usuario_id = u.id
    WHERE 
    (tc.data_aceite < DATE_SUB(NOW(), INTERVAL 5 YEAR))
    AND 
    (u.status = 'inativo' OR u.status = 'banido' OR u.status = 'cancelado')
";

$result = $conn->query($sql);
$count = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $arquivoPath = __DIR__ . '/../' . $row['caminho_arquivo'];
        
        // Deletar Arquivo Físico
        if (file_exists($arquivoPath)) {
            if (unlink($arquivoPath)) {
                echo "Arquivo deletado: " . $row['caminho_arquivo'] . "\n";
            } else {
                echo "Falha ao deletar arquivo: " . $row['caminho_arquivo'] . "\n";
            }
        } else {
            echo "Arquivo não encontrado (já deletado?): " . $row['caminho_arquivo'] . "\n";
        }

        // Deletar Registro no Banco
        $stmtDel = $conn->prepare("DELETE FROM termos_consentimento WHERE id = ?");
        $stmtDel->bind_param("i", $row['id']);
        $stmtDel->execute();
        $stmtDel->close();
        
        $count++;
    }
}

echo "Limpeza concluída. $count documentos processados.\n";

// Provas de aceite da exclusao sao independentes da conta apagada e ficam
// retidas por cinco anos. Depois do prazo, a remocao e definitiva.
$aceitesRemovidos = 0;
$aceites = $conn->query('SELECT id FROM aceites_exclusao_conta WHERE excluir_apos<=NOW()');
while ($aceites && ($aceite = $aceites->fetch_assoc())) {
    $idAceite = (int)$aceite['id'];
    $deleteAceite = $conn->prepare('DELETE FROM aceites_exclusao_conta WHERE id=? AND excluir_apos<=NOW()');
    $deleteAceite->bind_param('i', $idAceite); $deleteAceite->execute();
    $aceitesRemovidos += $deleteAceite->affected_rows; $deleteAceite->close();
}
echo "Provas de exclusao removidas apos 5 anos: $aceitesRemovidos.\n";
$conn->close();
?>
