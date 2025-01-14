<?php

// Função para somar dois números
function somar($a, $b) {
    return $a + $b;
}

// Usando a função com um erro de variável indefinida
$resultado = somar($numero1, 5); // $numero1 não foi definida previamente

echo "O resultado é: " . $resultado;
