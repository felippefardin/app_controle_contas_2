<?php
// includes/utils.php

/*
 * Converte valor monetário brasileiro para float do banco de dados
 * Ex: "R$ 1.250,50" vira 1250.50
 */
function brl_to_float($valor) {
    if (empty($valor)) return 0.00;
    $valor = preg_replace('/[^\d,]/', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return (float) $valor;
}

/*
 * Valida e formata datas para o MySQL (YYYY-MM-DD)
 */
function data_para_iso($data) {
    if (empty($data)) return date('Y-m-d');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return $data;
    $partes = explode('/', $data);
    if (count($partes) === 3) {
        if (checkdate($partes[1], $partes[0], $partes[2])) {
            return "{$partes[2]}-{$partes[1]}-{$partes[0]}";
        }
    }
    return null;
}

/*
 * Define a mensagem na sessão
 */
function set_flash_message($tipo, $mensagem) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash_message'] = [
        'tipo' => $tipo,
        'msg' => $mensagem
    ];
}

/*
 * Exibe a mensagem CENTRALIZADA E CHAMATIVA
 */

function calcularMediaMensalHistorica($conn, $usuarioId) {
    // Média de entradas (Receber Baixado + Caixa Diário)
    $sqlEntradas = "SELECT (
        COALESCE((SELECT SUM(valor) FROM contas_receber
            WHERE usuario_id=? AND status='baixada' AND deleted_at IS NULL
            AND data_baixa >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),0)
        + COALESCE((SELECT SUM(valor) FROM caixa_diario
            WHERE usuario_id=? AND tipo='manual_entrada'
            AND data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),0)
        ) / 6 AS media";
    
    // Média de saídas (Pagar Baixado)
    $sqlSaidas = "SELECT (
        COALESCE((SELECT SUM(valor) FROM contas_pagar
            WHERE usuario_id=? AND status='baixada' AND deleted_at IS NULL
            AND data_baixa >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),0)
        + COALESCE((SELECT SUM(valor) FROM caixa_diario
            WHERE usuario_id=? AND tipo='manual_saida'
            AND data >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)),0)
        ) / 6 AS media";

    $stmt = $conn->prepare($sqlEntradas);
    $stmt->bind_param('ii', $usuarioId, $usuarioId);
    $stmt->execute();
    $resE = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare($sqlSaidas);
    $stmt->bind_param('ii', $usuarioId, $usuarioId);
    $stmt->execute();
    $resS = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'entradas' => (float) ($resE['media'] ?? 0),
        'saidas' => (float) ($resS['media'] ?? 0)
    ];
}

function display_flash_message() {
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        // Mapeia tipos para classes de cor
        $classeCor = 'alert-info';
        if ($flash['tipo'] == 'success') $classeCor = 'alert-success';
        if ($flash['tipo'] == 'danger' || $flash['tipo'] == 'error') $classeCor = 'alert-danger';
        if ($flash['tipo'] == 'warning') $classeCor = 'alert-warning';
        
        // Ícone baseado no tipo
        $icone = '';
        if ($flash['tipo'] == 'success') $icone = '<i class="fa fa-check-circle" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>';
        if ($flash['tipo'] == 'danger') $icone = '<i class="fa fa-times-circle" style="font-size: 40px; display: block; margin-bottom: 10px;"></i>';

        echo "
        <div class='alert-overlay' id='flash-overlay'>
            <div class='alert-box {$classeCor}'>
                {$icone}
                <div class='alert-msg'>{$flash['msg']}</div>
                <button onclick='fecharFlash()' class='btn-fechar-alert'>OK</button>
            </div>
        </div>
        <style>
            /* Fundo escuro para focar na mensagem */
            .alert-overlay {
                position: fixed;
                top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.7);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
                animation: fadeIn 0.3s;
            }
            
            /* A caixa da mensagem */
            .alert-box {
                padding: 30px;
                border-radius: 12px;
                color: #fff;
                text-align: center;
                min-width: 320px;
                max-width: 90%;
                box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                transform: scale(0.8);
                animation: zoomIn 0.3s forwards;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                border: 1px solid rgba(255,255,255,0.2);
            }

            /* Cores */
            .alert-success { background: linear-gradient(135deg, #27ae60, #2ecc71); }
            .alert-danger { background: linear-gradient(135deg, #c0392b, #e74c3c); }
            .alert-warning { background: linear-gradient(135deg, #f39c12, #f1c40f); }
            .alert-info { background: linear-gradient(135deg, #2980b9, #3498db); }

            .alert-msg {
                font-size: 18px;
                margin-bottom: 20px;
                line-height: 1.5;
            }

            /* Botão de fechar bonito */
            .btn-fechar-alert {
                background: rgba(0,0,0,0.2);
                border: 1px solid rgba(255,255,255,0.4);
                color: white;
                padding: 8px 25px;
                border-radius: 20px;
                cursor: pointer;
                font-size: 16px;
                transition: background 0.2s;
            }
            .btn-fechar-alert:hover { background: rgba(0,0,0,0.4); }

            @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
            @keyframes zoomIn { 
                from { opacity:0; transform: scale(0.5); } 
                to { opacity:1; transform: scale(1); } 
            }
        </style>
        <script>
            function fecharFlash() {
                document.getElementById('flash-overlay').style.display = 'none';
            }
            // Auto-fechar após 3 segundos (opcional, se quiser que obrigue o clique, remova isso)
            setTimeout(fecharFlash, 3500);
        </script>
        ";
        
        unset($_SESSION['flash_message']);
    }
}
?>
