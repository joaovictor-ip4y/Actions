<?php
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Verifgfvvfadscgsdvjasvgicar o tipo de usuário
$tipo_usuario = $_SESSION['tipo'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Inicial</title>
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
            display: flex;
            align-items: center; /* Alinha verticalmente */
            justify-content: center; /* Centraliza os itens horizontalmente */
            position: relative;
        }

        header img {
            height: 50px;
            margin-right: 15px; /* Espaço entre a imagem e o título */
        }

        header h1 {
            font-size: 2.2em;
            display: inline-block; /* Para garantir que o título ocupe só o necessário */
        }

        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        a {
            text-decoration: none;
            width: 100%;
            max-width: 300px;
            margin-bottom: 10px;
        }

        button {
            width: 100%;
            padding: 15px;
            background-color: rgba(16, 15, 13, 1); 
            color: #ffffff; 
            border: none;
            border-radius: 5px;
            font-size: 1em;
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

        /* Responsividade */
        @media (max-width: 768px) {
            header h1 {
                font-size: 1.8em;
            }

            main {
                padding: 15px;
            }

            button {
                font-size: 1.1em;
                padding: 12px;
            }

            footer {
                font-size: 0.9em;
            }
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 1.5em;
            }

            header img {
                height: 40px;
            }

            button {
                font-size: 1em;
                padding: 10px;
            }

            a {
                max-width: 100%;
            }

            footer {
                font-size: 0.8em;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="img/download.png" alt="Logo iPay">
        <h1>Bem-vindo ao Sistema de Helpdesk</h1>
    </header>
    <main>
        <a href="novo_ticket.php">
            <button>Criar Novo Chamado</button>
        </a>

        <a href="acompanhar_ticket.php">
            <button>Acompanhar Chamados</button>
        </a>

        <a href="chamados_atribuidos.php">
            <button>Chamados Atribuídos</button>
        </a>

        <?php if ($tipo_usuario == 'admin'): ?>
            <a href="admin.php">
                <button>Acessar Administração</button>
            </a>

            <a href="usuarios_cadastrados.php">
                <button>Usuários Cadastrados</button>
            </a>
        <?php endif; ?>

        <a href="alterar_senha.php">
            <button>Alterar Senha</button>
        </a>

        <a href="logout.php">
            <button>Sair</button>
        </a>
    </main>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> - Sistema de Chamados</p>
    </footer>
</body>
</html>
