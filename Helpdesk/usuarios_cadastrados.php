<?php
// Iniciar a sessão
session_start();

// Verificar se o usuário está logado e é um administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] != 'admin') {
    // Se não for admin, redireciona para o login
    header("Location: login.php");
    exit();
}

// Conectar ao banco de dados
include 'conexao.php';

// Consultar todos os usuários
$query = "SELECT id, nome, email, tipo, setor FROM usuarios";
$result = mysqli_query($conexao, $query);

// Verificar se há usuários
if (mysqli_num_rows($result) > 0) {
    $usuarios = mysqli_fetch_all($result, MYSQLI_ASSOC);
} else {
    $usuarios = [];
}

// Alterar senha para o valor padrão "teste123"
if (isset($_POST['alterar_senha'])) {
    $usuario_id = $_POST['usuario_id'];

    // Senha padrão
    $senha_padrao = password_hash('teste123', PASSWORD_DEFAULT);

    // Atualizar a senha no banco de dados
    $update_query = "UPDATE usuarios SET senha = '$senha_padrao' WHERE id = '$usuario_id'";
    if (mysqli_query($conexao, $update_query)) {
        echo "<script>alert('Senha alterada para o valor padrão com sucesso!'); window.location.href = 'admin_usuarios.php';</script>";
    } else {
        echo "<script>alert('Erro ao alterar a senha!');</script>";
    }
}

// Login como outro usuário
if (isset($_POST['login_como_usuario'])) {
    $usuario_id = $_POST['usuario_id'];

    // Consultar os dados do usuário
    $query_usuario = "SELECT * FROM usuarios WHERE id = '$usuario_id'";
    $result_usuario = mysqli_query($conexao, $query_usuario);
    $usuario = mysqli_fetch_assoc($result_usuario);

    if ($usuario) {
        // Atualizar a sessão com os dados do usuário
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['tipo'] = $usuario['tipo'];
        $_SESSION['setor'] = $usuario['setor'];
        
        // Redirecionar para a página inicial após login
        header("Location: index.php");
        exit();
    }
}

// Adicionar um novo usuário (simples exemplo de formulário)
if (isset($_POST['criar_usuario'])) {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $tipo = $_POST['tipo'];
    $setor = $_POST['setor'];

    // Inserir o novo usuário no banco de dados
    $insert_query = "INSERT INTO usuarios (nome, email, senha, tipo, setor) VALUES ('$nome', '$email', '$senha', '$tipo', '$setor')";
    if (mysqli_query($conexao, $insert_query)) {
        echo "<script>alert('Usuário criado com sucesso!'); window.location.href = 'admin_usuarios.php';</script>";
    } else {
        echo "<script>alert('Erro ao criar o usuário!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Administração</title>
    <style>
        /* Estilos para a página */
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
            color: #fff;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        header img {
            height: 40px;
            width: auto;
            margin-right: 15px;
        }

        header h1 {
            font-size: 20px;
            margin: 0;
            flex-grow: 1;
            text-align: center;
        }

        .btn-container {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn-container a {
            padding: 10px 15px;
            background-color: #100f0d;
            color: white;
            text-decoration: none;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid #100f0d;
        }

        .btn-container a:hover {
            background-color: #333;
        }

        table {
            width: 80%; /* Ajusta a largura da tabela */
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #fff;
            overflow-x: auto;
        }

        th, td {
            padding: 8px 10px; /* Diminui o espaçamento nas células */
            border: 1px solid #ddd;
            text-align: left;
            font-size: 12px; /* Diminui o tamanho da fonte */
        }

        th {
            background-color: #333;
            color: white;
        }

        td {
            background-color: #f9f9f9;
        }

        a {
            color: #100f0d;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }

        .btn-alterar, .btn-login {
            padding: 8px 16px;
            background-color: #100f0d;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
        }

        .btn-alterar:hover, .btn-login:hover {
            background-color: #333;
        }

        footer {
            background-color: #100f0d;
            color: white;
            text-align: center;
            padding: 10px;
            margin-top: auto;
        }

        /* Media Query para telas pequenas */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                text-align: center;
            }

            header img {
                height: 30px;
            }

            header h1 {
                font-size: 18px;
            }

            .btn-container {
                justify-content: center;
            }

            table {
                font-size: 10px; /* Reduz a fonte em telas pequenas */
            }

            th, td {
                padding: 6px 8px; /* Ajusta o padding nas células para telas pequenas */
            }

            th {
                background-color: #444;
            }

            td {
                border-bottom: 1px solid #ddd;
            }

            .btn-alterar, .btn-login {
                font-size: 12px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>

<header>
    <!-- Logo à esquerda -->
    <img src="img/download.png" alt="Logo iPay">
    <h1>Usuários Cadastrados</h1>
    <div class="btn-container">
        <a href="index.php">Voltar para Início</a>
        <a href="criar_usuario.php">Criar Usuário</a>
    </div>
</header>

<main>
    <?php if (!empty($usuarios)): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Tipo</th>
                    <th>Setor</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usuario['id']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['tipo']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['setor']); ?></td>
                        <td>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                <button type="submit" name="alterar_senha" class="btn-alterar">Alterar Senha</button>
                            </form>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="usuario_id" value="<?php echo $usuario['id']; ?>">
                                <button type="submit" name="login_como_usuario" class="btn-login">Login como Usuário</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center;">Nenhum usuário encontrado.</p>
    <?php endif; ?>
</main>

<footer>
    <p>&copy; <?php echo date('Y'); ?> - Sistema de Chamados</p>
</footer>

</body>
</html>
