<?php
// pages/checkout_plano.php
require_once '../includes/session_init.php';
require_once '../includes/config/config.php'; // Carrega configurações (MercadoPago, etc)

// Verifica permissão
if (!isset($_SESSION['nivel_acesso']) || ($_SESSION['nivel_acesso'] !== 'admin' && $_SESSION['nivel_acesso'] !== 'master' && $_SESSION['nivel_acesso'] !== 'proprietario')) {
    header("Location: home.php");
    exit;
}

// Recupera o plano enviado via POST (da tela minha_assinatura) ou GET
$plano_selecionado = $_POST['plano'] ?? $_GET['plano'] ?? '';

// Definição dos planos e preços (Isso poderia vir do banco de dados futuramente)
$tabela_planos = [
    'basico'    => [
        'nome' => 'Plano Básico', 
        'preco' => 19,00, // Exemplo de valor
        'limite' => 3,
        'desc' => 'Ideal para pequenos negócios'
    ],
    'plus'      => [
        'nome' => 'Plano Plus', 
        'preco' => 39,00, // Exemplo de valor
        'limite' => 6,
        'desc' => 'Para empresas em crescimento'
    ],
    'essencial' => [
        'nome' => 'Plano Essencial', 
        'preco' => 59,00, // Exemplo de valor
        'limite' => 16,
        'desc' => 'Gestão completa para sua equipe'
    ]
];

// Se o plano não for válido, volta para a seleção
if (!array_key_exists($plano_selecionado, $tabela_planos)) {
    $_SESSION['erro'] = "Plano inválido selecionado.";
    header("Location: minha_assinatura.php");
    exit;
}

$dados_plano = $tabela_planos[$plano_selecionado];

include('../includes/header.php');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Confirmar Plano</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilo Neon Dark Consistente */
        body { background-color: #121212; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .container-checkout { 
            max-width: 900px; 
            margin: 50px auto; 
            padding: 0 20px; 
        }

        .checkout-card {
            background-color: #1e1e1e;
            border: 1px solid #333;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: row;
        }

        @media(max-width: 768px) { .checkout-card { flex-direction: column; } }

        .plan-summary {
            padding: 40px;
            flex: 1;
            border-right: 1px solid #333;
        }

        .payment-area {
            padding: 40px;
            flex: 1;
            background-color: #252525;
        }

        .checkout-title {
            color: #00bfff;
            font-size: 1.5rem;
            margin-bottom: 20px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
        }

        .price-tag {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
            margin: 20px 0;
        }
        .price-tag span { font-size: 1rem; color: #aaa; font-weight: normal; }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #ccc;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.5);
        }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #888;
            text-decoration: none;
        }
        .btn-back:hover { color: #fff; }

        .secure-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2ecc71;
            font-size: 0.9rem;
            margin-bottom: 20px;
            background: rgba(46, 204, 113, 0.1);
            padding: 10px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container-checkout">
    <div class="checkout-card">
        
        <div class="plan-summary">
            <h3 class="checkout-title"><i class="fa-solid fa-cart-shopping"></i> Resumo do Pedido</h3>
            
            <h4 class="text-white mb-1"><?= htmlspecialchars($dados_plano['nome']) ?></h4>
            <p class="text-muted"><?= htmlspecialchars($dados_plano['desc']) ?></p>

            <div class="price-tag">
                R$ <?= number_format($dados_plano['preco'], 2, ',', '.') ?> <span>/mês</span>
            </div>

            <hr style="border-color: #444;">

            <div class="detail-row">
                <span>Limite de Usuários:</span>
                <strong class="text-white"><?= $dados_plano['limite'] ?> Usuários</strong>
            </div>
            <div class="detail-row">
                <span>Suporte:</span>
                <strong class="text-white">Prioritário</strong>
            </div>
            <div class="detail-row">
                <span>Cobrança:</span>
                <strong class="text-white">Mensal</strong>
            </div>
        </div>

        <div class="payment-area">
            <h3 class="checkout-title"><i class="fa-solid fa-credit-card"></i> Pagamento</h3>

            <div class="secure-badge">
                <i class="fa-solid fa-lock"></i> Ambiente Seguro (SSL)
            </div>

            <p style="color: #ccc; font-size: 0.9rem; line-height: 1.5;">
                Ao confirmar, seu plano será atualizado imediatamente. 
                O valor será cobrado no seu método de pagamento padrão.
            </p>

            <form action="../actions/checkout_plano.php" method="POST" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="plano" value="<?= htmlspecialchars($plano_selecionado) ?>">
                
                <button type="submit" class="btn-confirm">
                    <i class="fa-solid fa-check"></i> Confirmar e Assinar
                </button>
            </form>

            <a href="minha_assinatura.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Voltar e escolher outro plano
            </a>
        </div>

    </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
    // Previne duplo clique no formulário
    document.getElementById('paymentForm').addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';
    });
</script>

</body>
</html>
