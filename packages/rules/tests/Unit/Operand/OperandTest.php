<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Operand;

use Phil\Rules\Exception\InvalidConditionType;
use Phil\Rules\Exception\InvalidLimit;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\OperandKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(Operand::class)]
#[CoversClass(OperandKind::class)]
#[CoversClass(InvalidConditionType::class)]
#[CoversClass(InvalidLimit::class)]
final class OperandTest extends TestCase
{
    /**
     * @return iterable<string, array{Operand, mixed, mixed}>
     */
    public static function accepted(): iterable
    {
        yield 'text' => [Operand::text('t'), 'Fête', 'Fête'];
        yield 'text at its length' => [Operand::text('t', maxLength: 3), 'abc', 'abc'];
        yield 'empty text' => [Operand::text('t'), '', ''];
        yield 'identifier' => [Operand::identifier('i'), 'sku:NAQ001F-M/blk_2.0', 'sku:NAQ001F-M/blk_2.0'];
        yield 'identifier of 128 bytes' => [Operand::identifier('i'), str_repeat('a', 128), str_repeat('a', 128)];
        yield 'integer' => [Operand::integer('n'), -4, -4];
        yield 'integer at its bounds' => [Operand::integer('n', min: 1, max: 3), 1, 1];
        yield 'integer at its upper bound' => [Operand::integer('n', min: 1, max: 3), 3, 3];
        yield 'number from an integer' => [Operand::number('n'), 2, 2.0];
        yield 'number' => [Operand::number('n'), 2.5, 2.5];
        yield 'true' => [Operand::boolean('b'), true, true];
        yield 'false' => [Operand::boolean('b'), false, false];
        yield 'choice' => [Operand::choice('c', ['lt', 'gt']), 'gt', 'gt'];
        yield 'date' => [Operand::date('d'), '2028-02-29', '2028-02-29'];
        yield 'scalar text' => [Operand::scalar('s'), 'CA', 'CA'];
        yield 'scalar integer' => [Operand::scalar('s'), 3, 3];
        yield 'scalar float' => [Operand::scalar('s'), 3.5, 3.5];
        yield 'scalar boolean' => [Operand::scalar('s'), false, false];
        yield 'identifier list' => [Operand::identifierList('l'), ['a', 'b'], ['a', 'b']];
        yield 'identifier list at its size' => [Operand::identifierList('l', maxItems: 2), ['a', 'b'], ['a', 'b']];
        yield 'scalar list' => [Operand::scalarList('l'), ['1', 1, 1.0, true], ['1', 1, 1.0, true]];
    }

    #[DataProvider('accepted')]
    public function testAcceptsAndKeeps(Operand $operand, mixed $written, mixed $kept): void
    {
        self::assertSame([true, $kept], $operand->accept($written));
    }

    /**
     * @return iterable<string, array{Operand, mixed, string}>
     */
    public static function refused(): iterable
    {
        yield 'text not a string' => [Operand::text('t'), 3, 'must be a text'];
        yield 'text too long' => [Operand::text('t', maxLength: 3), 'abcd', 'holds more than 3 bytes'];
        yield 'text with a control character' => [Operand::text('t'), "a\nb", 'must be UTF-8 without control characters'];
        yield 'text with a delete' => [Operand::text('t'), "a\x7F", 'must be UTF-8 without control characters'];
        yield 'text not UTF-8' => [Operand::text('t'), "\xC3", 'must be UTF-8 without control characters'];
        yield 'identifier with a space' => [Operand::identifier('i'), 'a b', 'must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
        yield 'identifier starting with a dash' => [Operand::identifier('i'), '-a', 'must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
        yield 'identifier with a new line' => [Operand::identifier('i'), "a\n", 'must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
        yield 'identifier too long' => [Operand::identifier('i'), str_repeat('a', 129), 'must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
        yield 'identifier not a string' => [Operand::identifier('i'), 4, 'must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
        yield 'integer from a float' => [Operand::integer('n'), 1.0, 'must be an integer'];
        yield 'integer below' => [Operand::integer('n', min: 1, max: 3), 0, 'must be 1 or more'];
        yield 'integer above' => [Operand::integer('n', min: 1, max: 3), 4, 'must be 3 or less'];
        yield 'number from a text' => [Operand::number('n'), '1', 'must be a finite number'];
        yield 'infinite number' => [Operand::number('n'), \INF, 'must be a finite number'];
        yield 'boolean from an integer' => [Operand::boolean('b'), 1, 'must be true or false'];
        yield 'unknown choice' => [Operand::choice('c', ['lt', 'gt']), 'eq', 'must be one of "lt", "gt"'];
        yield 'choice not a string' => [Operand::choice('c', ['1']), 1, 'must be one of "1"'];
        yield 'date that does not exist' => [Operand::date('d'), '2027-02-29', 'must be a calendar date written YYYY-MM-DD'];
        yield 'date with a time' => [Operand::date('d'), '2027-02-01T00:00', 'must be a calendar date written YYYY-MM-DD'];
        yield 'date with a trailing line' => [Operand::date('d'), "2027-02-01\n", 'must be a calendar date written YYYY-MM-DD'];
        yield 'date not a string' => [Operand::date('d'), 20270201, 'must be a calendar date written YYYY-MM-DD'];
        yield 'scalar null' => [Operand::scalar('s'), null, 'must be a text, a number or a boolean'];
        yield 'scalar list' => [Operand::scalar('s'), [], 'must be a text, a number or a boolean'];
        yield 'scalar infinite' => [Operand::scalar('s'), -\INF, 'must be a finite number'];
        yield 'scalar text too long' => [Operand::scalar('s', maxLength: 2), 'abc', 'holds more than 2 bytes'];
        yield 'list not an array' => [Operand::identifierList('l'), 'a', 'must be a non-empty list'];
        yield 'empty list' => [Operand::identifierList('l'), [], 'must be a non-empty list'];
        yield 'list keyed by name' => [Operand::identifierList('l'), ['x' => 'a'], 'must be a non-empty list'];
        yield 'list too long' => [Operand::identifierList('l', maxItems: 2), ['a', 'b', 'c'], 'holds more than 2 items'];
        yield 'list with a refused item' => [Operand::identifierList('l'), ['a', 'b c'], 'item 1 must be an identifier: a letter or digit, then letters, digits, "_", ".", ":", "/" or "-", 128 bytes at most'];
        yield 'list with a twice given item' => [Operand::identifierList('l'), ['a', 'b', 'a'], 'item 2 is given twice'];
        yield 'scalar list with a twice given number' => [Operand::scalarList('l'), [1.5, 1.5], 'item 1 is given twice'];
        yield 'scalar list with a long text' => [Operand::scalarList('l', maxLength: 1), ['ab'], 'item 0 holds more than 1 bytes'];
    }

    #[DataProvider('refused')]
    public function testRefusesWithAReason(Operand $operand, mixed $written, string $reason): void
    {
        self::assertSame([false, $reason], $operand->accept($written));
    }

    public function testKeepsItsDeclaration(): void
    {
        $operand = Operand::integer('seats', required: false, min: 1, max: 9);

        self::assertSame('seats', $operand->name);
        self::assertSame(OperandKind::Integer, $operand->kind);
        self::assertFalse($operand->required);
        self::assertTrue(Operand::text('t')->required);
    }

    public function testKeepsItsDefaultBounds(): void
    {
        self::assertSame(255, Operand::text('t')->maxLength);
        self::assertSame(255, Operand::identifier('i')->maxLength);
        self::assertSame(255, Operand::scalar('s')->maxLength);
        self::assertSame(255, Operand::scalarList('l')->maxLength);
        self::assertSame(100, Operand::identifierList('l')->maxItems);
        self::assertSame(100, Operand::scalarList('l')->maxItems);
        self::assertSame(100, Operand::identifier('i')->maxItems);
    }

    public function testRequiresEveryOperandByDefault(): void
    {
        foreach ([Operand::text('n'), Operand::identifier('n'), Operand::integer('n'), Operand::number('n'), Operand::boolean('n'), Operand::choice('n', ['a']), Operand::date('n'), Operand::scalar('n'), Operand::identifierList('n'), Operand::scalarList('n')] as $operand) {
            self::assertTrue($operand->required, $operand->kind->value);
        }
    }

    public function testAcceptsAMinimumWithoutAMaximum(): void
    {
        self::assertSame(3, Operand::integer('n', min: 3)->min);
        self::assertSame(-2, Operand::integer('n', max: -2)->max);
        self::assertSame([true, 4], Operand::integer('n', min: 3)->accept(4));
    }

    public function testRefusesATextEndingInANewline(): void
    {
        self::assertSame([false, 'must be UTF-8 without control characters'], Operand::text('t')->accept("abc\n"));
    }

    public function testTellsAnIntegerFromAFloatInAList(): void
    {
        $list = Operand::scalarList('l');

        self::assertSame([true, [1, 1.0, '1', true]], $list->accept([1, 1.0, '1', true]));
        self::assertSame([false, 'item 1 is given twice'], $list->accept([1, 1]));
        self::assertSame([false, 'item 1 is given twice'], $list->accept([1.0, 1.0]));
    }

    public function testRefusesADateWrittenAnotherWay(): void
    {
        $date = Operand::date('d');

        foreach (["2024-01-15\n", '2024-1-5', '10000-01-01', '2024-02-30', ' 2024-01-15', 5] as $written) {
            self::assertFalse($date->accept($written)[0], json_encode($written, \JSON_THROW_ON_ERROR));
        }

        self::assertSame([true, '2024-02-29'], $date->accept('2024-02-29'));
    }

    /**
     * @return iterable<string, array{Operand, array<string, mixed>}>
     */
    public static function descriptions(): iterable
    {
        yield 'text' => [Operand::text('t', maxLength: 9), ['name' => 't', 'kind' => 'text', 'required' => true, 'maxLength' => 9]];
        yield 'identifier' => [Operand::identifier('i', required: false), ['name' => 'i', 'kind' => 'identifier', 'required' => false]];
        yield 'integer' => [Operand::integer('n', min: 0, max: 5), ['name' => 'n', 'kind' => 'integer', 'required' => true, 'min' => 0, 'max' => 5]];
        yield 'number' => [Operand::number('n'), ['name' => 'n', 'kind' => 'number', 'required' => true]];
        yield 'boolean' => [Operand::boolean('b'), ['name' => 'b', 'kind' => 'boolean', 'required' => true]];
        yield 'choice' => [Operand::choice('c', ['a', 'b']), ['name' => 'c', 'kind' => 'choice', 'required' => true, 'choices' => ['a', 'b']]];
        yield 'date' => [Operand::date('d'), ['name' => 'd', 'kind' => 'date', 'required' => true]];
        yield 'scalar' => [Operand::scalar('s'), ['name' => 's', 'kind' => 'scalar', 'required' => true, 'maxLength' => 255]];
        yield 'identifier list' => [Operand::identifierList('l', maxItems: 7), ['name' => 'l', 'kind' => 'identifier-list', 'required' => true, 'maxItems' => 7]];
        yield 'scalar list' => [Operand::scalarList('l'), ['name' => 'l', 'kind' => 'scalar-list', 'required' => true, 'maxLength' => 255, 'maxItems' => 100]];
    }

    /**
     * @param array<string, mixed> $description
     */
    #[DataProvider('descriptions')]
    public function testDescribesItselfForABuilder(Operand $operand, array $description): void
    {
        self::assertSame($description, $operand->describe());
    }

    /**
     * @return iterable<string, array{callable(): Operand, class-string<Throwable>, string}>
     */
    public static function badDeclarations(): iterable
    {
        yield 'name with a capital' => [static fn (): Operand => Operand::text('Name'), InvalidConditionType::class, '"Name" is not an operand name'];
        yield 'name with a dash' => [static fn (): Operand => Operand::text('a-b'), InvalidConditionType::class, '"a-b" is not an operand name'];
        yield 'name too long' => [static fn (): Operand => Operand::text('a'.str_repeat('b', 64)), InvalidConditionType::class, 'is not an operand name'];
        yield 'name with a trailing line' => [static fn (): Operand => Operand::text("a\n"), InvalidConditionType::class, 'is not an operand name'];
        yield 'no choice' => [static fn (): Operand => Operand::choice('c', []), InvalidConditionType::class, 'The operand "c" needs at least one choice'];
        yield 'empty choice' => [static fn (): Operand => Operand::choice('c', ['a', '']), InvalidConditionType::class, 'needs at least one choice'];
        yield 'twice the same choice' => [static fn (): Operand => Operand::choice('c', ['a', 'a']), InvalidConditionType::class, 'needs at least one choice'];
        yield 'no length' => [static fn (): Operand => Operand::text('t', maxLength: 0), InvalidLimit::class, 'maxLength is 0; it must lie between 1 and 10000.'];
        yield 'too long a length' => [static fn (): Operand => Operand::text('t', maxLength: 10001), InvalidLimit::class, 'maxLength is 10001'];
        yield 'no items' => [static fn (): Operand => Operand::identifierList('l', maxItems: 0), InvalidLimit::class, 'maxItems is 0; it must lie between 1 and 1000.'];
        yield 'too many items' => [static fn (): Operand => Operand::scalarList('l', maxItems: 1001), InvalidLimit::class, 'maxItems is 1001'];
        yield 'bounds crossed' => [static fn (): Operand => Operand::integer('n', min: 3, max: 2), InvalidLimit::class, 'max is 2; it must lie between 3 and'];
    }

    /**
     * @param callable(): Operand     $declare
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('badDeclarations')]
    public function testRefusesABadDeclaration(callable $declare, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        $declare();
    }

    public function testAcceptsTheWidestBounds(): void
    {
        self::assertSame(10000, Operand::text('t', maxLength: 10000)->maxLength);
        self::assertSame(1, Operand::text('t', maxLength: 1)->maxLength);
        self::assertSame(1000, Operand::identifierList('l', maxItems: 1000)->maxItems);
        self::assertSame(1, Operand::identifierList('l', maxItems: 1)->maxItems);
        self::assertSame(2, Operand::integer('n', min: 2, max: 2)->max);
        self::assertSame('a'.str_repeat('b', 63), Operand::text('a'.str_repeat('b', 63))->name);
    }
}
