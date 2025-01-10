<?php
session_start();
include 'conexao.php'; // Conexão com o banco de dados

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Receber os dados do formulário
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];

    // Validar se a nova senha e a confirmação da senha são iguais
    if ($nova_senha !== $confirmar_senha) {
        echo "<p>As senhas não coincidem. Tente novamente.</p>";
        exit();
    }

    // Obter a senha atual do banco de dados
    $query = "SELECT senha FROM usuarios WHERE id = ?";
    $stmt = mysqli_prepare($conexao, $query);
    mysqli_stmt_bind_param($stmt, "i", $usuario_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $senha_bd);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Verificar se a senha atual está correta
    if (!password_verify($senha_atual, $senha_bd)) {
        echo "<p>A senha atual está incorreta. Tente novamente.</p>";
        exit();
    }

    // Gerar o hash da nova senha
    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

    // Atualizar a senha no banco de dados
    $update_query = "UPDATE usuarios SET senha = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conexao, $update_query);
    mysqli_stmt_bind_param($update_stmt, "si", $nova_senha_hash, $usuario_id);
    $executou = mysqli_stmt_execute($update_stmt);

    if ($executou) {
        // Se a senha foi alterada com sucesso, redireciona para a página inicial
        header("Location: index.php"); // Redireciona para a página index.php
        exit();
    } else {
        echo "<p>Erro ao alterar a senha. Tente novamente mais tarde.</p>";
    }

    mysqli_stmt_close($update_stmt);
}
?>
