<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\FormsBundle;

use Phil\FormLogic\FormsBundle\JsonLogicConditions;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\ValueObject\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonLogicConditions::class)]
final class JsonLogicConditionsTest extends TestCase
{
    /**
     * @return array<string, Field>
     */
    private function fields(): array
    {
        $fields = [
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
            Field::of('agreed', 'Agreed', FieldType::Checkbox),
            Field::of('arrival', 'Arrival', FieldType::Date),
            Field::of('born', 'Born', FieldType::BirthDate),
            Field::of('email', 'Email', FieldType::Email),
            Field::of('note', 'Note', FieldType::Textarea),
        ];

        return array_combine(array_map(static fn (Field $field): string => $field->name, $fields), $fields);
    }

    public function testTheFieldsAConditionReads(): void
    {
        $condition = ['and' => [['in' => [['var' => 'team'], ['Blue']]], ['!' => [['missing' => ['email', 'team']]]]]];

        self::assertSame(['team', 'email'], new JsonLogicConditions()->fields($condition));
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>}>
     */
    public static function accepted(): iterable
    {
        yield 'choice' => [['==' => [['var' => 'team'], 'Blue']]];
        yield 'checkbox' => [['==' => [['var' => 'agreed'], true]]];
        yield 'date' => [['<' => ['2026-01-01', ['var' => 'arrival'], '2026-12-31']]];
        yield 'birth date' => [['<=' => [['var' => 'born'], '2008-11-12']]];
        yield 'text' => [['in' => ['@example.org', ['var' => 'email']]]];
        yield 'textarea' => [['!!' => [['var' => 'note']]]];
    }

    /**
     * @param array<array-key, mixed> $condition
     */
    #[DataProvider('accepted')]
    #[DoesNotPerformAssertions]
    public function testConditionsCheckedAgainstTheTypesOfTheFields(array $condition): void
    {
        new JsonLogicConditions()->check($condition, $this->fields());
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>, 1: string}>
     */
    public static function refused(): iterable
    {
        yield 'unknown field' => [['==' => [['var' => 'other'], 'x']], 'A condition is refused: The logic reads "other", which is not a variable it may read.'];
        yield 'not a choice' => [['==' => [['var' => 'team'], 'Red']], 'A condition is refused: "team" is compared with "Red", a value it never takes.'];
        yield 'checkbox with a text' => [['==' => [['var' => 'agreed'], 'yes']], 'A condition is refused: "agreed" is compared with "yes", a value it never takes.'];
        yield 'date with a text' => [['==' => [['var' => 'arrival'], 'soon']], 'A condition is refused: "arrival" is compared with "soon", a value it never takes.'];
        yield 'birth date with a text' => [['==' => [['var' => 'born'], 'soon']], 'A condition is refused: "born" is compared with "soon", a value it never takes.'];
        yield 'text ordered' => [['<' => [['var' => 'email'], 'b']], 'A condition is refused: "email" is ordered, which is neither a number nor a day.'];
        yield 'textarea with a number' => [['==' => [['var' => 'note'], 1]], 'A condition is refused: "note" is compared with 1, a value it never takes.'];
    }

    /**
     * @param array<array-key, mixed> $condition
     */
    #[DataProvider('refused')]
    public function testRefusedConditions(array $condition, string $message): void
    {
        $this->expectExceptionObject(InvalidForm::because($message));

        new JsonLogicConditions()->check($condition, $this->fields());
    }

    public function testAConditionOutsideTheSubsetIsAnInvalidForm(): void
    {
        $evaluator = new JsonLogicConditions();

        foreach (['fields', 'check', 'holds'] as $method) {
            try {
                match ($method) {
                    'fields' => $evaluator->fields(['cat' => ['a']]),
                    'check' => $evaluator->check(['cat' => ['a']], []),
                    default => $evaluator->holds(['cat' => ['a']], []),
                };
                self::fail($method);
            } catch (InvalidForm $exception) {
                self::assertStringStartsWith('A condition is refused: An object of a rule is one operation of the subset: ', $exception->getMessage());
            }
        }
    }

    public function testAConditionIsOneOperation(): void
    {
        $this->expectExceptionObject(InvalidForm::because('A condition is one operation, such as {"==": [{"var": "team"}, "Blue"]}.'));

        new JsonLogicConditions()->fields([]);
    }

    public function testAConditionHoldsForTheValues(): void
    {
        $evaluator = new JsonLogicConditions();
        $condition = ['or' => [['==' => [['var' => 'team'], 'Blue']], ['and' => [['var' => 'agreed'], ['!' => [['missing' => ['email']]]]]]]];

        self::assertTrue($evaluator->holds($condition, ['team' => 'Blue']));
        self::assertFalse($evaluator->holds($condition, ['team' => 'Green', 'agreed' => true, 'email' => null]));
        self::assertTrue($evaluator->holds($condition, ['team' => 'Green', 'agreed' => true, 'email' => 'a@example.org']));
        self::assertFalse($evaluator->holds($condition, []));
    }

    public function testNotEqualHoldsForAFieldLeftEmptyAsUnderTheBasicConditions(): void
    {
        $condition = ['!=' => [['var' => 'team'], 'Blue']];
        $presence = ['and' => [['!!' => [['var' => 'team']]], $condition]];

        self::assertSame([true, true, false], [new JsonLogicConditions()->holds($condition, ['team' => null]), new JsonLogicConditions()->holds($condition, []), new JsonLogicConditions()->holds($condition, ['team' => 'Blue'])]);
        self::assertSame([true, false], [new BasicConditions()->holds($condition, ['team' => null]), new BasicConditions()->holds($condition, ['team' => 'Blue'])], 'the bundle reads it as JsonLogic does');
        self::assertSame([false, true], [new JsonLogicConditions()->holds($presence, ['team' => null]), new JsonLogicConditions()->holds($presence, ['team' => 'Green'])], 'the presence of the field written out');
    }
}
