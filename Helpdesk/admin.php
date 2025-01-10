<?php
session_start();
include 'conexao.php';
// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: login.php");
    exit();
}
// Capturar os filtros de estado e setor
$estado_filtro = isset($_POST['estado']) ? $_POST['estado'] : '';
$setor_filtro = isset($_POST['setor']) ? $_POST['setor'] : '';
// Consultar todos os tickets com filtros aplicados
$query = "SELECT * FROM tickets WHERE 1";
// Adicionar filtro de estado, se presente
if ($estado_filtro) {
    $query .= " AND estado = '$estado_filtro'";
}
// Adicionar filtro de setor, se presente
if ($setor_filtro) {
    $query .= " AND setor = '$setor_filtro'";
}
// Executar a consulta
$result = mysqli_query($conexao, $query);
// Verificar se foi solicitado excluir um ticket
if (isset($_GET['excluir_id'])) {
    $ticket_id = $_GET['excluir_id'];
    // Excluir o ticket
    $delete_query = "DELETE FROM tickets WHERE id = $ticket_id";
    if (mysqli_query($conexao, $delete_query)) {
        echo "<script>alert('Chamado excluído com sucesso!'); window.location.href = 'admin.php';</script>";
    } else {
        echo "<script>alert('Erro ao excluir o chamado: " . mysqli_error($conexao) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #FFFFFF;
            color: #FFFFFF;
            text-align: center;
            padding-bottom: 50px;
        }
        header {
            background-color: #100F0D;
            color: #FFFFFF;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header img {
            position: absolute;
            top: 10px;
            left: 10px;
            height: 50px;
        }
        header h1 {
            flex-grow: 1;
            text-align: center;
            margin: 0;
            padding-left: 200px;
            color: #FFFFFF;
        }
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        .header-buttons button {
            padding: 10px 20px;
            font-size: 16px;
            border: 1px solid #100F0D;
            border-radius: 5px;
            background-color: #100F0D;
            color: #FFFFFF;
            cursor: pointer;
            transition: 0.3s;
        }
        .header-buttons button:hover {
            background-color: #333333;
            color: #FFFFFF;
        }
        table {
            margin: 20px auto;
            width: 90%;
            max-width: 1000px;
            border-collapse: collapse;
            background: #333333;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            color: #FFFFFF;
        }
        table, th, td {
            border: 1px solid #100F0D;
        }
        th {
            background-color: #100F0D;
            color: #FFFFFF;
            padding: 15px;
        }
        td {
            padding: 12px;
            text-align: center;
        }
        tr:nth-child(even) {
            background-color: #444444;
        }
        tr:hover {
            background-color: #555555;
        }
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background: #100F0D;
            color: #FFFFFF;
            text-align: center;
            padding: 10px;
            margin-top: 20px;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
        }
        a {
            text-decoration: none;
        }
        a button:focus {
            outline: none;
            border: none;
        }
        .filter-form {
            margin: 20px 0;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .filter-form select, .filter-form button {
            padding: 8px 12px;
            font-size: 14px;
            background-color: #100F0D;
            color: white;
            border: none;
            border-radius: 5px;
        }
        .filter-form button:hover {
            background-color: #333333;
        }
        .filter-form select {
            width: 150px;
        }
        .filter-form button {
            width: 120px;
        }
        button {
            background-color: #000000; /* Garantir que todos os botões sejam pretos */
            color: #FFFFFF;
            border-radius: 5px;
            padding: 10px 20px;
            cursor: pointer;
            border: none;
        }
        button:hover {
            background-color: #333333;
        }
    </style>
</head>
<body>
    <header>
        <img src="img/download.png" alt="Logo iPay">
        <h1>Painel de Chamados</h1>
        <div class="header-buttons">
            <a href="criar_usuario.php"><button>Criar Novo Usuário</button></a>
            <a href="index.php"><button>Voltar</button></a>
        </div>
    </header>
    <form method="POST" class="filter-form">
        <select name="estado">
            <option value="">Selecione o Estado</option>
            <option value="Aberto" <?php if($estado_filtro == 'Aberto') echo 'selected'; ?>>Aberto</option>
            <option value="Em andamento" <?php if($estado_filtro == 'Em andamento') echo 'selected'; ?>>Em andamento</option>
            <option value="Concluído" <?php if($estado_filtro == 'Concluído') echo 'selected'; ?>>Concluído</option>
        </select>
        <select name="setor">
            <option value="">Selecione o Setor</option>
            <option value="Financeiro" <?php if($setor_filtro == 'Financeiro') echo 'selected'; ?>>Financeiro</option>
            <option value="Tecnologia" <?php if($setor_filtro == 'Tecnologia') echo 'selected'; ?>>Tecnologia</option>
            <option value="Comercial" <?php if($setor_filtro == 'Comercial') echo 'selected'; ?>>Comercial</option>
            <option value="SDR" <?php if($setor_filtro == 'SDR') echo 'selected'; ?>>SDR</option>
            <option value="RH" <?php if($setor_filtro == 'RH') echo 'selected'; ?>>RH</option>
        </select>
        <button type="submit">Filtrar</button>
    </form>
    <h2>Chamados</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Descrição</th>
            <th>Usuário</th>
            <th>Setor</th>
            <th>Data de Abertura</th>
            <th>Estado</th>
            <th>Alterar Estado</th>
            <th>Visualizar</th>
            <th>Excluir</th>
        </tr>
        <?php while ($ticket = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo $ticket['id']; ?></td>
                <td><?php echo $ticket['descricao']; ?></td>
                <td><?php
                    $usuario_query = "SELECT nome FROM usuarios WHERE id = " . $ticket['usuario_id'];
                    $usuario_result = mysqli_query($conexao, $usuario_query);
                    $usuario = mysqli_fetch_assoc($usuario_result);
                    echo $usuario['nome'] ?? "Usuário desconhecido";
                ?></td>
                <td><?php echo $ticket['setor']; ?></td>
                <td><?php echo date('d/m/Y H:i:s', strtotime($ticket['data_abertura'])); ?></td>
                <td><?php echo $ticket['estado']; ?></td>
                <td>
                    <form method="POST" action="alterar_estado.php">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                        <select name="estado">
                            <option value="Aberto" <?php if($ticket['estado'] == 'Aberto') echo 'selected'; ?>>Aberto</option>
                            <option value="Em andamento" <?php if($ticket['estado'] == 'Em andamento') echo 'selected'; ?>>Em andamento</option>
                            <option value="Concluído" <?php if($ticket['estado'] == 'Concluído') echo 'selected'; ?>>Concluído</option>
                        </select>
                        <button type="submit">Alterar</button>
                    </form>
                </td>
                <td>
                    <!-- Botão Visualizar -->
                    <a href="visualizar_ticket.php?ticket_id=<?php echo $ticket['id']; ?>">
                        <button>Visualizar</button>
                    </a>
                </td>
                <td>
                    <a href="?excluir_id=<?php echo $ticket['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir?');">
                        <button>Excluir</button>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
    <footer>
        <p>&copy; 2024 iPay. Todos os direitos reservados.</p>
    </footer>
</body>
</html>

<?php
mysqli_stmt_close($stmt);
?>
