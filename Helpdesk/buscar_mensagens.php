<?php
session_start();
include 'conexao.php';

// Verifique se o ticket_id foi passado corretamente
if (isset($_GET['ticket_id'])) {
    $ticket_id = $_GET['ticket_id'];

    // Consulta para buscar mensagens do ticket
    $query = "SELECT m.*, u.nome FROM mensagens m 
              LEFT JOIN usuarios u ON m.usuario_id = u.id 
              WHERE m.ticket_id = $ticket_id ORDER BY m.data_envio ASC";
    
    $result = mysqli_query($conexao, $query);

    $mensagens = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $mensagens[] = $row;
    }

    // Retorna as mensagens no formato JSON
    echo json_encode($mensagens);
} else {
    echo json_encode([]);
}
?>
