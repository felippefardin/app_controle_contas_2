<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa Flash Messages

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    // Se for AJAX, retorna erro JSON, senão redireciona
    if (isset($_POST['ajax'])) {
        echo json_encode(['status' => 'error', 'message' => 'Usuário não logado']);
        exit;
    }
    header('Location: ../pages/login.php');
    exit;
}

// 2. VERIFICA MÉTODO POST E CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica se é chamada AJAX
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] == 'true';

    // Verifica Token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'Token inválido']);
            exit;
        }
        set_flash_message('danger', 'Token de segurança inválido. Tente novamente.');
        header('Location: ../pages/categorias.php');
        exit;
    }

    $conn = getTenantConnection();
    if ($conn === null) {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'Erro de conexão']);
            exit;
        }
        set_flash_message('danger', 'Erro de conexão com o banco de dados.');
        header('Location: ../pages/categorias.php');
        exit;
    }

    $usuarioId = $_SESSION['usuario_id'];
    
    $nome = trim($_POST['nome']);
    $tipo = $_POST['tipo'];
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    if (empty($nome) || empty($tipo)) {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'Preencha nome e tipo']);
            exit;
        }
        set_flash_message('warning', 'Preencha o nome e o tipo da categoria.');
        header('Location: ../pages/categorias.php');
        exit;
    }

    $insertedId = null;

    // 3. INSERIR OU ATUALIZAR
    if (empty($id)) { 
        // Nova Categoria
        $stmt = $conn->prepare("INSERT INTO categorias (id_usuario, nome, tipo) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $usuarioId, $nome, $tipo);
        }
    } else { 
        // Atualizar (Garante propriedade do usuário)
        $stmt = $conn->prepare("UPDATE categorias SET nome = ?, tipo = ? WHERE id = ? AND id_usuario = ?");
        if ($stmt) {
            $stmt->bind_param("ssii", $nome, $tipo, $id, $usuarioId);
        }
    }

    if ($stmt) {
        if ($stmt->execute()) {
            $insertedId = empty($id) ? $stmt->insert_id : $id;
            
            if ($isAjax) {
                // Retorna JSON para o modal atualizar o select
                echo json_encode([
                    'status' => 'success', 
                    'id' => $insertedId, 
                    'nome' => $nome,
                    'message' => 'Categoria salva com sucesso!'
                ]);
                $stmt->close();
                exit;
            }

            set_flash_message('success', 'Categoria salva com sucesso!');
        } else {
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar: ' . $stmt->error]);
                $stmt->close();
                exit;
            }
            set_flash_message('danger', 'Erro ao salvar: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'Erro na preparação da query']);
            exit;
        }
        set_flash_message('danger', 'Erro na preparação da query.');
    }
    
    header('Location: ../pages/categorias.php');
    exit;
}

header('Location: ../pages/categorias.php');
exit;
?>