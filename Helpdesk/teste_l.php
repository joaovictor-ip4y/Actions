<?php
// PHP com erros de sintaxe e de padrão PSR-12
class TestClass {
function testMethod () { // Erro: espaço desnecessário entre o nome do método e os parênteses
echo "Edasrro de estilo no PHP"; // Erro: falta de indentação e visibilidade ausente no método
}
}

$object = new TestClass();
$object->testMethod();
?>
<html>
    <head>
        <title>Teste</title>
        <style>
            /* CSS com erros */
            body {
                background-colour: #ffffff; /* Erro: propriedade incorreta */
                font-weight bold; /* Erro: falta de dois-pontos */
            }
            h1 {
                color: red /* Erro: ponto e vírgula ausente */
            }
        </style>
    </head>
    <body>
        <h1>Teste de Página</h1>
        <script>
            // JavaScript com erros
            const test = "Erro no JavaScript" // Erro: falta de ponto e vírgula
            console.log(test)) // Erro: parêntese extra
        </script>
    </body>
</html>

