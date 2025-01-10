<?php
session_start();
include 'conexao.php';

if (!isset($_GET['ticket_id']) || !is_numeric($_GET['ticket_id'])) {
    echo json_encode([]);
    exit();
}

$ticket_id = $_GET['ticket_id'];
$last_message_time = isset($_GET['last_message_time']) ? $_GET['last_message_time'] : '1970-01-01 00:00:00';

// Consultar as novas mensagens
$mensagens_query = "SELECT * FROM mensagens WHERE ticket_id = $ticket_id AND data_envio > '$last_message_time' ORDER BY data_envio ASC";
$mensagens_result = mysqli_query($conexao, $mensagens_query);

$mensagens = [];
while ($mensagem = mysqli_fetch_assoc($mensagens_result)) {
    $mensagens[] = [
        'usuario_id' => $mensagem['usuario_id'],
        'mensagem' => $mensagem['mensagem'],
        'imagem' => $mensagem['imagem'],
        'data_envio' => $mensagem['data_envio']
    ];
}

echo json_encode($mensagens);
?>
