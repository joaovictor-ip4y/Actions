<?php
session_start();
include 'conexao.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Obter o ID do usuário logado
$usuario_id = $_SESSION['usuario_id'];

// Consulta para obter o setor do usuário
$query_setor = "SELECT setor FROM usuarios WHERE id = ?";
$stmt_setor = mysqli_prepare($conexao, $query_setor);
mysqli_stmt_bind_param($stmt_setor, "i", $usuario_id);
mysqli_stmt_execute($stmt_setor);
$result_setor = mysqli_stmt_get_result($stmt_setor);

// Verificar se o usuário tem um setor atribuído
$user = mysqli_fetch_assoc($result_setor);
if ($user) {
    $setor_usuario = $user['setor'];  // Armazenar o setor do usuário
} else {
    die("Erro: Setor do usuário não encontrado.");
}

// Filtro de status (padrão: todos os chamados)
$status_filtro = isset($_GET['status']) ? $_GET['status'] : 'todos';

// Consulta para buscar tickets com filtro de status
$query_tickets = "
    SELECT t.id, t.descricao, t.setor, t.setor_destinatorio, t.estado, t.data_abertura, u.nome AS usuario_nome
    FROM tickets t
    JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.setor_destinatorio = ?";

// Adicionar filtro de status se necessário
if ($status_filtro !== 'todos') {
    $query_tickets .= " AND t.estado = ?";
}

$query_tickets .= " ORDER BY t.data_abertura DESC";

$stmt_tickets = mysqli_prepare($conexao, $query_tickets);
if ($status_filtro !== 'todos') {
    mysqli_stmt_bind_param($stmt_tickets, "ss", $setor_usuario, $status_filtro);
} else {
    mysqli_stmt_bind_param($stmt_tickets, "s", $setor_usuario);
}
mysqli_stmt_execute($stmt_tickets);

// Verificar se a execução da consulta foi bem-sucedida
$result_tickets = mysqli_stmt_get_result($stmt_tickets);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chamados Atribuídos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }

        header {
            background-color: #100f0d;
            color: #ffffff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        header img {
            height: 50px;
            max-width: 100%;
            flex-shrink: 0;
        }

        header h1 {
            margin: 0;
            font-size: 24px;
            text-align: center;
            flex-grow: 1;
        }

        form {
            text-align: center;
            margin: 20px 0;
        }

        select {
            padding: 10px;
            font-size: 16px;
            width: 100%;
            max-width: 300px;
            margin-bottom: 20px;
        }

        /* Container para a tabela ser rolável */
        .table-container {
            overflow-x: auto; /* Permite rolagem horizontal */
            padding: 10px;
        }
/* Permite rolagem horizontal */
        table {
            width: 100%; /* Tabela ocupa 100% da largura do container */
            max-width: 100%; /* Garantir que a tabela não ultrapasse a tela */
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #ffffff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 12px 15px; /* Reduzir o padding */
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 14px; /* Diminuir o tamanho da fonte */
        }

        th {
            background-color: #100f0d;
            color: white;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        .status {
            font-weight: bold;
        }

        .button-container {
            text-align: center;
            margin-top: 20px;
        }

        button {
            padding: 10px 20px;
            background-color: #100f0d;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #333;
        }

        a {
            text-decoration: none;
        }

        /* Responsividade */
        @media (max-width: 768px) {
            header h1 {
                font-size: 20px;
            }

            header img {
                height: 40px;
                margin-right: 10px;
            }

            table {
                width: 100%;
                font-size: 12px;
            }

            th, td {
                padding: 8px; /* Diminuir o padding */
            }

            select {
                font-size: 14px;
            }

            button {
                padding: 8px 16px;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            header img {
                height: 40px;
                margin-right: 10px;
            }

            header h1 {
                font-size: 20px;
            }

            select {
                font-size: 12px;
                max-width: 250px;
            }

            button {
                padding: 6px 14px;
                font-size: 12px;
            }

            table {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<header>
    <img src="img/download.png" alt="Logo"> <!-- Caminho para a imagem -->
    <h1>Chamados Atribuídos</h1>
</header>

<main>
    <!-- Formulário de Filtro -->
    <form method="GET" action="">
        <label for="status">Filtrar por Status:</label>
        <select name="status" id="status" onchange="this.form.submit()">
            <option value="todos" <?php echo $status_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
            <option value="Aberto" <?php echo $status_filtro === 'Aberto' ? 'selected' : ''; ?>>Aberto</option>
            <option value="Em Andamento" <?php echo $status_filtro === 'Em Andamento' ? 'selected' : ''; ?>>Em Andamento</option>
            <option value="Concluído" <?php echo $status_filtro === 'Concluído' ? 'selected' : ''; ?>>Concluído</option>
        </select>
    </form>

    <!-- Tabela de Chamados -->
    <?php if (mysqli_num_rows($result_tickets) > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Usuário Criador</th>
                        <th>Descrição</th>
                        <th>Setor</th>
                        <th>Setor Destinatário</th>
                        <th>Status</th>
                        <th>Data de Abertura</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result_tickets)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['usuario_nome']); ?></td>
                            <td><?php echo htmlspecialchars($row['descricao']); ?></td>
                            <td><?php echo htmlspecialchars($row['setor']); ?></td>
                            <td><?php echo htmlspecialchars($row['setor_destinatorio']); ?></td>
                            <td class="status"><?php echo htmlspecialchars($row['estado']); ?></td>
                            <td><?php echo date("d/m/Y H:i:s", strtotime($row['data_abertura'])); ?></td>
                            <td>
                                <!-- Botão Visualizar -->
                                <a href="visualizar_ticket.php?ticket_id=<?php echo $row['id']; ?>">
                                    <button type="button">Visualizar</button>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>Nenhum chamado encontrado para o seu setor com o status selecionado.</p>
    <?php endif; ?>

    <div class="button-container">
        <a href="index.php">
            <button type="button">Voltar para a Página Inicial</button>
        </a>
    </div>
</main>

</body>
</html>
