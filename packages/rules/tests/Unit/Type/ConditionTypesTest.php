<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Type;

use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Exception\InvalidConditionType;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Tests\Support\Recorder;
use Phil\Rules\Type\ConditionType;
use Phil\Rules\Type\ConditionTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConditionTypes::class)]
#[CoversClass(InvalidConditionType::class)]
final class ConditionTypesTest extends TestCase
{
    public function testRegistersTheBuiltInTypes(): void
    {
        $types = ConditionTypes::builtIn();

        self::assertSame(['fact.equals', 'fact.in', 'fact.contains', 'fact.compare', 'fact.present', 'date.compare'], $types->names());
        self::assertTrue($types->has('fact.in'));
        self::assertFalse($types->has('fact.like'));
        self::assertNull($types->get('fact.like'));
        self::assertSame('fact.in', $types->get('fact.in')?->name());
    }

    public function testAddsTypesWithoutChangingTheRegistry(): void
    {
        $types = new ConditionTypes();
        $recorder = new Recorder();
        $more = $types->with($recorder);

        self::assertSame([], $types->names());
        self::assertSame(['test.flag'], $more->names());
        self::assertSame($recorder, $more->get('test.flag'));
    }

    public function testAddsTypesToTheOnesAlreadyRegistered(): void
    {
        $more = ConditionTypes::builtIn()->with(new Recorder());

        self::assertSame(['fact.equals', 'fact.in', 'fact.contains', 'fact.compare', 'fact.present', 'date.compare', 'test.flag'], $more->names());
    }

    public function testDescribesEveryType(): void
    {
        self::assertSame(
            ['test.flag' => [
                ['name' => 'holds', 'kind' => 'boolean', 'required' => true],
                ['name' => 'label', 'kind' => 'identifier', 'required' => false],
            ]],
            new ConditionTypes(new Recorder())->describe(),
        );
    }

    public function testRefusesATypeRegisteredTwice(): void
    {
        $this->expectException(InvalidConditionType::class);
        $this->expectExceptionMessageIsOrContains('The condition type "test.flag" is registered twice.');

        new ConditionTypes(new Recorder())->with(new Recorder());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'capital' => ['Fact.equals'];
        yield 'leading digit' => ['1fact'];
        yield 'double dot' => ['fact..equals'];
        yield 'trailing dot' => ['fact.'];
        yield 'space' => ['fact equals'];
        yield 'trailing line' => ["fact\n"];
        yield 'too long' => ['a'.str_repeat('b', 64)];
        yield 'empty' => [''];
    }

    #[DataProvider('badNames')]
    public function testRefusesANameOutsideTheStableForm(string $name): void
    {
        $this->expectException(InvalidConditionType::class);
        $this->expectExceptionMessageIsOrContains('is not a condition type name');

        new ConditionTypes($this->named($name));
    }

    public function testAcceptsTheLongestName(): void
    {
        $name = 'a'.str_repeat('b', 63);

        self::assertSame([$name, 'a-b_c.d9'], new ConditionTypes($this->named($name), $this->named('a-b_c.d9'))->names());
    }

    private function named(string $name): ConditionType
    {
        return new readonly class($name) implements ConditionType {
            public function __construct(private string $typeName)
            {
            }

            public function name(): string
            {
                return $this->typeName;
            }

            public function operands(): OperandSchema
            {
                return new OperandSchema();
            }

            public function evaluate(Operands $operands, FactSource $facts): Verdict
            {
                return Verdict::pass('always');
            }
        };
    }
}
