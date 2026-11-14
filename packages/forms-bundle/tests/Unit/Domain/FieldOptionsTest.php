<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\ValueObject\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a field may add to its type: a condition, tiers, disqualifying choices, capacities, sensitivity, and the birth
 * date.
 *
 * @phpstan-import-type FieldArray from Field
 */
#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
final class FieldOptionsTest extends TestCase
{
    public function testAFieldKeepsWhatItUsesOnly(): void
    {
        $field = Field::of(
            'tier',
            'Tier',
            FieldType::Choice,
            choices: ['1', 'Day', 'Week'],
            shownWhen: ['==' => [['var' => 'adult'], true]],
            tiers: ['vip', 'day-pass_2'],
            disqualifying: ['1' => 'Not for one day.'],
            capacities: ['Day' => 10, '1' => 1],
        );
        $sensitive = Field::of('health', 'Health', FieldType::Textarea, sensitive: true);

        self::assertSame([
            'name' => 'tier', 'label' => 'Tier', 'type' => 'choice', 'required' => false, 'choices' => ['1', 'Day', 'Week'], 'max_length' => null,
            'shown_when' => ['==' => [['var' => 'adult'], true]],
            'tiers' => ['vip', 'day-pass_2'],
            'disqualifying' => [1 => 'Not for one day.'],
            'capacities' => ['Day' => 10, 1 => 1],
        ], $field->toArray());
        self::assertSame(['name' => 'health', 'label' => 'Health', 'type' => 'textarea', 'required' => false, 'choices' => [], 'max_length' => 5000, 'sensitive' => true], $sensitive->toArray());
        self::assertEquals($field, Field::fromArray($field->toArray()));
        self::assertEquals($sensitive, Field::fromArray($sensitive->toArray()));
        $decoded = json_decode((string) json_encode($field->toArray()), true);
        self::assertIsArray($decoded);
        /** @var FieldArray $decoded */
        self::assertEquals($field, Field::fromArray($decoded), 'read back from JSON');
        self::assertSame([null, [], [], [], false], (static fn (Field $plain): array => [$plain->shownWhen, $plain->tiers, $plain->disqualifying, $plain->capacities, $plain->sensitive])(Field::of('a', 'A', FieldType::Text)));
        self::assertTrue($sensitive->sensitive);

        self::assertSame(['Not for one day.', null, null, null, null], [$field->disqualification('1'), $field->disqualification('Day'), $field->disqualification(true), $field->disqualification(null), $field->disqualification('2')]);
        self::assertSame([10, 1, null, null, null], [$field->capacity('Day'), $field->capacity('1'), $field->capacity('Week'), $field->capacity(false), $field->capacity(null)]);
        self::assertSame([true, true, true, false, false], [$field->isFor(['vip']), $field->isFor(['x', 'day-pass_2']), $field->isFor(['vip', 'day-pass_2']), $field->isFor([]), $field->isFor(['other'])]);
        self::assertSame([true, true], [$sensitive->isFor([]), $sensitive->isFor(['vip'])]);
        self::assertSame([500, 1_000_000, 130], [Field::MAX_CHOICES, Field::MAX_CAPACITY, Field::MAX_AGE]);
        self::assertCount(500, Field::of('a', 'A', FieldType::Choice, choices: array_map(strval(...), range(1, 500)))->choices);
        self::assertSame([str_repeat('é', 255)], Field::of('a', 'A', FieldType::Choice, choices: [str_repeat('é', 255)])->choices);
        self::assertSame(['a'.str_repeat('b', 63)], Field::of('a', 'A', FieldType::Text, tiers: ['a'.str_repeat('b', 63)])->tiers);
        self::assertSame([1_000_000], array_values(Field::of('a', 'A', FieldType::Choice, choices: ['x'], capacities: ['x' => 1_000_000])->capacities));
        self::assertSame([str_repeat('é', 500)], array_values(Field::of('a', 'A', FieldType::Choice, choices: ['x'], disqualifying: ['x' => str_repeat('é', 500)])->disqualifying));
        self::assertTrue(Field::of('a', 'A', FieldType::Choice, choices: ['x'], sensitive: true)->sensitive);
    }

    public function testADisqualifyingChoiceRefusesTheEntryWithItsCode(): void
    {
        $field = Field::of('age', 'Age', FieldType::Choice, choices: ['Under 18', '18 or over'], disqualifying: ['Under 18' => 'The event is for adults.']);

        self::assertSame(['value' => 'Under 18', 'error' => 'disqualified'], $field->accept(' Under 18 '));
        self::assertSame(['value' => '18 or over', 'error' => null], $field->accept('18 or over'));
        self::assertSame(['value' => 'Other', 'error' => 'choice'], $field->accept('Other'));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function birthDates(): iterable
    {
        yield 'today' => ['2026-11-11', null];
        yield 'tomorrow' => ['2026-11-12', 'birth_date'];
        yield 'next year' => ['2027-01-01', 'birth_date'];
        yield '130 years ago' => ['1896-11-11', null];
        yield 'a day more' => ['1896-11-10', 'birth_date'];
        yield 'no such day' => ['2000-02-30', 'date'];
        yield 'not a date' => ['11/11/2000', 'date'];
    }

    #[DataProvider('birthDates')]
    public function testABirthDateIsADayThatCameAndNotTooLongAgo(string $value, ?string $error): void
    {
        $field = Field::of('born', 'Born', FieldType::BirthDate);

        self::assertSame(['value' => $value, 'error' => $error], $field->accept($value, new DateTimeImmutable('2026-11-11 23:00 UTC')));
        self::assertNull($field->maxLength);
        self::assertSame('birth_date', FieldType::BirthDate->value);
    }

    public function testABirthDateWithoutTheDayIsADate(): void
    {
        $field = Field::of('born', 'Born', FieldType::BirthDate);

        self::assertSame(['value' => '2999-01-01', 'error' => null], $field->accept('2999-01-01'));
        self::assertSame(['value' => '2999-02-30', 'error' => 'date'], $field->accept('2999-02-30'));
    }

    /**
     * @return iterable<string, array{Closure(): Field, string}>
     */
    public static function refused(): iterable
    {
        yield 'too many choices' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: array_map(strval(...), range(1, 501))), 'A field has at most 500 choices: "a".'];
        yield 'an empty choice' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x', ' ']), 'A choice of "a" is 1 to 255 characters.'];
        yield 'a long choice' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: [str_repeat('é', 256)]), 'A choice of "a" is 1 to 255 characters.'];
        yield 'a tier' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, tiers: ['VIP']), 'A tier of "a" is 1 to 64 lower-case letters, digits, "_" or "-"; "VIP" is not.'];
        yield 'a tier starting with -' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, tiers: ['-a']), 'A tier of "a" is 1 to 64 lower-case letters, digits, "_" or "-"; "-a" is not.'];
        yield 'a long tier' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, tiers: ['a'.str_repeat('b', 64)]), 'A tier of "a" is 1 to 64 lower-case letters, digits, "_" or "-"; "a'.str_repeat('b', 64).'" is not.'];
        yield 'a tier and a line' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, tiers: ["vip\n"]), "A tier of \"a\" is 1 to 64 lower-case letters, digits, \"_\" or \"-\"; \"vip\n\" is not."];
        yield 'a tier twice' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, tiers: ['vip', 'vip']), 'The tiers of "a" are each given once.'];
        yield 'disqualifying, not a choice' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], disqualifying: ['y' => 'No.']), 'Only a choice of "a" disqualifies an entry; "y" is not one.'];
        yield 'disqualifying, no message' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], disqualifying: ['x' => ' ']), 'The message of an entry "a" disqualifies is 1 to 500 characters.'];
        yield 'disqualifying, long message' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], disqualifying: ['x' => str_repeat('é', 501)]), 'The message of an entry "a" disqualifies is 1 to 500 characters.'];
        yield 'capacity, not a choice' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], capacities: ['y' => 1]), 'Only a choice of "a" has a capacity; "y" is not one.'];
        yield 'capacity, none' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], capacities: ['x' => 0]), 'A choice of "a" holds 1 to 1000000 places.'];
        yield 'capacity, too many' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], capacities: ['x' => 1_000_001]), 'A choice of "a" holds 1 to 1000000 places.'];
        yield 'sensitive checkbox' => [static fn (): Field => Field::of('a', 'A', FieldType::Checkbox, sensitive: true), 'A sensitive field is neither a checkbox nor a choice with places: "a".'];
        yield 'sensitive places' => [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x'], capacities: ['x' => 1], sensitive: true), 'A sensitive field is neither a checkbox nor a choice with places: "a".'];
    }

    /**
     * @param Closure(): Field $define
     */
    #[DataProvider('refused')]
    public function testADefinitionAgainstTheRulesIsRefused(Closure $define, string $message): void
    {
        $this->expectException(InvalidForm::class);
        $this->expectExceptionMessageIsOrContains($message);

        $define();
    }
}
