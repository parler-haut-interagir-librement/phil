<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\Evaluation;

use Override;
use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\Arithmetic;
use Phil\FormLogic\Expression\ArithmeticOperator;
use Phil\FormLogic\Expression\Literal;
use Phil\FormLogic\Expression\Missing;
use Phil\FormLogic\Expression\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Evaluator::class)]
final class EvaluatorTest extends TestCase
{
    public function testAVariableReadsIntoMapsAndLists(): void
    {
        $evaluator = new Evaluator();
        $data = ['guest' => ['name' => 'Ada', 'days' => ['fri', 'sat']], 'n' => 'x', 'big' => [5 => 'five'], 'object' => new stdClass()];

        self::assertSame('Ada', $evaluator->evaluate(new Variable('guest.name'), $data));
        self::assertSame('sat', $evaluator->evaluate(new Variable('guest.days.1'), $data));
        self::assertSame('fri', $evaluator->evaluate(new Variable('guest.days.0'), $data));
        self::assertNull($evaluator->evaluate(new Variable('guest.days.name'), $data));
        self::assertNull($evaluator->evaluate(new Variable('n.length'), $data));
        self::assertSame('five', $evaluator->evaluate(new Variable('big.5'), $data));
        self::assertNull($evaluator->evaluate(new Variable('object'), $data));
        self::assertNull($evaluator->evaluate(new Variable('guest.days.1000000000'), ['guest' => ['days' => ['a']]]));
        self::assertNull($evaluator->evaluate(new Variable('guest.days.01'), $data));
    }

    public function testAVariableWithAFalseValueKeepsItOverItsDefault(): void
    {
        $evaluator = new Evaluator();

        self::assertFalse($evaluator->evaluate(new Variable('a', new Literal(true)), ['a' => false]));
        self::assertSame('', $evaluator->evaluate(new Variable('a', new Literal('x')), ['a' => '']));
        self::assertSame('x', $evaluator->evaluate(new Variable('a', new Literal('x')), []));
    }

    public function testMissingReadsDottedNames(): void
    {
        self::assertSame(['a.c'], new Evaluator()->evaluate(new Missing(['a.b', 'a.c']), ['a' => ['b' => 1]]));
    }

    public function testArithmeticOnTheOperandsInOrder(): void
    {
        $evaluator = new Evaluator();

        self::assertSame(-1, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Subtract, [new Literal(1), new Literal(2)]), []));
        self::assertSame(0.5, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Divide, [new Literal(1), new Literal(2)]), []));
        self::assertSame(2, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Divide, [new Literal(4), new Literal(2)]), []));
        self::assertSame(1, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Modulo, [new Literal(4), new Literal(3)]), []));
        self::assertSame(0, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Modulo, [new Literal(5), new Literal(1)]), []));
        self::assertNull($evaluator->evaluate(new Arithmetic(ArithmeticOperator::Modulo, [new Literal(5), new Literal(0)]), []));
        self::assertNull($evaluator->evaluate(new Arithmetic(ArithmeticOperator::Divide, [new Literal(5), new Literal(0)]), []));
        self::assertSame(-3, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Min, [new Literal(2), new Literal(-3), new Literal(5)]), []));
        self::assertSame(5, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Max, [new Literal(2), new Literal(-3), new Literal(5)]), []));
        self::assertNull($evaluator->evaluate(new Arithmetic(ArithmeticOperator::Max, [new Literal(2), new Literal('x'), new Literal(5)]), []));
        self::assertNull($evaluator->evaluate(new Arithmetic(ArithmeticOperator::Min, [new Literal(2), new Literal(5), new Literal('x')]), []));
        self::assertSame(24, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Multiply, [new Literal(2), new Literal(3), new Literal(4)]), []));
        self::assertSame(9, $evaluator->evaluate(new Arithmetic(ArithmeticOperator::Add, [new Literal(2), new Literal(3), new Literal(4)]), []));
    }

    public function testAnExpressionOfAnotherLibraryIsRefused(): void
    {
        $foreign = new class implements Expression {
            #[Override]
            public function jsonLogic(): array
            {
                return ['foreign' => []];
            }

            #[Override]
            public function children(): array
            {
                return [];
            }
        };

        $this->expectException(InvalidLogic::class);
        $this->expectExceptionMessageIsOrContains('The evaluator reads the expressions of the library only, not ');
        new Evaluator()->evaluate($foreign, []);
    }

    public function testHoldsTellsTheTruthOfTheValue(): void
    {
        $evaluator = new Evaluator();

        self::assertTrue($evaluator->holds(new Variable('a'), ['a' => [0]]));
        self::assertFalse($evaluator->holds(new Variable('a'), ['a' => []]));
    }
}
