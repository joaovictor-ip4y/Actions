<?php
$servername = "localhost";
$username = "root"; // Alterar conforme necessário
$password = ""; // Alterar conforme necessário
$dbname = "helpdesk";

// Conectar ao banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Atualizar o tipo do usuário para 'admin'
$email = 'joao@ip4y.com.br'; // O email do usuário que você deseja atualizar

// Usando prepared statements para evitar SQL Injection
$stmt = $conn->prepare("UPDATE usuarios SET tipo = ? WHERE email = ?");
$stmt->bind_param("ss", $tipo, $email); // 'ss' indica que são dois parâmetros do tipo string

$tipo = 'admin'; // Tipo de usuário que você deseja definir

// Executa a query e verifica se houve erro
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo "Usuário atualizado para administrador com sucesso!";
    } else {
        echo "Nenhum usuário foi atualizado. Verifique se o email existe.";
    }
} else {
    echo "Erro ao atualizar o usuário: " . $stmt->error;
}

// Fechar a conexão
$stmt->close();
$conn->close();
?>
