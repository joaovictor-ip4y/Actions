<?php
session_start();
include 'conexao.php'; // Conexão com o banco de dados

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verificar se o formulário foi submetido
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = $_POST['descricao'];
    $setor = $_POST['setor'];
    $setor_destinatorio = $_POST['setor_destinatorio'];  // Receber o setor destinatário
    $prioridade = $_POST['prioridade'];  // Receber a prioridade
    $usuario_id = $_SESSION['usuario_id'];

    if (!empty($descricao) && !empty($setor) && !empty($setor_destinatorio) && !empty($prioridade)) {
        // Inserir o novo ticket no banco de dados
        $query = "INSERT INTO tickets (descricao, setor, setor_destinatorio, prioridade, usuario_id, estado) VALUES (?, ?, ?, ?, ?, 'Aberto')";
        $stmt = mysqli_prepare($conexao, $query);

        // Vincular os parâmetros
        mysqli_stmt_bind_param($stmt, "ssssi", $descricao, $setor, $setor_destinatorio, $prioridade, $usuario_id);
        mysqli_stmt_execute($stmt);

        // Verificar se a inserção foi bem-sucedida
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            echo "<p>Chamado criado com sucesso!</p>";
        } else {
            echo "<p>Erro ao criar o chamado!</p>";
        }

        mysqli_stmt_close($stmt);
    } else {
        echo "<p>Descrição, setor, setor destinatário e prioridade são obrigatórios!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Chamado</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        /* O restante do estilo permanece o mesmo */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        header {
            background-color: #100f0d;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 10;
        }

        header img {
            position: absolute;
            top: 10px;
            left: 20px;
            height: 50px;
        }

        header h1 {
            margin: 0;
            font-size: 2em;
            color: white;
            padding-left: 80px; /* Ajusta o título para não sobrepor a imagem */
        }

        main {
            flex: 1;
            width: 90%;
            max-width: 600px;
            margin: 80px auto 20px auto; /* Adiciona espaço para o cabeçalho fixo */
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #100f0d;
        }

        textarea, select {
            width: calc(100% - 20px);
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #100f0d;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 120px;
        }

        select {
            height: 40px;
        }

        .button-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        button {
            padding: 10px 20px;
            background-color: #100f0d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            margin: 10px 0;
            width: 100%;
            max-width: 250px; /* Limita a largura do botão */
            text-align: center;
        }

        button:hover {
            background-color: #333333;
        }

        footer {
            background-color: #100f0d;
            color: white;
            text-align: center;
            padding: 10px 0;
            font-size: 14px;
        }

        footer p {
            margin: 0;
        }

        a {
            text-decoration: none;
        }

    </style>
</head>
<body>

    <header>
        <img src="img/download.png" alt="Logo iPay">
        <h1>Criar Novo Chamado</h1>
    </header>

    <main>
        <form method="POST" action="">
            <label for="descricao">Descrição do Problema:</label>
            <textarea id="descricao" name="descricao" required></textarea>

            <label for="setor">Seu Setor:</label>
            <select id="setor" name="setor" required>
                <option value="financeiro">Financeiro</option>
                <option value="tecnologia">Tecnologia</option>
                <option value="rh">RH</option>
                <option value="comercial">Comercial</option>
                <option value="sdr">SDR</option>
            </select>

            <label for="setor_destinatorio">Setor Destinatário:</label>
            <select id="setor_destinatorio" name="setor_destinatorio" required>
                <option value="financeiro">Financeiro</option>
                <option value="tecnologia">Tecnologia</option>
                <option value="rh">RH</option>
                <option value="comercial">Comercial</option>
                <option value="sdr">SDR</option>
            </select>

            <label for="prioridade">Prioridade:</label>
            <select id="prioridade" name="prioridade" required>
                <option value="baixa">Baixa</option>
                <option value="medio">Médio</option>
                <option value="alta">Alta</option>
            </select>

            <div class="button-container">
                <button type="submit">Criar Chamado</button>
            </div>
        </form>

        <div class="button-container">
            <a href="index.php">
                <button type="button">Voltar para a Página Inicial</button>
            </a>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 - Sistema de Chamados</p>
    </footer>

</body>
</html>
