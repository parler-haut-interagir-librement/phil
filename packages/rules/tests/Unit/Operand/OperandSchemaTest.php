<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Operand;

use Phil\Rules\Exception\InvalidConditionType;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperandSchema::class)]
#[CoversClass(Operands::class)]
#[CoversClass(InvalidRule::class)]
final class OperandSchemaTest extends TestCase
{
    private OperandSchema $schema;

    protected function setUp(): void
    {
        $this->schema = new OperandSchema(Operand::identifier('fact'), Operand::number('value'), Operand::boolean('strict', required: false));
    }

    public function testReadsTheOperandsInTheOrderOfTheSchema(): void
    {
        $operands = $this->schema->read(['value' => 3, 'fact' => 'age'], 'test.type', '/condition');

        self::assertSame(['fact' => 'age', 'value' => 3.0], $operands->toArray());
        self::assertFalse($operands->has('strict'));
    }

    public function testKeepsAnOptionalOperandGiven(): void
    {
        self::assertSame(['fact' => 'age', 'value' => 1.0, 'strict' => true], $this->schema->read(['fact' => 'age', 'value' => 1, 'strict' => true], 't', '/c')->toArray());
    }

    public function testReadsTheOperandsAfterAnOptionalOneLeftOut(): void
    {
        $schema = new OperandSchema(Operand::boolean('strict', required: false), Operand::identifier('fact'), Operand::number('value'));

        self::assertSame(['fact' => 'age', 'value' => 2.0], $schema->read(['fact' => 'age', 'value' => 2], 't', '/c')->toArray());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'a list' => [['age', 3], 'The rule is malformed at "/condition/all/0/operands": the operands are an object keyed by name, not a list.'];
        yield 'an unknown operand' => [['fact' => 'age', 'value' => 3, 'valeu' => 4], 'The operand "valeu" of the condition "test.type" at "/condition/all/0" is not one the type accepts.'];
        yield 'a missing operand' => [['fact' => 'age'], 'The operand "value" of the condition "test.type" at "/condition/all/0" is required.'];
        yield 'a refused operand' => [['fact' => 'age', 'value' => 'three'], 'The operand "value" of the condition "test.type" at "/condition/all/0" must be a finite number.'];
        yield 'an unknown integer key' => [['fact' => 'age', 'value' => 3, 7 => 1], 'The operand "7" of the condition "test.type"'];
    }

    /**
     * @param array<array-key, mixed> $written
     */
    #[DataProvider('refused')]
    public function testRefuses(array $written, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->schema->read($written, 'test.type', '/condition/all/0');
    }

    public function testAnEmptySchemaReadsNoOperand(): void
    {
        self::assertSame([], new OperandSchema()->read([], 't', '/c')->toArray());
    }

    public function testRefusesAnOperandDeclaredTwice(): void
    {
        $this->expectException(InvalidConditionType::class);
        $this->expectExceptionMessageIsOrContains('The operand "fact" is declared twice.');

        new OperandSchema(Operand::identifier('fact'), Operand::text('fact'));
    }

    public function testDescribesItsOperandsInOrder(): void
    {
        self::assertSame([
            ['name' => 'fact', 'kind' => 'identifier', 'required' => true],
            ['name' => 'value', 'kind' => 'number', 'required' => true],
            ['name' => 'strict', 'kind' => 'boolean', 'required' => false],
        ], $this->schema->describe());
    }
}
