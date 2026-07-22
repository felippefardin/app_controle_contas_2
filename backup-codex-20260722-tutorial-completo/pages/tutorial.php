<?php
// pages/tutorial.php

// Inicia a sessão e configurações
include_once '../includes/session_init.php';
require_once '../database.php'; 

// Verificação básica de login
if (!isset($_SESSION['usuario_logado']) && !isset($_SESSION['super_admin'])) {
    header("Location: ../pages/login.php");
    exit();
}

// Recuperação de dados do usuário para personalização
$nome_usuario = 'Usuário';
$perfil = 'Visitante';

if (isset($_SESSION['super_admin'])) {
    $nome_usuario = $_SESSION['super_admin']['nome'] ?? 'Super Admin';
    $perfil = 'Super Admin';
} elseif (isset($_SESSION['usuario_id'])) {
    // Tenta buscar dados frescos do banco
    try {
        $conn = getTenantConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT nome, nivel_acesso FROM usuarios WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("i", $_SESSION['usuario_id']);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($d = $res->fetch_assoc()) {
                    $nome_usuario = $d['nome'];
                    $perfil = ucfirst(str_replace('_', ' ', $d['nivel_acesso']));
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        // Fallback para sessão se der erro no banco
        $nome_usuario = $_SESSION['usuario_nome'] ?? $nome_usuario;
    }
}

include_once '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual do Sistema</title>
    
    <style>
        :root {
            --tutorial-sidebar-width: 280px;
            --tutorial-accent: #007bff;
            --tutorial-bg-card: #1e1e1e; /* Ajuste conforme seu tema dark */
            --tutorial-text: #e0e0e0;
            --tutorial-text-muted: #a0a0a0;
        }

        /* Layout Grid */
        .tutorial-wrapper {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            gap: 30px;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: var(--tutorial-text);
        }

        /* Sidebar de Navegação */
        .tutorial-nav {
            width: var(--tutorial-sidebar-width);
            flex-shrink: 0;
            position: sticky;
            top: 100px; /* Ajuste conforme altura do seu header.php */
            height: calc(100vh - 120px);
            overflow-y: auto;
            background: rgba(30, 30, 30, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            padding: 20px;
        }

        .tutorial-nav h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--tutorial-text-muted);
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }

        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-links li {
            margin-bottom: 5px;
        }

        .nav-links a {
            display: block;
            padding: 10px 15px;
            color: var(--tutorial-text);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links a:hover, .nav-links a.active {
            background: rgba(0, 123, 255, 0.2);
            color: #66b0ff;
            transform: translateX(5px);
        }

        /* Conteúdo Principal */
        .tutorial-content {
            flex-grow: 1;
            min-width: 0; /* Evita estouro em flex items */
        }

        .intro-hero {
            background: linear-gradient(135deg, #0d47a1 0%, #007bff 100%);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .intro-hero::after {
            content: '\f19d'; /* FontAwesome Icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: -20px;
            bottom: -40px;
            font-size: 15rem;
            opacity: 0.1;
            color: white;
            transform: rotate(-15deg);
        }

        .intro-hero h1 { margin: 0; font-size: 2.5rem; color: white; }
        .intro-hero p { font-size: 1.1rem; color: rgba(255,255,255,0.9); margin-top: 10px; max-width: 600px; }

        /* Seções do Tutorial */
        .tutorial-section {
            scroll-margin-top: 120px; /* Para o scroll não ficar atrás do header */
            margin-bottom: 50px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.6s forwards;
        }

        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .section-icon {
            width: 50px;
            height: 50px;
            background: #333;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--tutorial-accent);
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.8rem;
            color: #fff;
        }

        /* Grid de Cards de Funcionalidade */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .tuto-card {
            background: var(--tutorial-bg-card);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 25px;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }

        .tuto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            border-color: var(--tutorial-accent);
        }

        .tuto-card h3 { margin-top: 0; color: #fff; display: flex; align-items: center; gap: 10px; }
        .tuto-card p { font-size: 0.95rem; line-height: 1.6; color: #bbb; }
        
        .tuto-card ul { padding-left: 20px; color: #aaa; font-size: 0.9rem; }
        .tuto-card ul li { margin-bottom: 5px; }

        .btn-jump {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.05);
            color: var(--tutorial-accent);
            text-decoration: none;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: background 0.2s;
        }
        .btn-jump:hover { background: var(--tutorial-accent); color: white; }

        /* Destaques Especiais */
        .highlight-box {
            background: rgba(40, 167, 69, 0.1);
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 6px 6px 0;
            font-size: 0.9rem;
        }

        .warning-box {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 6px 6px 0;
            font-size: 0.9rem;
            color: #e0ce96;
        }

        /* Fluxograma Simplificado CSS */
        .flow-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            background: #151515;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            gap: 10px;
        }
        .flow-step {
            flex: 1;
            text-align: center;
            background: #252525;
            padding: 15px;
            border-radius: 8px;
            min-width: 120px;
            position: relative;
        }
        .flow-step i { font-size: 2rem; margin-bottom: 10px; color: var(--tutorial-accent); }
        .flow-step span { display: block; font-weight: bold; color: white; }
        .flow-arrow { color: #555; font-size: 1.2rem; }

        @media (max-width: 900px) {
            .tutorial-wrapper { flex-direction: column; }
            .tutorial-nav { width: 100%; height: auto; position: static; margin-bottom: 20px; }
            .flow-arrow { display: none; }
        }
    </style>
</head>
<body>

<div class="tutorial-wrapper">

    <nav class="tutorial-nav">
        <h3><i class="fas fa-map-signs"></i> Guia Rápido</h3>
        <ul class="nav-links">
            <li><a href="#intro" class="active"><i class="fas fa-flag"></i> Início</a></li>
            <li><a href="#fluxo"><i class="fas fa-project-diagram"></i> Ciclo de Vida</a></li>
            <li><a href="#config"><i class="fas fa-cogs"></i> 1. Configurações</a></li>
            <li><a href="#pessoas"><i class="fas fa-users"></i> 2. Cadastros</a></li>
            <li><a href="#estoque"><i class="fas fa-boxes"></i> 3. Estoque</a></li>
            <li><a href="#vendas"><i class="fas fa-shopping-cart"></i> 4. Vendas (PDV)</a></li>
            <li><a href="#financeiro"><i class="fas fa-chart-pie"></i> 5. Financeiro</a></li>
            <li><a href="#fiscal"><i class="fas fa-file-invoice-dollar"></i> 6. Fiscal (NFC-e)</a></li>
            <li><a href="#relatorios"><i class="fas fa-chart-line"></i> 7. Relatórios</a></li>
            <li><a href="#suporte"><i class="fas fa-headset"></i> Ajuda</a></li>
        </ul>
    </nav>

    <main class="tutorial-content">

        <div id="intro" class="intro-hero">
            <h1>Bem-vindo ao Sistema</h1>
            <p>Olá, <strong><?= htmlspecialchars($nome_usuario) ?></strong>. Este é o seu manual interativo. Aqui você entenderá como transformar dados em lucro para sua empresa.</p>
            <br>
            <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-size: 0.9rem;">
                <i class="fas fa-id-badge"></i> Seu perfil: <?= htmlspecialchars($perfil) ?>
            </span>
        </div>

        <div id="fluxo" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-sync"></i></div>
                <div>
                    <h2>O Ciclo do Negócio</h2>
                    <span style="color:#aaa;">Entenda como os módulos conversam entre si</span>
                </div>
            </div>

            <div class="flow-container">
                <div class="flow-step">
                    <i class="fas fa-truck"></i>
                    <span>Compras</span>
                    <small>Entra Mercadoria</small>
                </div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-step">
                    <i class="fas fa-boxes"></i>
                    <span>Estoque</span>
                    <small>Armazenamento</small>
                </div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-step">
                    <i class="fas fa-cash-register"></i>
                    <span>Vendas</span>
                    <small>Sai Mercadoria</small>
                </div>
                <div class="flow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="flow-step">
                    <i class="fas fa-university"></i>
                    <span>Financeiro</span>
                    <small>Recebe Dinheiro</small>
                </div>
            </div>
        </div>

        <div id="config" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-sliders-h"></i></div>
                <h2>1. Configurações Essenciais</h2>
            </div>
            <p style="margin-bottom: 20px; color: #aaa;">Antes de operar, precisamos preparar o terreno. Sem isso, os relatórios não terão precisão.</p>
            
            <div class="cards-grid">
                <div class="tuto-card">
                    <h3><i class="fas fa-tags"></i> Categorias Financeiras</h3>
                    <p>Defina a origem e destino do dinheiro. O DRE (Demonstrativo de Resultado) depende 100% disso.</p>
                    <ul>
                        <li>Ex Receita: Venda de Produtos, Serviços.</li>
                        <li>Ex Despesa: Aluguel, Funcionários, Água.</li>
                    </ul>
                    <a href="categorias.php" class="btn-jump">Ir para Categorias &rarr;</a>
                </div>

                <div class="tuto-card">
                    <h3><i class="fas fa-landmark"></i> Contas Bancárias</h3>
                    <p>Cadastre onde seu dinheiro fica guardado.</p>
                    <ul>
                        <li>Crie uma conta "Caixa Físico" para o dinheiro da gaveta.</li>
                        <li>Cadastre seus Bancos reais.</li>
                    </ul>
                    <a href="banco_cadastro.php" class="btn-jump">Cadastrar Bancos &rarr;</a>
                </div>
            </div>
        </div>

        <div id="pessoas" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-address-book"></i></div>
                <h2>2. Pessoas & Usuários</h2>
            </div>
            
            <div class="cards-grid">
                <div class="tuto-card">
                    <h3><i class="fas fa-user-friends"></i> Clientes e Fornecedores</h3>
                    <p>Centralize seus contatos. Essencial para emitir notas e boletos.</p>
                    <div class="highlight-box">
                        <i class="fas fa-check"></i> Dica: O sistema preenche o endereço automaticamente pelo CEP.
                    </div>
                    <a href="cadastrar_pessoa_fornecedor.php" class="btn-jump">Gerenciar Pessoas &rarr;</a>
                </div>

                <div class="tuto-card">
                    <h3><i class="fas fa-user-shield"></i> Usuários do Sistema</h3>
                    <p>Adicione sua equipe e defina o que cada um pode ver.</p>
                    <ul>
                        <li>Vendedor: Acesso ao PDV.</li>
                        <li>Admin: Acesso total.</li>
                    </ul>
                    <a href="usuarios.php" class="btn-jump">Gerenciar Equipe &rarr;</a>
                </div>
            </div>
        </div>

        <div id="estoque" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-box-open"></i></div>
                <h2>3. Gestão de Estoque</h2>
            </div>

            <div class="cards-grid">
                <div class="tuto-card">
                    <h3><i class="fas fa-barcode"></i> Cadastro de Produtos</h3>
                    <p>A base da sua venda. Cadastre fotos, códigos de barras e preços.</p>
                    <ul>
                        <li><strong>Preço de Custo:</strong> Crucial para saber seu lucro real.</li>
                        <li><strong>Estoque Mínimo:</strong> O sistema avisa quando comprar mais.</li>
                    </ul>
                    <a href="controle_estoque.php" class="btn-jump">Ver Produtos &rarr;</a>
                </div>

                <div class="tuto-card">
                    <h3><i class="fas fa-truck-loading"></i> Entrada de Notas (XML)</h3>
                    <p>Não perca tempo cadastrando manualmente. Importe o XML da nota fiscal do fornecedor.</p>
                    <div class="highlight-box">
                        Ao importar o XML, o sistema cadastra o produto e aumenta o estoque automaticamente.
                    </div>
                    <a href="compras.php" class="btn-jump">Registrar Compra &rarr;</a>
                </div>
            </div>
        </div>

        <div id="vendas" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-shopping-basket"></i></div>
                <h2>4. Frente de Caixa (PDV)</h2>
            </div>
            
            <div class="cards-grid">
                <div class="tuto-card">
                    <h3><i class="fas fa-cash-register"></i> Realizar Venda</h3>
                    <p>O processo é simples e rápido:</p>
                    <ol style="padding-left: 15px; color:#aaa; font-size:0.9rem;">
                        <li>Abra o caixa (se estiver fechado).</li>
                        <li>Selecione o cliente (ou venda anônima).</li>
                        <li>Bipe ou busque os produtos.</li>
                        <li>Escolha a forma de pagamento e finalize.</li>
                    </ol>
                    <a href="vendas.php" class="btn-jump">Ir para o PDV &rarr;</a>
                </div>

                <div class="tuto-card">
                    <h3><i class="fas fa-wallet"></i> Controle de Caixa</h3>
                    <p>Gerencie o dinheiro físico da gaveta.</p>
                    <ul>
                        <li><strong>Sangria:</strong> Retirar dinheiro para levar ao banco.</li>
                        <li><strong>Suprimento:</strong> Adicionar troco no início do dia.</li>
                        <li><strong>Fechamento:</strong> Conferência final do dia.</li>
                    </ul>
                    <a href="lancamento_caixa.php" class="btn-jump">Abrir/Fechar Caixa &rarr;</a>
                </div>
            </div>
        </div>

        <div id="financeiro" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-money-bill-wave"></i></div>
                <h2>5. Gestão Financeira</h2>
            </div>

            <div class="warning-box" style="margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Conceito Importante:</strong> Lançar uma conta não muda seu saldo. Você precisa confirmar o pagamento/recebimento clicando no botão de "Baixa" (<i class="fas fa-check"></i>).
            </div>

            <div class="cards-grid">
                <div class="tuto-card" style="border-left: 3px solid #dc3545;">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Contas a Pagar</h3>
                    <p>Registre boletos de fornecedores, contas de luz e salários.</p>
                    <a href="contas_pagar.php" class="btn-jump">Ver Contas a Pagar &rarr;</a>
                </div>

                <div class="tuto-card" style="border-left: 3px solid #28a745;">
                    <h3><i class="fas fa-hand-holding-usd"></i> Contas a Receber</h3>
                    <p>Monitore vendas a prazo, cartões e crediário.</p>
                    <a href="contas_receber.php" class="btn-jump">Ver Contas a Receber &rarr;</a>
                </div>
            </div>
        </div>

        <div id="fiscal" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-qrcode"></i></div>
                <h2>6. Emissão Fiscal (NFC-e)</h2>
            </div>
            
            <div class="cards-grid">
                <div class="tuto-card">
                    <h3><i class="fas fa-receipt"></i> Nota Fiscal do Consumidor</h3>
                    <p>Para emitir notas, certifique-se de preencher:</p>
                    <ul>
                        <li>Dados da empresa em "Minha Assinatura/Configuração".</li>
                        <li>Certificado Digital (A1).</li>
                        <li>NCM e Tributação nos Produtos.</li>
                    </ul>
                    <a href="configuracao_fiscal.php" class="btn-jump">Configuração Fiscal &rarr;</a>
                </div>
            </div>
        </div>

        <div id="relatorios" class="tutorial-section">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-chart-bar"></i></div>
                <h2>7. Análise de Resultados</h2>
            </div>
            <p>Onde a mágica acontece. Transforme dados em estratégia.</p>

            <div class="cards-grid">
                <div class="tuto-card">
                    <h3><i class="fas fa-calculator"></i> DRE Gerencial</h3>
                    <p>O relatório mais importante. Mostra se sua empresa teve <strong>Lucro</strong> ou <strong>Prejuízo</strong> no mês, cruzando vendas e despesas categorizadas.</p>
                </div>
                <div class="tuto-card">
                    <h3><i class="fas fa-exchange-alt"></i> Fluxo de Caixa</h3>
                    <p>Analise a movimentação diária do dinheiro. Descubra em quais dias do mês falta dinheiro e se planeje.</p>
                </div>
                <div class="tuto-card">
                    <h3><i class="fas fa-sort-amount-up"></i> Curva ABC</h3>
                    <p>Descubra quais são os produtos "Estrelas" (que mais vendem) e quais estão parados no estoque.</p>
                </div>
            </div>
             <a href="relatorios.php" class="btn-jump" style="display:block; text-align:center; margin-top:15px; width:200px;">Acessar Relatórios Completos</a>
        </div>

        <div id="suporte" class="tutorial-section" style="margin-bottom: 100px;">
            <div class="section-header">
                <div class="section-icon"><i class="fas fa-life-ring"></i></div>
                <h2>Precisa de Ajuda?</h2>
            </div>
            <div class="tuto-card">
                <p>Nossa equipe está pronta para ajudar você a crescer.</p>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="suporte.php" class="btn-jump"><i class="fas fa-ticket-alt"></i> Abrir Chamado</a>
                    <a href="chat_suporte_online.php" class="btn-jump"><i class="fas fa-comments"></i> Chat Online</a>
                    <a href="lembrete.php" class="btn-jump"><i class="fas fa-bell"></i> Ver meus Lembretes</a>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    // Script para destacar o menu lateral conforme o scroll
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.tutorial-section, .intro-hero');
        const navLi = document.querySelectorAll('.nav-links a');

        window.addEventListener('scroll', () => {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                // Ajuste de offset para ativar o link um pouco antes de chegar na seção
                if (pageYOffset >= (sectionTop - 150)) {
                    current = section.getAttribute('id');
                }
            });

            navLi.forEach(a => {
                a.classList.remove('active');
                if (a.getAttribute('href').includes(current)) {
                    a.classList.add('active');
                }
            });
        });
    });
</script>

<?php include_once '../includes/footer.php'; ?>
</body>
</html>