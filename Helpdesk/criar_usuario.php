<?php
session_start();
include 'conexao.php';

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $tipo = $_POST['tipo'];
    $setor = $_POST['setor']; // Receber o setor do usuário

    // Criptografar a senha
    $senha_criptografada = password_hash($senha, PASSWORD_DEFAULT);

    // Inserir o novo usuário no banco de dados, incluindo o setor
    $query = "INSERT INTO usuarios (nome, email, senha, tipo, setor) VALUES ('$nome', '$email', '$senha_criptografada', '$tipo', '$setor')";
    if (mysqli_query($conexao, $query)) {
        echo "<script>alert('Usuário criado com sucesso!'); window.location.href = 'admin.php';</script>";
    } else {
        echo "<script>alert('Erro ao criar o usuário.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Usuário</title>
    <style>
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
            justify-content: flex-start; 
            overflow-x: hidden; 
        }

        header {
            background-color: #100f0d;
            color: #ffffff;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between; 
            position: relative; 
        }

        header img {
            position: absolute; 
            top: 10px; 
            left: 10px; 
            height: 50px;
        }

        header h1 {
            margin: 0 auto; 
            text-align: center;
            font-size: 1.8em; 
        }

        form {
            background-color: #f9f9f9;
            border: 1px solid #100f0d;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            text-align: left;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"], input[type="email"], input[type="password"], select {
            width: calc(100% - 20px); 
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #100f0d;
            border-radius: 4px;
            font-size: 14px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #100f0d;
        }

        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #100f0d;
            border-radius: 4px;
            font-size: 14px;
        }

        button {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
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

        a button {
            display: block;
            margin: 0 auto;
            width: auto;
        }

        
        a {
            text-decoration: none;
        }

        
        a button:focus {
            outline: none;
            border: none;
        }

        footer {
            background: #100f0d; 
            color: #ffffff; 
            padding: 10px;
            text-align: center;
            width: 100%;
            margin-top: auto; 
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

            button {
                font-size: 14px; 
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
                font-size: 12px; 
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="img/download.png" alt="Logo iPay">
        <h1>Criar Novo Usuário</h1>
    </header>
    <form method="POST" action="">
        <label for="nome">Nome:</label>
        <input type="text" id="nome" name="nome" required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="senha">Senha:</label>
        <input type="password" id="senha" name="senha" required>

        <label for="tipo">Tipo de Usuário:</label>
        <select name="tipo" id="tipo" required>
            <option value="comum">Comum</option>
            <option value="admin">Administrador</option>
        </select>

        <!-- Campo de Setor -->
        <label for="setor">Setor:</label>
        <select name="setor" id="setor" required>
            <option value="financeiro">Financeiro</option>
            <option value="tecnologia">Tecnologia</option>
            <option value="rh">RH</option>
            <option value="comercial">Comercial</option>
            <option value="sdr">SDR</option>
        </select>

        <button type="submit">Criar Usuário</button>
    </form>
    <a href="admin.php">
        <button>Voltar ao Painel</button>
    </a>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> - Sistema de Chamados</p>
    </footer>
</body>
</html>
