<?php

use App\Calculator;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    private $calculator;

    protected function setUp(): void
    {
        $this->calculator = new Calculator();
    }

    public function testAdd()
    {
        $this->assertEquals(5, $this->calculator->add(2, 3));
    }

    public function testSubtract()
    {
        $this->assertEquals(1, $this->calculator->subtract(3, 2));
    }
}

