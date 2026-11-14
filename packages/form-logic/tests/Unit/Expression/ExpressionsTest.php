<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\Expression;

use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\AllOf;
use Phil\FormLogic\Expression\AnyOf;
use Phil\FormLogic\Expression\Arithmetic;
use Phil\FormLogic\Expression\ArithmeticOperator;
use Phil\FormLogic\Expression\Between;
use Phil\FormLogic\Expression\Comparison;
use Phil\FormLogic\Expression\ComparisonOperator;
use Phil\FormLogic\Expression\Conditional;
use Phil\FormLogic\Expression\In;
use Phil\FormLogic\Expression\ListOf;
use Phil\FormLogic\Expression\Literal;
use Phil\FormLogic\Expression\Missing;
use Phil\FormLogic\Expression\Not;
use Phil\FormLogic\Expression\Variable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllOf::class)]
#[CoversClass(AnyOf::class)]
#[CoversClass(Arithmetic::class)]
#[CoversClass(ArithmeticOperator::class)]
#[CoversClass(Between::class)]
#[CoversClass(Comparison::class)]
#[CoversClass(ComparisonOperator::class)]
#[CoversClass(Conditional::class)]
#[CoversClass(In::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Literal::class)]
#[CoversClass(Missing::class)]
#[CoversClass(Not::class)]
#[CoversClass(Variable::class)]
final class ExpressionsTest extends TestCase
{
    public function testEachNodeWritesItsRuleAndListsItsChildren(): void
    {
        $a = new Variable('a');
        $one = new Literal(1);
        $two = new Literal(2);

        self::assertSame([], $one->children());
        self::assertSame([], $a->children());
        self::assertSame(['var' => 'a'], $a->jsonLogic());
        self::assertSame(['var' => ['a', 1]], new Variable('a', $one)->jsonLogic());
        self::assertSame([$one], new Variable('a', $one)->children());
        self::assertSame([1, ['var' => 'a']], new ListOf([$one, $a])->jsonLogic());
        self::assertSame([$one, $a], new ListOf([$one, $a])->children());
        self::assertSame(['!==' => [['var' => 'a'], 1]], new Comparison(ComparisonOperator::StrictlyNotEqual, $a, $one)->jsonLogic());
        self::assertSame([$a, $one], new Comparison(ComparisonOperator::Equal, $a, $one)->children());
        self::assertSame(['<' => [1, ['var' => 'a'], 2]], new Between($one, $a, $two)->jsonLogic());
        self::assertSame(['<=' => [1, ['var' => 'a'], 2]], new Between($one, $a, $two, true)->jsonLogic());
        self::assertSame([$one, $a, $two], new Between($one, $a, $two)->children());
        self::assertSame(['and' => [1, 2]], new AllOf([$one, $two])->jsonLogic());
        self::assertSame([$one, $two], new AllOf([$one, $two])->children());
        self::assertSame(['or' => [1, 2]], new AnyOf([$one, $two])->jsonLogic());
        self::assertSame([$one, $two], new AnyOf([$one, $two])->children());
        self::assertSame(['!' => [1]], new Not($one)->jsonLogic());
        self::assertSame(['!!' => [1]], new Not($one, true)->jsonLogic());
        self::assertSame([$one], new Not($one)->children());
        self::assertSame(['in' => [1, ['var' => 'a']]], new In($one, $a)->jsonLogic());
        self::assertSame([$one, $a], new In($one, $a)->children());
        self::assertSame(['missing' => ['a', 'b']], new Missing(['a', 'b'])->jsonLogic());
        self::assertSame(['missing_some' => [1, ['a', 'b']]], new Missing(['a', 'b'], 1)->jsonLogic());
        self::assertSame([], new Missing(['a'])->children());
        self::assertSame(['if' => [1, 2]], new Conditional([[$one, $two]])->jsonLogic());
        self::assertSame(['if' => [1, 2, ['var' => 'a']]], new Conditional([[$one, $two]], $a)->jsonLogic());
        self::assertSame([$one, $two, $one, $a], new Conditional([[$one, $two], [$one, $a]])->children());
        self::assertSame(['%' => [1, 2]], new Arithmetic(ArithmeticOperator::Modulo, [$one, $two])->jsonLogic());
        self::assertSame([$one, $two], new Arithmetic(ArithmeticOperator::Modulo, [$one, $two])->children());
    }

    public function testANumberIsKeptAsJavaScriptHoldsIt(): void
    {
        self::assertSame(2, new Literal(2.0)->value);
        self::assertSame(0, new Literal(-0.0)->value);
        self::assertSame(2.5, new Literal(2.5)->value);
        self::assertSame(9_007_199_254_740_991, new Literal(9_007_199_254_740_991)->value);
        self::assertSame(9_007_199_254_740_992.0, new Literal(9_007_199_254_740_993)->value);
        self::assertSame(-9_007_199_254_740_992.0, new Literal(-9_007_199_254_740_993)->value);
        self::assertSame('a', new Literal('a')->value);
        self::assertNull(new Literal(null)->value);
    }

    public function testANonFiniteNumberIsRefused(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('A number of a rule is finite.'));

        new Literal(\INF);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validNames(): iterable
    {
        yield 'a letter' => ['a'];
        yield 'an underscore' => ['_'];
        yield 'mixed' => ['Guest_2'];
        yield '64 characters' => [str_repeat('a', 64)];
        yield 'eight segments' => ['a.1.b.2.c.3.d.4'];
        yield 'a numeric segment' => ['guests.0'];
        yield 'a 64-character segment' => ['a.'.str_repeat('b', 64)];
    }

    #[DataProvider('validNames')]
    public function testValidNames(string $name): void
    {
        self::assertSame($name, new Variable($name)->name);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'a digit first' => ['1a'];
        yield '65 characters' => [str_repeat('a', 65)];
        yield 'nine segments' => ['a.1.b.2.c.3.d.4.e'];
        yield 'an empty segment' => ['a..b'];
        yield 'a trailing dot' => ['a.'];
        yield 'a dash' => ['a-b'];
        yield 'a line break' => ["a\n"];
        yield 'a 65-character segment' => ['a.'.str_repeat('b', 65)];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNames(string $name): void
    {
        $this->expectException(InvalidLogic::class);

        new Variable($name);
    }

    public function testTheMessageOfAnInvalidNameShowsItsStart(): void
    {
        $this->expectExceptionObject(InvalidLogic::because(\sprintf('A variable is named by 1 to 8 segments of letters, digits or "_", joined by dots, the first starting with a letter or "_"; "%s" is not.', 'é'.str_repeat('ü', 79))));

        Variable::checkName('é'.str_repeat('ü', 100));
    }

    public function testThePathOfANameIsItsSegments(): void
    {
        self::assertSame(['guest', '0', 'name'], new Variable('guest.0.name')->path());
    }

    public function testMissingChecksItsNamesAndCount(): void
    {
        self::assertSame(0, new Missing(['a', 'b'], 0)->atLeast);
        self::assertSame(2, new Missing(['a', 'b'], 2)->atLeast);

        foreach ([-1, 3] as $atLeast) {
            try {
                new Missing(['a', 'b'], $atLeast);
                self::fail('The count is checked.');
            } catch (InvalidLogic $exception) {
                self::assertSame('A "missing_some" needs 0 to 2 of its names.', $exception->getMessage());
            }
        }

        try {
            new Missing([]);
            self::fail('A name is needed.');
        } catch (InvalidLogic $invalidLogic) {
            self::assertSame('A "missing" names at least one variable.', $invalidLogic->getMessage());
        }

        $this->expectException(InvalidLogic::class);
        new Missing(['a', '1']);
    }

    public function testAnEmptyAndOrOrIsRefused(): void
    {
        foreach ([AllOf::class => 'An "and" has at least one operand.', AnyOf::class => 'An "or" has at least one operand.'] as $class => $message) {
            try {
                new $class([]);
                self::fail('No operand is refused.');
            } catch (InvalidLogic $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testAConditionalHasABranch(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('An "if" has at least one condition and its value.'));

        new Conditional([]);
    }

    /**
     * @return iterable<string, array{0: ArithmeticOperator, 1: int, 2: string|null}>
     */
    public static function arities(): iterable
    {
        foreach ([ArithmeticOperator::Add, ArithmeticOperator::Multiply, ArithmeticOperator::Min, ArithmeticOperator::Max] as $operator) {
            yield $operator->name.' of 1' => [$operator, 1, null];
            yield $operator->name.' of 5' => [$operator, 5, null];
        }

        yield 'Subtract of 1' => [ArithmeticOperator::Subtract, 1, null];
        yield 'Subtract of 2' => [ArithmeticOperator::Subtract, 2, null];
        yield 'Subtract of 3' => [ArithmeticOperator::Subtract, 3, 'A "-" takes 1 or 2 operands.'];

        foreach ([ArithmeticOperator::Divide, ArithmeticOperator::Modulo] as $operator) {
            yield $operator->name.' of 1' => [$operator, 1, \sprintf('A "%s" takes 2 operands.', $operator->value)];
            yield $operator->name.' of 2' => [$operator, 2, null];
            yield $operator->name.' of 3' => [$operator, 3, \sprintf('A "%s" takes 2 operands.', $operator->value)];
        }
    }

    #[DataProvider('arities')]
    public function testArithmeticTakesTheOperandsOfItsOperator(ArithmeticOperator $operator, int $count, ?string $refused): void
    {
        if (null !== $refused) {
            $this->expectExceptionObject(InvalidLogic::because($refused));
        }

        $operands = array_fill(0, $count, new Literal(1));
        self::assertCount($count, new Arithmetic($operator, $operands)->operands);
    }

    public function testArithmeticWithoutOperandIsRefused(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('A "+" takes 1 or more operands.'));

        new Arithmetic(ArithmeticOperator::Add, []);
    }

    public function testOnlyOrderingComparisonsOrder(): void
    {
        $ordering = array_filter(ComparisonOperator::cases(), static fn (ComparisonOperator $operator): bool => $operator->orders());

        self::assertSame([ComparisonOperator::Less, ComparisonOperator::LessOrEqual, ComparisonOperator::Greater, ComparisonOperator::GreaterOrEqual], array_values($ordering));
    }
}
