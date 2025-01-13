<?php
// Exemplo com erro de formatação PSR-12

class ExampleClass
{
    public function exampleMethod() {  // Erro de formatação: espaço extra antes da chave
        echo "Hello, World!";
    }
}

$example = new ExampleClass();
$example->exampleMethod();
?>
