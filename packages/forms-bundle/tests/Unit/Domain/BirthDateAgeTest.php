<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The ages a birth date takes: both bounds included, counted in full years on the reference date or on the day the
 * entry comes, a person born on 29 February a year older on 1 March of a year without that day.
 */
#[CoversClass(Field::class)]
final class BirthDateAgeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function ages(): iterable
    {
        yield 'the birthday' => ['2008-11-12', '2026-11-12', 18];
        yield 'the day before the birthday' => ['2008-11-12', '2026-11-11', 17];
        yield 'the day after the birthday' => ['2008-11-12', '2026-11-13', 18];
        yield 'an earlier month' => ['2008-11-12', '2026-10-30', 17];
        yield 'a later month' => ['2008-01-31', '2026-02-01', 18];
        yield 'born today' => ['2026-11-12', '2026-11-12', 0];
        yield 'the day before the birth' => ['2026-11-12', '2026-11-11', -1];
        yield 'a leap day, on 28 February of a common year' => ['2008-02-29', '2026-02-28', 17];
        yield 'a leap day, on 1 March of a common year' => ['2008-02-29', '2026-03-01', 18];
        yield 'a leap day, on 29 February of a leap year' => ['2008-02-29', '2028-02-29', 20];
        yield 'a leap day, on 28 February of a leap year' => ['2008-02-29', '2028-02-28', 19];
        yield '28 February, in a leap year' => ['2007-02-28', '2028-02-28', 21];
        yield '1 March, on 29 February of a leap year' => ['2007-03-01', '2028-02-29', 20];
    }

    #[DataProvider('ages')]
    public function testTheAgeIsCountedInFullYears(string $born, string $on, int $age): void
    {
        self::assertSame($age, Field::age($born, $on));
    }

    public function testBothBoundsAreIncluded(): void
    {
        $field = Field::of('born', 'Born', FieldType::BirthDate, minAge: 18, maxAge: 25);
        $today = new DateTimeImmutable('2026-11-12 12:00 UTC');

        self::assertNull($field->accept('2008-11-12', $today)['error'], '18 today: accepted');
        self::assertSame('too_young', $field->accept('2008-11-13', $today)['error'], '18 tomorrow');
        self::assertNull($field->accept('2000-11-13', $today)['error'], '25 until tomorrow: accepted');
        self::assertSame('too_old', $field->accept('2000-11-12', $today)['error'], '26 today');
        self::assertSame(['value' => '2008-11-12', 'error' => null], $field->accept(' 2008-11-12 ', $today));
    }

    public function testALeapDayCompletesItsYearOnTheFirstOfMarch(): void
    {
        $field = Field::of('born', 'Born', FieldType::BirthDate, minAge: 18);

        self::assertSame('too_young', $field->accept('2008-02-29', new DateTimeImmutable('2026-02-28 23:00 UTC'))['error']);
        self::assertNull($field->accept('2008-02-29', new DateTimeImmutable('2026-03-01 00:00 UTC'))['error']);
    }

    public function testTheBoundsMayStandAlone(): void
    {
        $today = new DateTimeImmutable('2026-11-12 12:00 UTC');
        $adult = Field::of('born', 'Born', FieldType::BirthDate, minAge: 18);
        $child = Field::of('born', 'Born', FieldType::BirthDate, maxAge: 12);
        $newborn = Field::of('born', 'Born', FieldType::BirthDate, minAge: 0, maxAge: 0);

        self::assertSame([null, null], [$adult->accept('1900-01-01', $today)['error'], $child->accept('2026-11-12', $today)['error']]);
        self::assertSame(['too_young', 'too_old'], [$adult->accept('2026-01-01', $today)['error'], $child->accept('2013-11-12', $today)['error']]);
        self::assertSame([null, 'too_old'], [$newborn->accept('2025-11-13', $today)['error'], $newborn->accept('2025-11-12', $today)['error']]);
    }

    public function testTheAgeIsCountedOnTheReferenceDate(): void
    {
        $field = Field::of('born', 'Born', FieldType::BirthDate, minAge: 18, referenceDate: '2027-06-01');
        $today = new DateTimeImmutable('2026-11-12 12:00 UTC');

        self::assertNull($field->accept('2009-06-01', $today)['error'], '17 today, 18 on the day of the event');
        self::assertSame('too_young', $field->accept('2009-06-02', $today)['error']);
        self::assertNull($field->accept('2009-06-01')['error'], 'without the day the entry comes, the reference date still counts');
        self::assertSame('too_young', $field->accept('2009-06-02')['error']);
        self::assertSame('birth_date', $field->accept('2026-11-13', $today)['error'], 'still not after the day the entry comes');
    }

    public function testWithoutADayToCountOnNoAgeIsChecked(): void
    {
        self::assertNull(Field::of('born', 'Born', FieldType::BirthDate, minAge: 18)->accept('2026-01-01')['error']);
    }

    public function testAnEntryTooYoungOrTooOldIsRefusedWithTheAge(): void
    {
        $form = Form::create('a', 'A', [Field::of('born', 'Born', FieldType::BirthDate, minAge: 18, maxAge: 99)]);
        $at = new DateTimeImmutable('2026-11-12 12:00 UTC');

        self::assertSame(['born' => '2008-11-12'], $form->submit(['born' => '2008-11-12'], $at)->values);

        try {
            $form->submit(['born' => '2010-01-01'], $at);
            self::fail('A child was accepted.');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame(['born' => 'too_young'], $invalidEntry->errors);
        }

        $field = $form->fields()[0];
        self::assertSame([['%age%' => 18], ['%age%' => 99], []], [$field->errorParameters('too_young'), $field->errorParameters('too_old'), $field->errorParameters('required')]);
        $plain = Field::of('born', 'Born', FieldType::BirthDate);
        self::assertSame([[], []], [$plain->errorParameters('too_young'), $plain->errorParameters('too_old')]);
    }

    public function testTheAgesAreKeptInTheDefinition(): void
    {
        $field = Field::of('born', 'Born', FieldType::BirthDate, minAge: 18, maxAge: 30, referenceDate: '2027-06-01');

        self::assertSame(['name' => 'born', 'label' => 'Born', 'type' => 'birth_date', 'required' => false, 'choices' => [], 'max_length' => null, 'min_age' => 18, 'max_age' => 30, 'reference_date' => '2027-06-01'], $field->toArray());
        self::assertEquals($field, Field::fromArray($field->toArray()));
        self::assertSame([18, 30, '2027-06-01'], [$field->minAge, $field->maxAge, $field->referenceDate]);
        self::assertSame(['name' => 'born', 'label' => 'Born', 'type' => 'birth_date', 'required' => false, 'choices' => [], 'max_length' => null, 'min_age' => 0], Field::of('born', 'Born', FieldType::BirthDate, minAge: 0)->toArray());
        self::assertSame([null, null, null], (static fn (Field $plain): array => [$plain->minAge, $plain->maxAge, $plain->referenceDate])(Field::of('born', 'Born', FieldType::BirthDate)));
        self::assertSame([0, 130], [Field::of('born', 'Born', FieldType::BirthDate, minAge: 0, maxAge: 130)->minAge, Field::of('born', 'Born', FieldType::BirthDate, minAge: 0, maxAge: 130)->maxAge]);
        self::assertSame(7, Field::of('born', 'Born', FieldType::BirthDate, minAge: 7, maxAge: 7)->maxAge);
    }

    /**
     * @return iterable<string, array{Closure(): Field, string}>
     */
    public static function refused(): iterable
    {
        yield 'an age on a date' => [static fn (): Field => Field::of('a', 'A', FieldType::Date, minAge: 18), 'Only a birth date has ages: "a".'];
        yield 'an oldest age on text' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, maxAge: 18), 'Only a birth date has ages: "a".'];
        yield 'a reference date on text' => [static fn (): Field => Field::of('a', 'A', FieldType::Text, referenceDate: '2027-01-01'), 'Only a birth date has ages: "a".'];
        yield 'a negative youngest age' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, minAge: -1), 'An age of "a" is 0 to 130 years.'];
        yield 'a negative oldest age' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, maxAge: -1), 'An age of "a" is 0 to 130 years.'];
        yield 'a youngest age too old' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, minAge: 131), 'An age of "a" is 0 to 130 years.'];
        yield 'an oldest age too old' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, maxAge: 131), 'An age of "a" is 0 to 130 years.'];
        yield 'the youngest above the oldest' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, minAge: 19, maxAge: 18), 'The youngest age of "a" is not above its oldest: 19 > 18.'];
        yield 'a reference date alone' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, referenceDate: '2027-01-01'), 'A reference date goes with an age: "a".'];
        yield 'a reference date that is not a date' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, minAge: 18, referenceDate: '2027-02-30'), 'The reference date of "a" is a date, YYYY-MM-DD; "2027-02-30" is not.'];
        yield 'a reference date and a line' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, minAge: 18, referenceDate: "2027-01-01\n"), "The reference date of \"a\" is a date, YYYY-MM-DD; \"2027-01-01\n\" is not."];
        yield 'a reference date in another format' => [static fn (): Field => Field::of('a', 'A', FieldType::BirthDate, maxAge: 18, referenceDate: '01/06/2027'), 'The reference date of "a" is a date, YYYY-MM-DD; "01/06/2027" is not.'];
    }

    /**
     * @param Closure(): Field $define
     */
    #[DataProvider('refused')]
    public function testAgesAgainstTheRulesAreRefused(Closure $define, string $message): void
    {
        $this->expectException(InvalidForm::class);
        $this->expectExceptionMessageIsOrContains($message);

        $define();
    }
}
