<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\Typing;

use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\Typing\Schema;
use Phil\FormLogic\Typing\TypeChecker;
use Phil\FormLogic\Typing\VariableDefinition;
use Phil\FormLogic\Typing\VariableNames;
use Phil\FormLogic\Typing\VariableType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeChecker::class)]
#[CoversClass(Schema::class)]
#[CoversClass(VariableDefinition::class)]
#[CoversClass(VariableNames::class)]
#[CoversClass(VariableType::class)]
final class TypingTest extends TestCase
{
    private function schema(): Schema
    {
        return new Schema([
            new VariableDefinition('name', VariableType::Text),
            new VariableDefinition('nickname', VariableType::Text),
            new VariableDefinition('age', VariableType::Number),
            new VariableDefinition('height', VariableType::Number),
            new VariableDefinition('agreed', VariableType::Boolean),
            new VariableDefinition('subscribed', VariableType::Boolean),
            new VariableDefinition('day', VariableType::Date),
            new VariableDefinition('arrival', VariableType::Date),
            new VariableDefinition('team', VariableType::Choice, ['Blue', 'Green']),
            new VariableDefinition('colours', VariableType::Choices, ['red', 'blue']),
        ]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function accepted(): iterable
    {
        yield 'text equal' => ['{"==": [{"var": "name"}, "Ada"]}'];
        yield 'text on the right' => ['{"!=": ["Ada", {"var": "name"}]}'];
        yield 'text against null' => ['{"==": [{"var": "name"}, null]}'];
        yield 'number ordered' => ['{">=": [{"var": "age"}, 18]}'];
        yield 'number with a decimal' => ['{"<": [{"var": "height"}, 1.8]}'];
        yield 'boolean' => ['{"===": [{"var": "agreed"}, true]}'];
        yield 'date ordered' => ['{"<": [{"var": "day"}, "2026-02-28"]}'];
        yield 'leap day' => ['{"==": [{"var": "day"}, "2028-02-29"]}'];
        yield 'choice' => ['{"==": [{"var": "team"}, "Green"]}'];
        yield 'choice in a list' => ['{"in": [{"var": "team"}, ["Blue", "Green"]]}'];
        yield 'choice among choices' => ['{"in": ["red", {"var": "colours"}]}'];
        yield 'text in a text' => ['{"in": ["@", {"var": "name"}]}'];
        yield 'variable in choices' => ['{"in": [{"var": "name"}, {"var": "colours"}]}'];
        yield 'two texts' => ['{"==": [{"var": "name"}, {"var": "nickname"}]}'];
        yield 'two days ordered' => ['{"<": [{"var": "day"}, {"var": "arrival"}]}'];
        yield 'two booleans' => ['{"==": [{"var": "agreed"}, {"var": "subscribed"}]}'];
        yield 'number interval' => ['{"<=": [0, {"var": "age"}, 17]}'];
        yield 'date interval' => ['{"<": ["2026-01-01", {"var": "day"}, "2026-12-31"]}'];
        yield 'interval of constants' => ['{"<": [1, 2, 3]}'];
        yield 'arithmetic' => ['{"+": [{"var": "age"}, {"var": "height"}, 1]}'];
        yield 'nested' => ['{"and": [{"!": [{"missing": ["name"]}]}, {"or": [{"var": "agreed"}, {"if": [{"var": "subscribed"}, true, false]}]}]}'];
        yield 'two constants' => ['{"==": [1, "1"]}'];
        yield 'computed operand' => ['{">": [{"+": [{"var": "age"}, 1]}, 18]}'];
        yield 'list in a list' => ['{"in": ["a", ["a"]]}'];
        yield 'null among the texts' => ['{"in": [null, {"var": "name"}]}'];
        yield 'null among the choices' => ['{"in": [null, {"var": "colours"}]}'];
        yield 'leap day of a century' => ['{"==": [{"var": "day"}, "2000-02-29"]}'];
        yield 'in without a variable' => ['{"in": [{"var": "age"}, {"if": [true, ["a"], []]}]}'];
    }

    #[DataProvider('accepted')]
    #[DoesNotPerformAssertions]
    public function testAcceptedLogic(string $rule): void
    {
        new TypeChecker()->check(new JsonLogicReader()->read($rule), $this->schema());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function refused(): iterable
    {
        yield 'unknown variable' => ['{"var": "other"}', 'The logic reads "other", which is not a variable it may read.'];
        yield 'unknown missing' => ['{"missing": ["name", "other"]}', 'The logic reads "other", which is not a variable it may read.'];
        yield 'unknown in a default' => ['{"var": ["name", {"var": "other"}]}', 'The logic reads "other", which is not a variable it may read.'];
        yield 'text with a number' => ['{"==": [{"var": "name"}, 1]}', '"name" is compared with 1, a value it never takes.'];
        yield 'number with a text' => ['{"==": [{"var": "age"}, "18"]}', '"age" is compared with "18", a value it never takes.'];
        yield 'boolean with a number' => ['{"==": [1, {"var": "agreed"}]}', '"agreed" is compared with 1, a value it never takes.'];
        yield 'date not a day' => ['{"==": [{"var": "day"}, "2026-02-30"]}', '"day" is compared with "2026-02-30", a value it never takes.'];
        yield 'date not a date' => ['{"==": [{"var": "day"}, "tomorrow"]}', '"day" is compared with "tomorrow", a value it never takes.'];
        yield 'date with a number' => ['{"==": [{"var": "day"}, 20260101]}', '"day" is compared with 20260101, a value it never takes.'];
        yield 'date with a time' => ['{"==": [{"var": "day"}, "2026-01-01T10:00"]}', '"day" is compared with "2026-01-01T10:00", a value it never takes.'];
        yield 'choice not among them' => ['{"==": [{"var": "team"}, "Red"]}', '"team" is compared with "Red", a value it never takes.'];
        yield 'choice with a number' => ['{"==": [{"var": "team"}, 1]}', '"team" is compared with 1, a value it never takes.'];
        yield 'choices compared' => ['{"==": [{"var": "colours"}, "red"]}', '"colours" is compared with "red", a value it never takes.'];
        yield 'text ordered' => ['{"<": [{"var": "name"}, "b"]}', '"name" is ordered, which is neither a number nor a day.'];
        yield 'choice ordered' => ['{">": ["Blue", {"var": "team"}]}', '"team" is ordered, which is neither a number nor a day.'];
        yield 'ordered against null' => ['{"<": [{"var": "age"}, null]}', '"age" is compared with null, a value it never takes.'];
        yield 'text interval' => ['{"<": ["a", {"var": "name"}, "c"]}', '"name" is ordered, which is neither a number nor a day.'];
        yield 'interval of the wrong type' => ['{"<": [0, {"var": "age"}, "17"]}', '"age" is compared with "17", a value it never takes.'];
        yield 'interval with a null low bound' => ['{"<": [null, {"var": "age"}, 17]}', '"age" is compared with null, a value it never takes.'];
        yield 'interval with a null high bound' => ['{"<": [0, {"var": "age"}, null]}', '"age" is compared with null, a value it never takes.'];
        yield 'date with a prefix' => ['{"==": [{"var": "day"}, "x2026-01-01"]}', '"day" is compared with "x2026-01-01", a value it never takes.'];
        yield 'date with a line break' => ['{"==": [{"var": "day"}, "2026-01-01\\n"]}', '"day" is compared with "2026-01-01\\n", a value it never takes.'];
        yield 'leap day of a common century' => ['{"==": [{"var": "day"}, "2100-02-29"]}', '"day" is compared with "2100-02-29", a value it never takes.'];
        yield 'interval low bound' => ['{"<": ["0", {"var": "age"}, 17]}', '"age" is compared with "0", a value it never takes.'];
        yield 'two types' => ['{"==": [{"var": "name"}, {"var": "age"}]}', '"name" and "age" are compared, which are not of one type that compares.'];
        yield 'two texts ordered' => ['{"<": [{"var": "name"}, {"var": "nickname"}]}', '"name" and "nickname" are compared, which are not of one type that orders.'];
        yield 'look into a number' => ['{"in": [1, {"var": "age"}]}', '"age" is looked into, which holds neither choices nor a text.'];
        yield 'look into a choice' => ['{"in": ["Blue", {"var": "team"}]}', '"team" is looked into, which holds neither choices nor a text.'];
        yield 'unknown choice' => ['{"in": ["green", {"var": "colours"}]}', '"colours" is compared with "green", a value it never takes.'];
        yield 'number in a text' => ['{"in": [1, {"var": "name"}]}', '"name" is compared with 1, a value it never takes.'];
        yield 'choice among wrong values' => ['{"in": [{"var": "team"}, ["Blue", "Red"]]}', '"team" is compared with "Red", a value it never takes.'];
        yield 'arithmetic on a text' => ['{"*": [2, {"var": "name"}]}', '"name" is computed with, which is not a number.'];
        yield 'deep' => ['{"and": [true, {"or": [false, {"!": {"==": [{"var": "team"}, "Red"]}}]}]}', '"team" is compared with "Red", a value it never takes.'];
        yield 'long value' => ['{"==": [{"var": "team"}, "'.str_repeat('é', 81).'"]}', '"team" is compared with "'.str_repeat('é', 79).', a value it never takes.'];
    }

    #[DataProvider('refused')]
    public function testRefusedLogic(string $rule, string $message): void
    {
        $this->expectExceptionObject(InvalidLogic::because($message));

        new TypeChecker()->check(new JsonLogicReader()->read($rule), $this->schema());
    }

    public function testASchemaKnowsItsVariablesByName(): void
    {
        $name = new VariableDefinition('name', VariableType::Text);
        $age = new VariableDefinition('age', VariableType::Number);
        $schema = new Schema([$name]);
        $wider = $schema->with($age);
        $widest = $wider->with(new VariableDefinition('agreed', VariableType::Boolean));

        self::assertSame(['name' => $name], $schema->variables);
        self::assertSame(['name' => $name, 'age' => $age], $wider->variables);
        self::assertSame(['name', 'age', 'agreed'], array_keys($widest->variables));
        self::assertSame($age, $wider->get('age'));
        self::assertNull($schema->get('age'));
        self::assertSame([], new Schema()->variables);

        $this->expectExceptionObject(InvalidLogic::because('The variable "name" is defined twice.'));
        $wider->with($name);
    }

    public function testOnlyChoicesListChoices(): void
    {
        self::assertSame(['a'], new VariableDefinition('x', VariableType::Choices, ['a'])->choices);

        foreach ([[VariableType::Choice, []], [VariableType::Choices, []], [VariableType::Text, ['a']], [VariableType::Number, ['1']]] as [$type, $choices]) {
            try {
                new VariableDefinition('x', $type, $choices);
                self::fail('The choices are checked.');
            } catch (InvalidLogic $exception) {
                self::assertSame('The variable "x" lists its choices only when it is a choice, and then at least one.', $exception->getMessage());
            }
        }

        $this->expectException(InvalidLogic::class);
        new VariableDefinition('1x', VariableType::Text);
    }

    public function testOnlyNumbersAndDaysOrder(): void
    {
        $ordering = array_filter(VariableType::cases(), static fn (VariableType $type): bool => $type->orders());

        self::assertSame([VariableType::Number, VariableType::Date], array_values($ordering));
    }

    public function testTheNamesOfAnExpressionEachOnceInOrder(): void
    {
        $expression = new JsonLogicReader()->read('{"and": [{"==": [{"var": "b"}, {"var": ["a", {"var": "c"}]}]}, {"missing": ["d", "b"]}, {"var": "a"}]}');

        self::assertSame(['b', 'a', 'c', 'd'], new VariableNames()->of($expression));
        self::assertSame([], new VariableNames()->of(new JsonLogicReader()->read('true')));
    }
}
