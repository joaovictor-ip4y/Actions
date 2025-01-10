<?php
// Incluir o arquivo de conexão
include 'conexao.php';

// Verificar se a conexão foi bem-sucedida
if (!$conexao) {
    die("Erro na conexão: " . mysqli_connect_error());
}

// Novo valor de senha
$nova_senha = 'teste';

// Criptografar a nova senha (usando bcrypt)
$senha_criptografada = password_hash($nova_senha, PASSWORD_BCRYPT);

// Atualizar a senha do usuário admin
$email_admin = 'admin@ip4y.com.br'; // Email do usuário admin
$sql = "UPDATE usuarios SET senha = '$senha_criptografada' WHERE email = '$email_admin'";

// Executar a consulta
if (mysqli_query($conexao, $sql)) {
    echo "Senha do admin atualizada com sucesso!";
} else {
    echo "Erro ao atualizar a senha: " . mysqli_error($conexao);
}

// Fechar a conexão com o banco de dados
mysqli_close($conexao);
?>
