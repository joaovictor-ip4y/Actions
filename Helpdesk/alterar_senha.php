<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
// Lógica para processar a mudança de senha...

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar Senha</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4; 
            color: #333333; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            justify-content: space-between; 
        }

        header {
            background-color: rgba(16, 15, 13, 1);
            color: #ffffff;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        header img {
            position: absolute;
            top: 10px;
            left: 10px;
            height: 50px; 
        }

        header h1 {
            margin: 0;
            font-size: 2.2em;
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        form {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #100f0d;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
        }

        button {
            width: 100%;
            padding: 15px;
            margin-top: 10px;
            background-color: rgba(16, 15, 13, 1); 
            color: #ffffff; 
            border: none;
            border-radius: 5px;
            font-size: 1.2em;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: rgba(16, 15, 13, 0.8); 
        }

        footer {
            background: rgba(16, 15, 13, 1); 
            color: #ffffff; 
            padding: 10px;
            text-align: center;
            width: 100%;
        }

        footer p {
            margin: 0;
        }

       
        @media (max-width: 768px) {
            footer {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <header>
        
        <img src="img/download.png" alt="Logo iPay">
        <h1>Alterar Senha</h1>
    </header>

    <main>
        <form method="POST" action="alterar_senha_processar.php">
            <label for="senha_atual">Senha Atual:</label>
            <input type="password" name="senha_atual" required><br>

            <label for="nova_senha">Nova Senha:</label>
            <input type="password" name="nova_senha" required><br>

            <label for="confirmar_senha">Confirmar Nova Senha:</label>
            <input type="password" name="confirmar_senha" required><br>

            <button type="submit">Alterar Senha</button>
        </form>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> - Sistema de Chamados</p>
    </footer>
</body>
</html>
