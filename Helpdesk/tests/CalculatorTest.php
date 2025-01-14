<?php

use PHPUnit\Framework\TestCase;
use Helpdesk\Calculator; // Usar o namespace correto

require_once __DIR__ . '/../src/Calculator.php'; // Carregar a classe a partir de src/

class CalculatorTest extends TestCase
{
    private $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    public function testAddition()
    {
        $this->assertEquals(5, $this->calculator->add(2, 3));
    }

    public function testSubtraction()
    {
        $this->assertEquals(1, $this->calculator->subtract(3, 2));
    }
}
