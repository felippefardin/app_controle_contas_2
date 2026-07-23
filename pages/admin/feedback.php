<?php
require_once '../../includes/session_init.php';
include('../../database.php');

require_super_admin();

// Verifique permissão admin

$conn = getMasterConnection();
$result = $conn->query("SELECT * FROM feedbacks ORDER BY criado_em DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../../assets/css/universal-theme.css">
<script src="../../assets/js/global-theme.js"></script>
    <?php require __DIR__ . '/../../includes/csrf_client.php'; ?>
    <meta charset="UTF-8">
    <title>Gerenciar Feedbacks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-dark text-light p-4">
    <div class="container">
        <h2 class="text-warning mb-4"><i class="fa-solid fa-star"></i> Moderação de Feedbacks</h2>
        
        <div class="row">
            <?php while($row = $result->fetch_assoc()): ?>
            <div class="col-md-6 mb-3">
                <div class="card bg-secondary text-white border-<?= $row['aprovado'] ? 'success' : ($row['lido'] ? 'secondary' : 'warning') ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title"><?= htmlspecialchars($row['nome']) ?></h5>
                            <span class="text-warning">
                                <?= str_repeat('<i class="fa-solid fa-star"></i>', $row['pontuacao']) ?>
                            </span>
                        </div>
                        <p class="card-text mt-2">"<?= htmlspecialchars($row['descricao']) ?>"</p>
                        <div class="small text-light opacity-75 mb-3">
                            <?= !$row['anonimo'] ? $row['email'] . ' | ' . $row['whatsapp'] : 'Anônimo' ?>
                            <br>Data: <?= date('d/m/Y H:i', strtotime($row['criado_em'])) ?>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <?php if(!$row['aprovado']): ?>
                                <form action="../../actions/gerenciar_feedback.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="acao" value="aprovar" class="btn btn-sm btn-success"><i class="fa-solid fa-check"></i> Aprovar (Publicar)</button>
                                </form>
                            <?php else: ?>
                                <form action="../../actions/gerenciar_feedback.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="acao" value="reprovar" class="btn btn-sm btn-warning text-dark"><i class="fa-solid fa-ban"></i> Desaprovar</button>
                                </form>
                            <?php endif; ?>
                            
                            <form action="../../actions/gerenciar_feedback.php" method="POST" onsubmit="return confirm('Excluir permanentemente?');">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" name="acao" value="excluir" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i> Excluir</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
