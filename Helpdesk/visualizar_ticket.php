<?php
session_start();
include 'conexao.php';

// Obter ticket_id da URL ou de uma variável de sessão
$ticket_id = isset($_GET['ticket_id']) ? $_GET['ticket_id'] : $_SESSION['ticket_id'];

// Buscar informações do ticket
$ticket_query = "SELECT * FROM tickets WHERE id = $ticket_id";
$ticket_result = mysqli_query($conexao, $ticket_query);
$ticket = mysqli_fetch_assoc($ticket_result);

// Enviar uma nova mensagem
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mensagem = $_POST['mensagem'];
    $imagem = isset($_FILES['imagem']) ? $_FILES['imagem'] : null;

    // Se a mensagem não for vazia
    if (!empty($mensagem) || $imagem) {
        // Verificar se há upload de imagem
        if ($imagem && $imagem['error'] == 0) {
            $imagem_path = 'uploads/' . $imagem['name'];
            move_uploaded_file($imagem['tmp_name'], $imagem_path);
        } else {
            $imagem_path = null;
        }

        // Inserir a mensagem no banco de dados
        $insert_query = "INSERT INTO mensagens (ticket_id, usuario_id, mensagem, imagem, data_envio) 
                        VALUES ($ticket_id, {$_SESSION['usuario_id']}, '$mensagem', '$imagem_path', NOW())";
        mysqli_query($conexao, $insert_query);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Chamado</title>
    <style>
        /* CSS Original */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-bottom: 100px;
        }

        header {
            background-color: #100F0D;
            color: #FFFFFF;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }

        header img {
            position: absolute;
            top: 10px;
            left: 10px;
            height: 50px;
        }

        header h1 {
            text-align: center;
            margin: 0;
            padding-left: 60px;
        }

        .back-button {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #100F0D;
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }

        .back-button:hover {
            background-color: #333333;
        }

        .content {
            display: flex;
            margin: 20px auto;
            width: 90%;
            max-width: 900px;
        }

        .ticket-details {
            flex: 1;
            margin-right: 20px;
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 320px;
            height: 400px;
            overflow-y: auto;
        }

        .ticket-details h2 {
            font-size: 18px;
            margin-bottom: 15px;
        }

        .ticket-details table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .ticket-details th, .ticket-details td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .ticket-details th {
            background-color: #100F0D;
            color: #fff;
        }

        .chat-box {
            flex: 2;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            max-height: 500px;
            overflow: hidden;
        }

        .messages {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f1f1f1;
            border-radius: 8px;
            overflow-x: hidden;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .message {
            padding: 8px 12px;
            margin-bottom: 10px;
            background-color: #e5e5e5;
            border-radius: 4px;
            max-width: 70%;
            word-wrap: break-word;
        }

        .message.admin {
            background-color: #d1f7c4;
            align-self: flex-start;
        }

        .message.user {
            background-color: #c4d1f7;
            align-self: flex-end;
        }

        .message-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        form {
            display: flex;
            flex-direction: column;
            margin-top: 10px;
        }

        form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
            max-height: 150px;
            overflow-y: auto;
        }

        form button {
            padding: 10px 15px;
            background-color: #100F0D;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            width: 100%;
            font-size: 16px;
        }

        form button:hover {
            background-color: #333333;
        }

        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            background-color: #100F0D;
            color: #fff;
            text-align: center;
            padding: 10px;
        }

        /* Estilo para a imagem */
        .message-image {
            max-width: 100%;
            max-height: 200px;
            cursor: pointer;
            border-radius: 8px;
            display: block;
            margin-top: 10px;
        }

        /* Estilo para o Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 80%;
            max-height: 80%;
            text-align: center;
        }

        .close {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #fff;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>

<header>
    <img src="img/download.png" alt="Logo">
    <h1>Visualizar Chamado</h1>
    <a href="meus_tickets.php" class="back-button">Voltar</a>
</header>

<div class="content">
    <div class="ticket-details">
        <h2>Detalhes do Chamado</h2>
        <table>
            <tr><th>ID</th><td><?php echo htmlspecialchars($ticket['id']); ?></td></tr>
            <tr><th>Descrição</th><td><?php echo !empty($ticket['descricao']) ? htmlspecialchars($ticket['descricao']) : 'Sem Descrição'; ?></td></tr>
            <tr><th>Status</th><td><?php echo $ticket['estado']; ?></td></tr>
            <tr><th>Prioridade</th><td><?php echo $ticket['prioridade']; ?></td></tr>
            <tr><th>Data de Abertura</th><td><?php echo $ticket['data_abertura']; ?></td></tr>
        </table>
        <form method="POST">
            <select name="novo_status">
                <option value="Aberto" <?php echo $ticket['estado'] == 'Aberto' ? 'selected' : ''; ?>>Aberto</option>
                <option value="Em Andamento" <?php echo $ticket['estado'] == 'Em Andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                <option value="Concluído" <?php echo $ticket['estado'] == 'Concluído' ? 'selected' : ''; ?>>Concluído</option>
            </select>
            <button type="submit" name="alterar_status">Alterar Status</button>
        </form>
    </div>

    <div class="chat-box">
        <div class="messages" id="messages">
            <!-- As mensagens serão carregadas aqui -->
        </div>
        <form method="POST" enctype="multipart/form-data">
            <textarea name="mensagem" placeholder="Digite sua mensagem..."></textarea>
            <input type="file" name="imagem" accept="image/*">
            <button type="submit">Enviar</button>
        </form>
    </div>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> Sua Empresa. Todos os direitos reservados.
</footer>

<!-- Modal para exibir imagens -->
<div id="imageModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script>
    // Função para carregar mensagens via AJAX
    function carregarMensagens() {
        const ticketId = <?php echo $ticket_id; ?>;
        fetch(`buscar_mensagens.php?ticket_id=${ticketId}`)
            .then(response => response.json())
            .then(mensagens => {
                const mensagensDiv = document.getElementById('messages');
                mensagensDiv.innerHTML = ''; // Limpa as mensagens antigas

                mensagens.forEach(mensagem => {
                    const div = document.createElement('div');
                    div.classList.add('message', mensagem.usuario_id == <?php echo $_SESSION['usuario_id']; ?> ? 'user' : 'admin');
                    div.innerHTML = `
                        <div class="message-name">${mensagem.usuario_id == <?php echo $_SESSION['usuario_id']; ?> ? 'Você' : 'Admin'}</div>
                        <div class="message-text">${mensagem.mensagem}</div>
                    `;

                    // Se houver imagem
                    if (mensagem.imagem) {
                        const img = document.createElement('img');
                        img.src = mensagem.imagem;
                        img.classList.add('message-image');
                        img.onclick = () => openModal(mensagem.imagem);
                        div.appendChild(img);
                    }

                    mensagensDiv.appendChild(div);
                });

                // Rola para a última mensagem
                mensagensDiv.scrollTop = mensagensDiv.scrollHeight;
            });
    }

    // Atualiza as mensagens a cada 3 segundos
    setInterval(carregarMensagens, 3000);

    // Chama a função ao carregar a página
    carregarMensagens();

    // Abrir modal com a imagem
    function openModal(src) {
        document.getElementById('imageModal').style.display = 'flex';
        document.getElementById('modalImage').src = src;
    }

    // Fechar modal
    function closeModal() {
        document.getElementById('imageModal').style.display = 'none';
    }
</script>

</body>
</html>
