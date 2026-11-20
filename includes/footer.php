    </main>

<?php
// ===============================
// COMPONENTES OCULTOS
// ===============================
if (file_exists(__DIR__ . '/../pages/calculadora.php')) {
    include __DIR__ . '/../pages/calculadora.php';
}

if (file_exists(__DIR__ . '/../pages/calendario.php')) {
    include __DIR__ . '/../pages/calendario.php';
}
?>

<!-- ===============================
     BOTÕES FLUTUANTES
================================ -->
<div class="action-buttons">
    <a href="https://wa.me/5527999642716" target="_blank" title="Suporte via WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <a href="#" id="abrir-calculadora" title="Abrir Calculadora">
        <i class="fas fa-calculator"></i>
    </a>

    <a href="#" id="abrir-calendario" title="Abrir Calendário">
        <i class="fas fa-calendar-alt"></i>
    </a>
</div>

<!-- ===============================
     FOOTER
================================ -->
<footer class="footer-app">
    <p>
        &copy; <?= date('Y') ?> App Controle de Contas. Desenvolvido por Felippe Fardin.
        <a href="../pages/tutorial.php">Tutorial</a>
        <a href="../pages/suporte.php">Suporte</a>
        <a href="../pages/protecao_de_dados.php">Proteção de Dados</a>
    </p>
</footer>

<!-- ===============================
     ESTILOS DO FOOTER E BOTÕES
================================ -->
<style>
/* ===============================
   FOOTER FIXO
================================ */
.footer-app {
    background-color: var(--bg-card);
    color: var(--text-secondary);
    text-align: center;
    padding: 15px 20px;
    box-shadow: 0 -2px 5px rgba(0,0,0,0.3);
    font-size: 0.9em;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
}

.footer-app p {
    margin: 0;
}

.footer-app a {
    color: var(--highlight-color);
    text-decoration: none;
    margin: 0 10px;
}

.footer-app a:hover {
    text-decoration: underline;
}

/* ===============================
   BOTÕES FLUTUANTES
================================ */
.action-buttons {
    position: fixed;
    bottom: 70px;
    left: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    z-index: 1002;
}

.action-buttons a {
    background-color: transparent;
    color: rgba(255, 255, 255, 0.35);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 22px;
    text-decoration: none;
    border: 1px solid rgba(255,255,255,0.15);
    cursor: pointer;
    transition:
        background-color 0.3s ease,
        color 0.3s ease,
        transform 0.2s ease,
        box-shadow 0.3s ease;
}

/* ===============================
   HOVER AZUL (CALC / CALENDÁRIO)
================================ */
/* Mantém os atalhos visíveis no tema claro. */
html[data-app-theme="light"] .action-buttons a,
body.light-mode .action-buttons a {
    background-color: rgba(255, 255, 255, 0.92);
    color: #344054;
    border-color: #cbd5e1;
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.14);
}

#abrir-calculadora:hover,
#abrir-calendario:hover {
    background-color: #007bff;
    color: #fff;
    box-shadow: 0 0 12px rgba(0,123,255,0.6);
    transform: scale(1.1);
}

/* ===============================
   HOVER VERDE (WHATSAPP)
================================ */
.action-buttons a[href*="wa.me"]:hover {
    background-color: #25D366;
    color: #fff;
    box-shadow: 0 0 12px rgba(37,211,102,0.6);
    transform: scale(1.1);
}
</style>

<!-- ===============================
     SCRIPTS
================================ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const botaoCalc = document.getElementById('abrir-calculadora');
    const botaoCal = document.getElementById('abrir-calendario');
    const calcContainer = document.getElementById('calculadora-container');
    const calContainer = document.getElementById('calendario-container');

    if (!botaoCalc || !botaoCal || !calcContainer || !calContainer) return;

    const fecharAmbos = () => {
        calcContainer.style.display = 'none';
        calContainer.style.display = 'none';
    };

    botaoCalc.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        const aberto = calcContainer.style.display === 'block';
        fecharAmbos();
        if (!aberto) calcContainer.style.display = 'block';
    });

    botaoCal.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        const aberto = calContainer.style.display === 'block';
        fecharAmbos();
        if (!aberto) calContainer.style.display = 'block';
    });
});
</script>

</body>
</html>
