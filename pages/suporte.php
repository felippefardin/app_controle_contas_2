<!DOCTYPE html>
<html lang="pt-BR">
<head>
<link rel="stylesheet" href="../assets/css/universal-theme.css">
<script src="../assets/js/global-theme.js"></script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- 👈 Essencial para o modo responsivo -->
  <title>Suporte - App Controle de Contas</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  
  <style>
    /* ===== Estilos gerais ===== */
    * {
      box-sizing: border-box;
    }

    body {
      background-color: #121212;
      color: #eee;
      font-family: Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    form {
      background: #1f1f1f;
      padding: 25px 30px;
      border-radius: 10px;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 0 15px rgba(0, 123, 255, 0.7);
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
    }

    form h2 {
      margin-bottom: 20px;
      text-align: center;
      color: #00bfff;
      font-size: 1.6rem;
    }

    label {
      margin-top: 10px;
      font-weight: 600;
      font-size: 0.95rem;
      color: #ccc;
    }

    input,
    textarea {
      
      padding: 10px;
      margin-top: 6px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      background-color: #2a2a2a;
      color: #fff;
      transition: all 0.2s ease;
    }

    input:focus,
    textarea:focus {
      outline: 2px solid #00bfff;
      background-color: #333;
    }

    button {
      margin-top: 20px;
      padding: 12px;
      background-color: #007bff;
      border: none;
      border-radius: 6px;
      color: white;
      font-weight: bold;
      font-size: 1.1rem;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }

    button:hover,
    button:focus {
      background-color: #0056b3;
      outline: none;
      transform: scale(1.02);
    }

    .success-message {
      background-color: #4BB543;
      color: white;
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 5px;
      font-weight: 600;
      text-align: center;
      display: none;
    }

    .anonimo-container {
      margin-top: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.9rem;
    }

    /* ===== Responsividade ===== */

    /* Tablets e telas médias */
    @media (max-width: 768px) {
      form {
        max-width: 90%;
        padding: 20px;
      }
      form h2 {
        font-size: 1.4rem;
      }
      button {
        font-size: 1rem;
        padding: 10px;
      }
    }

    /* Smartphones pequenos */
    @media (max-width: 480px) {
      body {
        padding: 10px;
      }

      form {
        padding: 15px;
        border-radius: 8px;
      }

      form h2 {
        font-size: 1.3rem;
      }

      label {
        font-size: 0.85rem;
      }

      input,
      textarea {
        font-size: 0.9rem;
        padding: 8px;
      }

      button {
        font-size: 0.95rem;
        padding: 9px;
      }

      .anonimo-container {
        flex-direction: row;
        align-items: center;
        font-size: 0.85rem;
      }
    }
  </style>
</head>

<body>
  <form id="suporteForm" action="../actions/enviar_suporte.php" method="POST">
    <h2>Suporte</h2>

    <div id="successMessage" class="success-message">
      Recebemos a sua mensagem com sucesso, em breve retornaremos.
    </div>

    <label for="nome">Nome (Opcional)</label>
    <input type="text" id="nome" name="nome" />

    <label for="whatsapp">WhatsApp (Opcional)</label>
    <input type="text" id="whatsapp" name="whatsapp" />

    <label for="email">E-mail</label>
    <input type="email" id="email" name="email" required />

    <label for="mensagem">Mensagem</label>
    <textarea id="mensagem" name="mensagem" required rows="4"></textarea>

    <div class="anonimo-container">
      <input type="checkbox" id="anonimo" name="anonimo">
      <label for="anonimo">Enviar anonimamente</label>
    </div>

    <button type="submit">Enviar</button>
  </form>

  <script>
    document.getElementById('suporteForm').addEventListener('submit', function(event) {
      event.preventDefault();
      const form = event.target;
      const formData = new FormData(form);

      fetch(form.action, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        const successMessage = document.getElementById('successMessage');
        successMessage.style.display = 'block';
        setTimeout(() => {
          successMessage.style.display = 'none';
        }, 5000);
        form.reset();
      })
      .catch(error => console.error('Error:', error));
    });
  </script>
</body>
</html>
