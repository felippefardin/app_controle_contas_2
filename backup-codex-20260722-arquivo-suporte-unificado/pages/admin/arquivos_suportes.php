<?php
require_once '../../includes/session_init.php';
require_once '../../database.php';

// Conexão via MySQLi
$conn = getMasterConnection(); 

// Busca
$busca = $_GET['busca'] ?? '';
$sql = "SELECT cs.*, u.nome as usuario_nome 
        FROM chat_sessions cs 
        JOIN usuarios u ON cs.usuario_id = u.id 
        WHERE cs.status = 'closed'";

$params = [];
$types = "";

if ($busca) {
    $sql .= " AND cs.protocolo LIKE ?";
    $params[] = "%$busca%";
    $types .= "s";
}

$sql .= " ORDER BY cs.data_criacao DESC";

$stmt = $conn->prepare($sql);

if ($busca) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Arquivos de Suporte</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; color: #333; }
        
        /* Tabela */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: middle; }
        th { background-color: #007bff; color: white; font-weight: 600; text-transform: uppercase; font-size: 0.9rem; }
        tr:hover { background-color: #f1f1f1; }
        
        /* Botões e Forms */
        .btn { padding: 8px 15px; border-radius: 4px; text-decoration: none; color: white; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.3s; }
        .btn-primary { background-color: #007bff; } .btn-primary:hover { background-color: #0056b3; }
        .btn-danger { background-color: #dc3545; } .btn-danger:hover { background-color: #a71d2a; }
        .btn-secondary { background-color: #6c757d; } .btn-secondary:hover { background-color: #545b62; }
        /* Botão específico para lixeira com estilo sutilmente diferente se desejar, ou reusar btn-danger */
        .btn-trash { background-color: #e74c3c; margin-left: 5px; } .btn-trash:hover { background-color: #c0392b; }
        
        .form-control { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 300px; font-size: 1rem; }
        .actions-bar { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }

        /* Estilo dos anexos */
        .attachment-list { display: flex; gap: 8px; flex-wrap: wrap; }
        .attach-link { 
            text-decoration: none; 
            background: #eef2f5; 
            padding: 4px 8px; 
            border-radius: 4px; 
            border: 1px solid #ddd;
            color: #555; 
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 5px;
        }
        .attach-link:hover { background: #dfe4ea; color: #007bff; }
        .attach-icon-img { color: #198754; }
        .attach-icon-pdf { color: #dc3545; }

        /* Informação de validade 5 anos */
        .validade-info { font-size: 0.75rem; color: #888; margin-top: 4px; display: block; }
        .validade-info i { margin-right: 3px; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 400px; max-width: 90%; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .modal-buttons { margin-top: 20px; display: flex; justify-content: center; gap: 10px; }
        .modal-title { margin-top: 0; color: #333; }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-archive"></i> Arquivos de Suporte (Protocolos)</h2>
    
    <form method="GET" class="actions-bar">
        <input type="text" name="busca" class="form-control" placeholder="Buscar por Protocolo..." value="<?php echo htmlspecialchars($busca); ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
    </form>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Protocolo</th>
                <th style="width: 20%;">Usuário</th>
                <th style="width: 15%;">Data Fim</th>
                <th style="width: 30%;">Anexos Enviados</th>
                <th style="width: 20%;">Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result->num_rows > 0): ?>
                <?php while($chat = $result->fetch_assoc()): ?>
                
                <?php 
                    // Lógica para cálculo de 5 anos
                    $dataFechamento = new DateTime($chat['closed_at']);
                    $dataExpiracao = clone $dataFechamento;
                    $dataExpiracao->modify('+5 years');
                ?>

                <tr>
                    <td><strong><?php echo htmlspecialchars($chat['protocolo']); ?></strong></td>
                    <td><?php echo htmlspecialchars($chat['usuario_nome']); ?></td>
                    <td>
                        <?php echo $dataFechamento->format('d/m/Y H:i'); ?>
                        <span class="validade-info" title="Data legal de arquivamento">
                            <i class="fas fa-clock"></i> Expira: <?php echo $dataExpiracao->format('d/m/Y'); ?>
                        </span>
                    </td>
                    
                    <td>
                        <div class="attachment-list">
                            <?php
                            // Busca mensagens que são arquivos (JSON contendo _is_file)
                            $sqlFiles = "SELECT mensagem FROM chat_messages 
                                         WHERE chat_session_id = ? AND mensagem LIKE '%_is_file%'";
                            $stmtF = $conn->prepare($sqlFiles);
                            $stmtF->bind_param("i", $chat['id']);
                            $stmtF->execute();
                            $resF = $stmtF->get_result();
                            
                            $hasFile = false;
                            while($msg = $resF->fetch_assoc()){
                                $data = json_decode($msg['mensagem'], true);
                                if(isset($data['_is_file']) && $data['_is_file'] === true){
                                    $hasFile = true;
                                    $url = htmlspecialchars($data['url']);
                                    $name = htmlspecialchars($data['name'] ?? 'Arquivo');
                                    $type = $data['filetype'] ?? '';
                                    
                                    // Ajuste de caminho: O DB salva como '../assets/...', mas estamos em 'pages/admin/'
                                    // Precisamos voltar mais um nível, então transformamos '../' em '../../'
                                    $displayUrl = str_replace('../assets', '../../assets', $url);

                                    // Ícone baseado no tipo
                                    $iconClass = (strpos($type, 'image') !== false) ? 'fa-image attach-icon-img' : 'fa-file-pdf attach-icon-pdf';
                                    
                                    echo "<a href='{$displayUrl}' target='_blank' class='attach-link' title='{$name}'>
                                            <i class='fas {$iconClass}'></i> Ver
                                          </a>";
                                }
                            }
                            $stmtF->close();
                            
                            if(!$hasFile) {
                                echo "<span style='color:#999; font-size:0.85rem;'>Sem anexos</span>";
                            }
                            ?>
                        </div>
                    </td>

                    <td>
                        <a href="../../actions/gerar_pdf_chat.php?chat_id=<?php echo $chat['id']; ?>" class="btn btn-primary btn-sm" target="_blank" title="Baixar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        
                        <button type="button" class="btn btn-trash btn-sm" onclick="abrirModalExclusao(<?php echo $chat['id']; ?>)" title="Excluir Arquivo">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="text-align:center; color:#666; padding: 30px;">Nenhum arquivo encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="modalExclusao" class="modal-overlay">
    <div class="modal-content">
        <h3 class="modal-title">Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir este arquivo de suporte permanentemente?</p>
        <p style="font-size: 0.9rem; color: #d9534f;">Esta ação não pode ser desfeita.</p>
        
        <form id="formExclusao" action="../../actions/excluir_arquivo_suporte.php" method="POST">
            <input type="hidden" name="id" id="idExclusao">
            <div class="modal-buttons">
                <button type="button" class="btn btn-secondary" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn btn-danger">Sim, Excluir</button>
            </div>
        </form>
    </div>
</div>

<script>
    function abrirModalExclusao(id) {
        document.getElementById('idExclusao').value = id;
        document.getElementById('modalExclusao').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('modalExclusao').style.display = 'none';
    }

    // Fechar modal ao clicar fora
    window.onclick = function(event) {
        var modal = document.getElementById('modalExclusao');
        if (event.target == modal) {
            fecharModal();
        }
    }
</script>

</body>
</html>
<?php 
$stmt->close();
$conn->close(); 
?>