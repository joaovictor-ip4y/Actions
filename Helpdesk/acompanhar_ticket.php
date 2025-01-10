<?php
session_start();
include 'conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Obter todos os tickets do usuário logado
$usuario_id = $_SESSION['usuario_id'];
$query = "SELECT * FROM tickets WHERE usuario_id = ?";
$stmt = mysqli_prepare($conexao, $query);
mysqli_stmt_bind_param($stmt, 'i', $usuario_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Tickets</title>
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
            justify-content: space-between;
        }

        header {
            background-color: #100f0d;
            color: #ffffff;
            padding: 20px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
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
            padding-left: 70px; 
            text-align: center;
        }

        main {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        table {
            border-collapse: collapse;
            width: 90%;
            max-width: 800px;
            margin: 20px auto;
        }

        table th, table td {
            border: 1px solid #100f0d;
            padding: 10px;
            text-align: left;
        }

        table th {
            background-color: #100f0d;
            color: #ffffff;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:hover {
            background-color: #ddd;
        }

        p {
            margin-top: 20px;
        }

        button {
            padding: 10px 20px;
            margin-top: 20px;
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

        /* Responsividade */
        @media (max-width: 768px) {
            header h1 {
                font-size: 1.5em;
            }

            table th, table td {
                font-size: 14px;
                padding: 8px;
            }

            footer {
                font-size: 0.9em;
            }
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 1.2em;
            }

            table th, table td {
                font-size: 12px;
                padding: 6px;
            }

            button {
                font-size: 14px;
                padding: 8px;
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
        <h1>Acompanhar Meus Tickets</h1>
    </header>
    <main>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Descrição</th>
                    <th>Data de Abertura</th>
                    <th>Estado</th>
                    <th>Ação</th>
                </tr>
                <?php while ($ticket = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $ticket['id']; ?></td>
                        <td><?php echo $ticket['descricao']; ?></td>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($ticket['data_abertura'])); ?></td>
                        <td><?php echo $ticket['estado']; ?></td>
                        <td>
                            <a href="visualizar_ticket.php?ticket_id=<?php echo $ticket['id']; ?>">
                                <button>Visualizar</button>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>Você ainda não tem tickets abertos.</p>
        <?php endif; ?>
        <a href="index.php">
            <button>Voltar para a Página Inicial</button>
        </a>
    </main>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> - Sistema de Chamados</p>
    </footer>
</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
