$senha_criptografada = password_hash('adminpassword', PASSWORD_DEFAULT);
$query = "INSERT INTO usuarios (nome, tipo, email, senha) VALUES ('Admin', 'admin', 'admin@ip4y.com.br', '$senha_criptografada')";
mysqli_query($conexao, $query);
