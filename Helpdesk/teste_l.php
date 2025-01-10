<?php
// PHP code com problemas no padrão PSR-12
function testFunction() {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Test Page</title>
        <style>
            /* CSS com erros */
            body {
                background-colour: #f0f0f0; /* Propriedade escrita incorretamente */
                font-family: Arial, sans-serif;
            }
            .error {
                colorr: blue; /* Propriedade inválida */
                font-weight bold; /* Faltando dois-pontos */
            }
        </style>
    </head>
    <body>
        <h1 class='error'>Welcome to Test Page</h1>
        <script>
            // JavaScript com erros
            var x = 10
            console.log(x));
        </script>
    </body>
    </html>";
}
testFunction();
?>
