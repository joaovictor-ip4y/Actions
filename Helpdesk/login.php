<?php
session_start();
include 'conexao.php';  // Incluindo a conexão com o banco de dados

// Vswsdserificar se o formulário foi submetido
$erro = null;  // Inicializar variável de erro
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);  // Remover espaços em branco
    $senha = trim($_POST['senha']);  // Remover espaços em branco

    // Verificar se os campos estão preenchidos
    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        // Consulta para verificar o usuário no banco de dados
        $query = "SELECT * FROM usuarios WHERE email = '$email'";
        $result = mysqli_query($conexao, $query);
        $usuario = mysqli_fetch_assoc($result);

        // Verificar se o usuário existe e a senha está correta
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['tipo'] = $usuario['tipo'];  // Guardar o tipo de usuário (admin ou comum)

            // Recuperar o setor do usuário e armazená-lo na sessão
            $_SESSION['setor'] = $usuario['setor'];  // Armazenando o setor na sessão

            // Redirecionar para a página index.php
            header("Location: index.php");
            exit();
        } else {
            $erro = "Usuário ou Senha incorreta.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        /* Seu CSS original */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            color: #100f0d;
            text-align: center;
            min-height: 100vh; 
            display: flex;
            flex-direction: column; 
            justify-content: space-between; 
        }

        header {
            background-color: #100f0d;
            color: #ffffff;
            padding: 20px;
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
            font-size: 2em;
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
            background-color: #f9f9f9;
            border: 1px solid #100f0d;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: left;
        }

        input[type="email"], input[type="password"] {
            width: calc(100% - 20px); 
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #100f0d;
            border-radius: 4px;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #100f0d;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #100f0d;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #333333;
        }

        footer {
            background: #100f0d;
            color: #ffffff;
            text-align: center;
            padding: 10px 0;
        }

        footer p {
            margin: 0;
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 1.5em;
            }

            form {
                width: 95%;
                padding: 15px;
            }

            footer {
                font-size: 0.9em;
            }
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 1.2em;
            }

            form {
                width: 100%;
                margin: 10px;
            }

            button {
                font-size: 14px;
                padding: 8px;
            }

            label {
                font-size: 14px;
            }

            input[type="email"], input[type="password"] {
                font-size: 16px;
                padding: 12px;
            }
        }

        /* Estilo da mensagem de erro */
        .error-message {
            color: #ff0000;
            background-color: #ffe6e6;
            padding: 10px;
            margin-top: 15px;
            border: 1px solid #ff0000;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header>
        <img src="img/download.png" alt="Logo iPay">
        <h1>Login</h1>
    </header>
    <main>
        <form method="POST" action="">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required>
            <button type="submit">Entrar</button>
        </form>
        <?php if (!empty($erro)): ?>
            <div class="error-message">
                <?php echo $erro; ?>
            </div>
        <?php endif; ?>
    </main>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> - Sistema de Chamados</p>
    </footer>
</body>
</html>
