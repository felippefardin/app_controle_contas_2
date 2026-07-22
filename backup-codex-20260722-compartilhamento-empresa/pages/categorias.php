<?php
require_once '../includes/session_init.php';
require_once '../database.php'; 
require_once '../includes/utils.php'; // Importa utils para Flash Messages

// 1. VERIFICA LOGIN
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}
$conn = getTenantConnection();
if ($conn === null) {
    die("Falha ao obter a conexão com o banco de dados do cliente.");
}

// 2. PEGA DADOS
$usuarioId = $_SESSION['usuario_id'];
$perfil = $_SESSION['nivel_acesso'] ?? 'padrao';

include('../includes/header.php');

// 3. BUSCA CATEGORIAS
$where = ["id_usuario = " . intval($usuarioId)];
$sql = "SELECT * FROM categorias";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY tipo, nome ASC";

$result = $conn->query($sql);
$categorias = [];
while ($row = $result->fetch_assoc()) {
    $categorias[] = $row;
}

// EXIBE O POP-UP CENTRALIZADO
display_flash_message();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Categorias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* === CONFIGURAÇÃO BASE === */
        body { 
            background-color: #121212; 
            color: #eee; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            padding: 20px; 
        }

        /* === CONTAINER RESPONSIVO (FULL DESKTOP) === */
        .container { 
            width: 95%;             /* Ocupa a largura disponível com margem de segurança */
            max-width: 1400px;      /* Limite para telas ultrawide (Full Desktop) */
            margin: 20px auto; 
            background-color: #1f1f1f; 
            padding: 30px; 
            border-radius: 8px; 
            box-sizing: border-box; /* Garante que padding não estoure a largura */
        }

        h2, h3 { 
            text-align: center; 
            color: #00bfff; 
            margin-bottom: 20px; 
        }
        
        /* === BOTÃO DE ADICIONAR === */
        .btn-add { 
            background-color: #00bfff; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: bold; 
            margin-bottom: 25px; 
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn-add:hover { 
            background-color: #0099cc; 
        }
        
        /* === TABELA === */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            table-layout: auto; /* Permite ajuste fluido das colunas */
        }
        
        th, td { 
            padding: 15px; 
            border-bottom: 1px solid #333; 
            text-align: left; 
            font-size: 16px;
        }
        
        tr:hover { 
            background-color: #2a2a2a; 
        }
        
        /* === AÇÕES DA TABELA === */
        .actions { 
            text-align: right; 
            white-space: nowrap; /* Evita que botões quebrem linha no desktop */
            width: 1%; /* Força a coluna a ocupar apenas o espaço necessário */
        }
        
        .btn-icon { 
            background: none; 
            border: none; 
            cursor: pointer; 
            font-size: 15px; 
            margin-left: 15px; 
            text-decoration: none; 
            padding: 5px; /* Aumenta área de clique */
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-edit { color: #00bfff; }
        .btn-delete { color: #e74c3c; }
        .btn-edit:hover { text-decoration: underline; }
        .btn-delete:hover { text-decoration: underline; }

        /* === MODAL STYLES === */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.7); 
            justify-content: center; 
            align-items: center; 
            padding: 10px; /* Espaço nas bordas em mobile */
            box-sizing: border-box;
        }
        
        .modal-content { 
            background-color: #282828; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.3); 
            width: 100%; 
            max-width: 500px; 
            position: relative; 
            box-sizing: border-box;
        }
        
        .modal-content .close-btn { 
            color: #aaa; 
            position: absolute; 
            top: 15px; 
            right: 20px; 
            font-size: 28px; 
            font-weight: bold; 
            cursor: pointer; 
            line-height: 1;
        }
        .modal-content .close-btn:hover { color: #00bfff; }
        
        .modal-content form { display: flex; flex-direction: column; gap: 15px; }
        .modal-content form input, .modal-content form select, .modal-content form button, .btn-modal { 
            width: 100%; 
            padding: 12px; 
            font-size: 16px; 
            border-radius: 5px; 
            border: 1px solid #444; 
            background-color: #333; 
            color: #eee; 
            box-sizing: border-box;
        }
        
        .modal-content form button[type="submit"] { background-color: #27ae60; cursor: pointer; font-weight: bold; border: none; }
        .modal-content form button[type="submit"]:hover { background-color: #218838; }
        
        .btn-cancel { background-color: #555; color: white; border: none; cursor: pointer; font-weight: bold; }
        .btn-confirm-delete { background-color: #e74c3c; color: white; border: none; cursor: pointer; font-weight: bold; }

        /* === RESPONSIVIDADE (TABLET E MOBILE) === */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                width: 100%;
                padding: 15px;
                margin: 10px 0;
                border-radius: 0; /* Remove bordas arredondadas em telas muito pequenas para aproveitar espaço */
            }

            h2, h3 {
                font-size: 1.4em;
            }

            /* Botão Add ocupa largura total no mobile para facilitar o clique */
            .btn-add {
                width: 100%;
                text-align: center;
                margin-bottom: 20px;
            }

            /* Ajustes da tabela para mobile */
            th, td {
                padding: 12px 8px; /* Menos padding lateral */
                font-size: 14px;   /* Fonte levemente menor */
            }

            /* Coluna de ações: empilha ou ajusta ícones */
            .actions {
                width: auto;
                text-align: right;
            }

            .btn-icon {
                margin-left: 8px;
                font-size: 14px;
                /* Removemos o texto no mobile se ficar muito apertado, 
                   mas aqui vamos manter e ajustar o espaçamento */
                padding: 8px; /* Área de toque maior */
                background-color: #2a2a2a; /* Fundo sutil para parecer botão tátil */
                border-radius: 4px;
            }
            
            /* Modal ocupa quase toda a tela no mobile */
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }

        /* === AJUSTE PARA TELAS MUITO PEQUENAS (IPHONE SE/ANTIGOS) === */
        @media (max-width: 400px) {
            .btn-icon span {
                display: none; /* Esconde o texto "Editar/Excluir" mantendo só o ícone */
            }
            .btn-icon i {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-tags"></i> Gerenciar Categorias</h2>
    
    <button class="btn-add" onclick="abrirModal()">➕ Nova Categoria</button>

    <h3>Categorias de Despesa</h3>
    <div style="overflow-x: auto;"> <table>
            <tbody>
                <?php foreach ($categorias as $categoria): if($categoria['tipo'] == 'despesa'): ?>
                <tr>
                    <td><?= htmlspecialchars($categoria['nome']) ?></td>
                    <td class="actions">
                        <button class="btn-icon btn-edit" onclick="abrirModal(<?= htmlspecialchars(json_encode($categoria)) ?>)">
                            <i class="fa fa-pen"></i> <span>Editar</span>
                        </button>
                        <button class="btn-icon btn-delete" onclick="abrirModalExcluir(<?= $categoria['id'] ?>, '<?= addslashes($categoria['nome']) ?>')">
                            <i class="fa fa-trash"></i> <span>Excluir</span>
                        </button>
                    </td>
                </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3 style="margin-top: 30px;">Categorias de Receita</h3>
    <div style="overflow-x: auto;">
        <table>
            <tbody>
                <?php foreach ($categorias as $categoria): if($categoria['tipo'] == 'receita'): ?>
                <tr>
                    <td><?= htmlspecialchars($categoria['nome']) ?></td>
                    <td class="actions">
                        <button class="btn-icon btn-edit" onclick="abrirModal(<?= htmlspecialchars(json_encode($categoria)) ?>)">
                            <i class="fa fa-pen"></i> <span>Editar</span>
                        </button>
                        <button class="btn-icon btn-delete" onclick="abrirModalExcluir(<?= $categoria['id'] ?>, '<?= addslashes($categoria['nome']) ?>')">
                            <i class="fa fa-trash"></i> <span>Excluir</span>
                        </button>
                    </td>
                </tr>
                <?php endif; endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="categoriaModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="fecharModal()">&times;</span>
        <h3 id="modalTitle">Nova Categoria</h3>
        <form id="categoriaForm" action="../actions/salvar_categoria.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <input type="hidden" name="id" id="categoriaId">
            <input type="text" name="nome" id="categoriaNome" placeholder="Nome da Categoria" required>
            <select name="tipo" id="categoriaTipo" required>
                <option value="despesa">Despesa</option>
                <option value="receita">Receita</option>
            </select>
            <button type="submit">Salvar</button>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('deleteModal').style.display='none'">&times;</span>
        <h3 style="color:#e74c3c">Confirmar Exclusão</h3>
        <p>Tem certeza que deseja excluir a categoria <strong id="nomeExclusao"></strong>?</p>
        <p style="font-size:0.9em; color:#aaa;">Isso removerá a associação com todos os lançamentos existentes.</p>
        
        <form action="../actions/excluir_categoria.php" method="POST" style="margin-top:20px;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="id" id="idExclusao">
            
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-modal btn-cancel" onclick="document.getElementById('deleteModal').style.display='none'">Cancelar</button>
                <button type="submit" class="btn-modal btn-confirm-delete">Excluir</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal Editar/Criar
    function abrirModal(categoria = null) {
        if (categoria) {
            document.getElementById('modalTitle').innerText = 'Editar Categoria';
            document.getElementById('categoriaId').value = categoria.id;
            document.getElementById('categoriaNome').value = categoria.nome;
            document.getElementById('categoriaTipo').value = categoria.tipo;
        } else {
            document.getElementById('modalTitle').innerText = 'Nova Categoria';
            document.getElementById('categoriaForm').reset();
            document.getElementById('categoriaId').value = '';
        }
        document.getElementById('categoriaModal').style.display = 'flex';
    }

    function fecharModal() {
        document.getElementById('categoriaModal').style.display = 'none';
    }
    
    // Modal Excluir
    function abrirModalExcluir(id, nome) {
        document.getElementById('idExclusao').value = id;
        document.getElementById('nomeExclusao').innerText = nome;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>

<?php include('../includes/footer.php'); ?>

</body>
</html>