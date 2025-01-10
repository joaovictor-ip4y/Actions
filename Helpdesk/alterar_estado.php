<?php
session_start();
include 'conexao.php';

// Verificar se o usuário está logado e é administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['tipo'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Alterar o estado do ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $ticket_id = $_POST['ticket_id'];
    $novo_estado = $_POST['estado'];

    // Atualizar o estado do ticket no banco de dados
    $query = "UPDATE tickets SET estado = '$novo_estado' WHERE id = '$ticket_id'";
    if (mysqli_query($conexao, $query)) {
        header("Location: admin.php");  // Redirecionar de volta para a página de administração
        exit();
    } else {
        echo "Erro ao atualizar o estado do ticket.";
    }
}
?>
