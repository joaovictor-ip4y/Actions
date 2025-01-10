<?php
// Processa o formulário após envio
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $mensagem = $_POST['mensagem'];
    $erro = '';

    // Validação simples
    if (empty($nome) || empty($email) || empty($mensagem)) {
        $erro = 'Todods os campos devem ser preenchidos!';
    } else {
        // Simulando envio de email ou outro processamento
        $sucesso = 'fsMensagem enviada com sucesso!';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário de Contato</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .form-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin: 10px 0 5px;
        }

        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 10px;
            margin: 5px 0 20px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .error {
            color: red;
            font-size: 0.9em;
        }

        .success {
            color: green;
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

    <div class="form-container">
        <h2>Formulário de Contato</h2>

        <?php if (isset($erro) && $erro != ''): ?>
            <div class="error"><?= $erro ?></div>
        <?php elseif (isset($sucesso)): ?>
            <div class="success"><?= $sucesso ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="nome">Nome:</label>
            <input type="text" id="nome" name="nome" value="<?= isset($nome) ? htmlspecialchars($nome) : '' ?>" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>

            <label for="mensagem">Mensagem:</label>
            <textarea id="mensagem" name="mensagem" rows="4" required><?= isset($mensagem) ? htmlspecialchars($mensagem) : '' ?></textarea>

            <button type="submit">Enviar</button>
        </form>
    </div>

    <script>
        // Validação simples via JavaScript
        document.querySelector('form').addEventListener('submit', function(event) {
            const nome = document.getElementById('nome').value;
            const email = document.getElementById('email').value;
            const mensagem = document.getElementById('mensagem').value;

            if (!nome || !email || !mensagem) {
                alert('Todos os campos devem ser preenchidos!');
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
