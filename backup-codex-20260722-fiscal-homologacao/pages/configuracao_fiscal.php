<?php
require_once '../includes/session_init.php';
require_once '../database.php';
require_once '../includes/utils.php'; // Importa utils

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

$conn = getTenantConnection();
if ($conn === null) {
    die("Erro: Falha ao conectar ao banco de dados.");
}

$dadosEmpresa = [];

// 1. Busca dados cadastrais
$stmt = $conn->query("SELECT * FROM empresa_config LIMIT 1");
if ($stmt && $stmt->num_rows > 0) {
    $dadosEmpresa = $stmt->fetch_assoc();
}

// 2. Busca dados fiscais
$stmtKv = $conn->query("SELECT chave, valor FROM configuracoes_tenant");
if ($stmtKv) {
    while ($row = $stmtKv->fetch_assoc()) {
        $dadosEmpresa[$row['chave']] = $row['valor'];
    }
}

include('../includes/header.php');

// EXIBE MENSAGEM
display_flash_message();
?>

<style>
:root {
    --bg-card: #1f1f1f;
    --bg-input: #2a2a2a;
    --bg-hover: #333;
    --border-color: #3a3a3a;
    --text-primary: #fff;
    --text-secondary: #bdbdbd;
    --primary-color: #00bfff;
    --primary-hover: #009ed1;
}

/* Reset básico para garantir responsividade */
* { box-sizing: border-box; }

html, body { height: 100%; margin: 0; }

/* === CONTAINER PRINCIPAL (FULL DESKTOP) === */
.fiscal-container { 
    width: 98%; 
    max-width: 1600px; /* Expandido para aproveitar telas grandes */
    margin: 0 auto; 
    padding: 30px; 
    min-height: calc(100vh - 120px); 
    display: flex; 
    flex-direction: column; 
    animation: fadeIn .6s ease; 
}

/* === TIPOGRAFIA E TÍTULOS === */
.page-title { 
    font-size: 2.1rem; 
    font-weight: 700; 
    color: var(--primary-color); 
    display: flex; 
    align-items: center; 
    gap: 14px; 
    margin-bottom: 30px; 
    padding-bottom: 15px; 
    border-bottom: 1px solid var(--border-color); 
}
.page-title i { font-size: 2rem; }

/* === CARDS === */
.card { 
    background: var(--bg-card); 
    border-radius: 12px; 
    border: 1px solid var(--border-color); 
    box-shadow: 0 6px 16px rgba(0,0,0,0.45); 
    overflow: hidden; 
    height: 100%; 
    display: flex; 
    flex-direction: column; 
}

.card-header { 
    padding: 18px 22px; 
    background: rgba(255,255,255,0.04); 
    color: var(--primary-color); 
    font-size: 1.15rem; 
    font-weight: 600; 
    border-bottom: 1px solid var(--border-color); 
    display: flex; 
    align-items: center; 
    gap: 10px; 
}

.card-body { 
    padding: 28px; 
    flex: 1; 
}

/* === FORMULÁRIOS === */
label { 
    color: var(--text-secondary); 
    font-size: .9rem; 
    margin-bottom: 6px; 
    display: block; 
    font-weight: 500; 
}

.form-control { 
    background: var(--bg-input); 
    border: 1px solid var(--border-color); 
    color: var(--text-primary); 
    border-radius: 6px; 
    height: 46px; 
    width: 100%; 
    padding-left: 12px; 
    transition: .2s; 
}

.form-control:hover { background: var(--bg-hover); }
.form-control:focus { 
    border-color: var(--primary-color); 
    background: #303030; 
    box-shadow: 0 0 0 2px rgba(0,191,255,.25); 
    outline: none; 
}

select.form-control { 
    appearance: none; 
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10'%3E%3Cpath fill='%23b0b0b0' d='M0 2l4 4 4-4z'/%3E%3C/svg%3E"); 
    background-repeat: no-repeat; 
    background-position: right 14px center; 
}

/* === SISTEMA DE GRID RESPONSIVO === */
.row { 
    display: flex; 
    flex-wrap: wrap; 
    gap: 20px; 
    margin-bottom: 20px;
}
.row:last-child { margin-bottom: 0; }

.col-full { flex: 0 0 100%; max-width: 100%; }

/* Calculos ajustados para gap de 20px */
.col-half { flex: 1 1 calc(50% - 10px); }
.col-third { flex: 1 1 calc(33.333% - 14px); }
.col-quart { flex: 1 1 calc(25% - 15px); }

/* Colunas Especiais (Logradouro/Número) */
.col-large { flex: 1 1 auto; width: 70%; } 
.col-small { flex: 0 0 140px; }

/* === BOTÕES E ALERTAS === */
.btn-container { 
    margin-top: 30px; 
    display: flex; 
    justify-content: flex-end; 
}

.btn-primary { 
    background: var(--primary-color); 
    border: none; 
    color: #000; 
    padding: 12px 35px; 
    border-radius: 8px; 
    font-size: 1rem; 
    font-weight: 600; 
    cursor: pointer; 
    transition: .25s; 
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary:hover { 
    background: var(--primary-hover); 
    transform: translateY(-2px); 
    box-shadow: 0 4px 14px rgba(0,191,255,.35); 
}

.alert { 
    background: #262626; 
    padding: 14px 18px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    border-left: 4px solid var(--primary-color); 
    color: #fff; 
}
.alert-info { border-left-color: #17a2b8; }

/* === RESPONSIVIDADE (TABLET) === */
@media (max-width: 992px) {
    .col-third { flex: 1 1 calc(50% - 10px); } /* Vira 2 colunas */
    .col-quart { flex: 1 1 calc(50% - 10px); } /* Vira 2 colunas */
}

/* === RESPONSIVIDADE (MOBILE) === */
@media (max-width: 768px) { 
    .fiscal-container { padding: 15px; width: 100%; }
    
    .page-title { 
        font-size: 1.6rem; 
        flex-direction: column; 
        text-align: center; 
        gap: 10px; 
    }
    
    .row { gap: 15px; } /* Menor gap no mobile */
    
    .btn-container { justify-content: center; } 
    .btn-primary { width: 100%; font-size: 1.1rem; padding: 15px; } 
    
    /* Empilha tudo no mobile */
    .col-half, .col-third, .col-quart, .col-small, .col-large { 
        flex: 1 1 100%; 
        width: 100%; 
    }
    
    .card-body { padding: 20px; }
}

@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="fiscal-container">

    <div class="page-title">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span>Configurações Fiscais</span>
    </div>

    <form action="../actions/salvar_configuracao_fiscal.php" method="POST">
        
        <div style="display: flex; flex-direction: column; gap: 30px;">

            <div class="card">
                <div class="card-header"><i class="fa-regular fa-building"></i> Dados da Empresa</div>
                <div class="card-body">

                    <div class="row">
                        <div class="col-half">
                            <label>Razão Social</label>
                            <input type="text" class="form-control" name="razao_social"
                                value="<?= htmlspecialchars($dadosEmpresa['razao_social'] ?? '') ?>" required>
                        </div>
                        <div class="col-half">
                            <label>Nome Fantasia</label>
                            <input type="text" class="form-control" name="fantasia"
                                value="<?= htmlspecialchars($dadosEmpresa['fantasia'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-third">
                            <label>CNPJ</label>
                            <input type="text" class="form-control" name="cnpj"
                                value="<?= htmlspecialchars($dadosEmpresa['cnpj'] ?? '') ?>"
                                required placeholder="Apenas números">
                        </div>
                        <div class="col-third">
                            <label>Inscrição Estadual</label>
                            <input type="text" class="form-control" name="ie"
                                value="<?= htmlspecialchars($dadosEmpresa['ie'] ?? '') ?>">
                        </div>
                        <div class="col-third">
                            <label>CEP</label>
                            <input type="text" class="form-control" name="cep" id="cep"
                                value="<?= htmlspecialchars($dadosEmpresa['cep'] ?? '') ?>"
                                onblur="buscarCep(this.value)">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-large">
                            <label>Logradouro</label>
                            <input type="text" class="form-control" name="logradouro" id="logradouro"
                                value="<?= htmlspecialchars($dadosEmpresa['logradouro'] ?? '') ?>">
                        </div>
                        <div class="col-small">
                            <label>Número</label>
                            <input type="text" class="form-control" name="numero"
                                value="<?= htmlspecialchars($dadosEmpresa['numero'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-third">
                            <label>Bairro</label>
                            <input type="text" class="form-control" name="bairro" id="bairro"
                                value="<?= htmlspecialchars($dadosEmpresa['bairro'] ?? '') ?>">
                        </div>
                        <div class="col-third">
                            <label>Município</label>
                            <input type="text" class="form-control" name="municipio" id="municipio"
                                value="<?= htmlspecialchars($dadosEmpresa['municipio'] ?? '') ?>">
                        </div>
                        <div class="col-small"> <label>UF</label>
                            <input type="text" class="form-control" name="uf" id="uf"
                                value="<?= htmlspecialchars($dadosEmpresa['uf'] ?? '') ?>" maxlength="2">
                        </div>
                        <div class="col-small">
                            <label>Cód. IBGE</label>
                            <input type="text" class="form-control" name="cod_municipio" id="ibge"
                                value="<?= htmlspecialchars($dadosEmpresa['cod_municipio'] ?? '') ?>">
                        </div>
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fa-solid fa-key"></i> Parâmetros NFC-e</div>
                <div class="card-body">

                    <div class="row">
                        <div class="col-half">
                            <label>Ambiente</label>
                            <select class="form-control" name="ambiente">
                                <option value="2" <?= ($dadosEmpresa['ambiente'] ?? '') == 2 ? 'selected' : '' ?>>
                                    Homologação (Teste)
                                </option>
                                <option value="1" <?= ($dadosEmpresa['ambiente'] ?? '') == 1 ? 'selected' : '' ?>>
                                    Produção
                                </option>
                            </select>
                        </div>

                        <div class="col-half">
                            <label>Regime Tributário</label>
                            <select class="form-control" name="regime_tributario">
                                <option value="1" <?= ($dadosEmpresa['regime_tributario'] ?? '') == 1 ? 'selected' : '' ?>>
                                    Simples Nacional
                                </option>
                                <option value="3" <?= ($dadosEmpresa['regime_tributario'] ?? '') == 3 ? 'selected' : '' ?>>
                                    Regime Normal
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-half">
                            <label>ID CSC (Token)</label>
                            <input type="text" class="form-control" name="csc_id"
                                value="<?= htmlspecialchars($dadosEmpresa['csc_id'] ?? '') ?>" placeholder="Ex: 000001">
                            <small style="color: #777;">Número sequencial do token.</small>
                        </div>

                        <div class="col-half">
                            <label>CSC (Código Alpha)</label>
                            <input type="text" class="form-control" name="csc"
                                value="<?= htmlspecialchars($dadosEmpresa['csc'] ?? '') ?>" placeholder="Código alfanumérico">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-full">
                            <div class="alert alert-info" style="margin-bottom: 0; font-size: 0.9rem;">
                                <i class="fa-solid fa-circle-info"></i> O certificado A1 deve ser enviado ao suporte técnico para configuração.
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div class="btn-container">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check mr-2" style="margin-right: 8px;"></i> Salvar Configurações
            </button>
        </div>

    </form>

</div>

<script>
function buscarCep(cep) {
    cep = cep.replace(/\D/g, '');
    if (cep.length === 8) {
        const inputCep = document.getElementById('cep');
        inputCep.style.borderColor = '#00bfff';

        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(r => r.json())
            .then(data => {
                if (!data.erro) {
                    document.getElementById('logradouro').value = data.logradouro;
                    document.getElementById('bairro').value = data.bairro;
                    document.getElementById('municipio').value = data.localidade;
                    document.getElementById('uf').value = data.uf;
                    document.getElementById('ibge').value = data.ibge;

                    document.getElementsByName('numero')[0].focus();
                }
                inputCep.style.borderColor = '';
            })
            .catch(() => {
                inputCep.style.borderColor = '#dc3545'; // red
            });
    }
}
</script>

<?php include('../includes/footer.php'); ?>