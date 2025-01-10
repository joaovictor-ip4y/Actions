<?php
// Configurações de conexão com o banco de dados
$servername = "localhost";  // Nome do servidor (geralmente localhost)
$username = "root";         // Nome de usuário do MySQL
$password = "";             // Senha do MySQL (deixe vazio se for localhost sem senha)
$dbname = "helpdesk";       // Nome do banco de dados que você criou

// Criar a conexão
$conexao = new mysqli($servername, $username, $password, $dbname);

// Verificar a conexão
if ($conexao->connect_error) {
    die("Erro de conexão: " . $conexao->connect_error);
}

// Caso a conexão seja bem-sucedida, exibe uma mensagem (opcional)
?>
