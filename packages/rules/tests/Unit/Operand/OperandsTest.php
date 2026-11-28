<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Operand;

use Phil\Rules\Exception\MissingOperand;
use Phil\Rules\Operand\Operands;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Operands::class)]
#[CoversClass(MissingOperand::class)]
final class OperandsTest extends TestCase
{
    private Operands $operands;

    protected function setUp(): void
    {
        $this->operands = new Operands(['s' => 'x', 'i' => 2, 'f' => 2.5, 'b' => false, 'l' => ['a', 'b'], 'm' => ['a', 1, 1.5, true]]);
    }

    public function testReadsEachKind(): void
    {
        self::assertSame('x', $this->operands->string('s'));
        self::assertSame(2, $this->operands->int('i'));
        self::assertSame(2.5, $this->operands->float('f'));
        self::assertFalse($this->operands->bool('b'));
        self::assertSame('x', $this->operands->scalar('s'));
        self::assertFalse($this->operands->scalar('b'));
        self::assertSame(['a', 'b'], $this->operands->strings('l'));
        self::assertSame(['a', 1, 1.5, true], $this->operands->scalars('m'));
        self::assertTrue($this->operands->has('b'));
        self::assertFalse($this->operands->has('z'));
    }

    public function testKeepsOnlyTheItemsOfTheKindAsAList(): void
    {
        $operands = new Operands(['l' => ['a', 1, 'b', null], 'm' => ['a', null, [1], 2]]);

        self::assertSame(['a', 'b'], $operands->strings('l'));
        self::assertSame(['a', 2], $operands->scalars('m'));
    }

    /**
     * @return iterable<string, array{callable(Operands): mixed, string}>
     */
    public static function misreads(): iterable
    {
        yield 'string' => [static fn (Operands $o): mixed => $o->string('i'), 'The operand "i" is not a string in these operands.'];
        yield 'int' => [static fn (Operands $o): mixed => $o->int('f'), 'The operand "f" is not an integer in these operands.'];
        yield 'float' => [static fn (Operands $o): mixed => $o->float('i'), 'The operand "i" is not a number in these operands.'];
        yield 'bool' => [static fn (Operands $o): mixed => $o->bool('s'), 'The operand "s" is not a boolean in these operands.'];
        yield 'scalar' => [static fn (Operands $o): mixed => $o->scalar('l'), 'The operand "l" is not a scalar in these operands.'];
        yield 'missing scalar' => [static fn (Operands $o): mixed => $o->scalar('z'), 'The operand "z" is not a scalar in these operands.'];
        yield 'strings' => [static fn (Operands $o): mixed => $o->strings('s'), 'The operand "s" is not a list in these operands.'];
        yield 'scalars' => [static fn (Operands $o): mixed => $o->scalars('z'), 'The operand "z" is not a list in these operands.'];
    }

    /**
     * @param callable(Operands): mixed $read
     */
    #[DataProvider('misreads')]
    public function testRefusesAMisread(callable $read, string $message): void
    {
        $this->expectException(MissingOperand::class);
        $this->expectExceptionMessageIsOrContains($message);

        $read($this->operands);
    }
}
