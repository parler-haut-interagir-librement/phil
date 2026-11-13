<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Model;

use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Name::class)]
#[CoversClass(PartialDate::class)]
#[CoversClass(CslDate::class)]
#[CoversClass(Reference::class)]
#[CoversClass(ItemType::class)]
#[CoversClass(InvalidReference::class)]
final class ModelTest extends TestCase
{
    public function testANameKeepsItsParts(): void
    {
        $name = new Name('Gogh', 'Vincent Willem', 'de', 'van', 'Jr.');

        self::assertSame('Gogh', $name->family);
        self::assertSame('Vincent Willem', $name->given);
        self::assertSame('de', $name->droppingParticle);
        self::assertSame('van', $name->nonDroppingParticle);
        self::assertSame('Jr.', $name->suffix);
        self::assertNull($name->literal);
        self::assertFalse($name->isLiteral());
        self::assertSame('van Gogh', $name->familyName());
        self::assertSame('V. W.', $name->initials());
    }

    public function testInitials(): void
    {
        self::assertSame('J.-P.', new Name('Sartre', 'Jean-Paul')->initials());
        self::assertSame('É. A.', new Name('Zola', "Émile\tAnne")->initials());
        self::assertSame('A.', new Name('Smith', ' A- ')->initials());
        self::assertSame('', new Name('Smith')->initials());
        self::assertSame('Smith', new Name('Smith')->familyName());
    }

    public function testALiteralName(): void
    {
        $name = Name::literal('World Health Organization');

        self::assertTrue($name->isLiteral());
        self::assertSame('World Health Organization', $name->familyName());
    }

    public function testANameNeedsAFamilyOrALiteral(): void
    {
        $this->expectExceptionObject(InvalidReference::because('a name needs a family or a literal'));

        new Name(given: 'Madonna');
    }

    public function testAPartialDate(): void
    {
        self::assertSame([2020], new PartialDate(2020)->parts());
        self::assertSame([2020, 5], new PartialDate(2020, 5)->parts());
        self::assertSame([2020, 5, 31], new PartialDate(2020, 5, 31)->parts());
        self::assertSame([-44, 3, 15], new PartialDate(-44, 3, 15)->parts());
        self::assertSame(4, new PartialDate(2020, season: 4)->season);
        self::assertSame([9999, 12], new PartialDate(9999, 12)->parts());
        self::assertSame([-9999, 1, 1], new PartialDate(-9999, 1, 1)->parts());
        self::assertSame(1, new PartialDate(2020, season: 1)->season);
        self::assertSame([2024, 2, 29], new PartialDate(2024, 2, 29)->parts());
    }

    /**
     * @return iterable<string, array{int, ?int, ?int, ?int, string}>
     */
    public static function impossibleDates(): iterable
    {
        yield 'year zero' => [0, null, null, null, 'the year 0 is outside -9999 to 9999 or is 0'];
        yield 'year too late' => [10000, null, null, null, 'the year 10000 is outside -9999 to 9999 or is 0'];
        yield 'year too early' => [-10000, null, null, null, 'the year -10000 is outside -9999 to 9999 or is 0'];
        yield 'month zero' => [2020, 0, null, null, 'the month 0 is outside 1 to 12'];
        yield 'month thirteen' => [2020, 13, null, null, 'the month 13 is outside 1 to 12'];
        yield 'day without a month' => [2020, null, 1, null, 'the day 1 does not exist in that month'];
        yield 'day out of the month' => [2023, 2, 29, null, 'the day 29 does not exist in that month'];
        yield 'day before the era' => [-1, 2, 30, null, 'the day 30 does not exist in that month'];
        yield 'season and month' => [2020, 1, null, 1, 'a season is 1 to 4, for a year without a month'];
        yield 'season zero' => [2020, null, null, 0, 'a season is 1 to 4, for a year without a month'];
        yield 'season five' => [2020, null, null, 5, 'a season is 1 to 4, for a year without a month'];
    }

    #[DataProvider('impossibleDates')]
    public function testImpossibleDatesAreRefused(int $year, ?int $month, ?int $day, ?int $season, string $problem): void
    {
        $this->expectExceptionObject(InvalidReference::because($problem));

        new PartialDate($year, $month, $day, $season);
    }

    public function testACslDate(): void
    {
        $range = new CslDate(new PartialDate(2019), new PartialDate(2020), true);

        self::assertSame(2019, $range->startYear());
        self::assertSame(2020, $range->end?->year);
        self::assertTrue($range->circa);
        self::assertSame(2020, CslDate::year(2020)->startYear());
        self::assertFalse(CslDate::year(2020)->circa);
        self::assertNull(new CslDate(literal: 'forthcoming')->startYear());
    }

    public function testADateNeedsAStart(): void
    {
        $this->expectExceptionObject(InvalidReference::because('a date needs a start, or a literal alone'));

        new CslDate();
    }

    public function testARangeNeedsAStart(): void
    {
        $this->expectExceptionObject(InvalidReference::because('a date needs a start, or a literal alone'));

        new CslDate(null, new PartialDate(2020), false, 'text');
    }

    public function testAReference(): void
    {
        $author = new Name('Smith', 'John');
        $issued = CslDate::year(2020);
        $reference = new Reference(
            'smith',
            ItemType::Book,
            ['author' => [$author], 'editor' => []],
            ['issued' => $issued],
            ['title' => 'A Book', 'note' => ' ', 'archive_location' => 'Box 1'],
        );

        self::assertSame('smith', $reference->id);
        self::assertSame(ItemType::Book, $reference->type);
        self::assertSame([$author], $reference->names(NameVariable::Author));
        self::assertSame([], $reference->names(NameVariable::Editor));
        self::assertSame(['author' => [$author]], $reference->allNames());
        self::assertSame($issued, $reference->date(DateVariable::Issued));
        self::assertNull($reference->date(DateVariable::Accessed));
        self::assertSame(['issued' => $issued], $reference->allDates());
        self::assertSame('A Book', $reference->title());
        self::assertNull($reference->field('note'));
        self::assertSame(['title' => 'A Book', 'archive_location' => 'Box 1'], $reference->allFields());

        $copy = $reference->withId('other');

        self::assertSame('other', $copy->id);
        self::assertSame($reference->allFields(), $copy->allFields());
        self::assertSame($reference->allNames(), $copy->allNames());
        self::assertSame($reference->allDates(), $copy->allDates());
        self::assertSame(ItemType::Book, $copy->type);
    }

    /**
     * @return iterable<string, array{string, array<string, list<Name>>, array<string, CslDate>, array<string, string>, string}>
     */
    public static function invalidReferences(): iterable
    {
        yield 'empty id' => [' ', [], [], [], 'the id is empty'];
        yield 'unknown name variable' => ['a', ['writer' => []], [], [], '"writer" is not a name variable'];
        yield 'unknown date variable' => ['a', [], ['published' => CslDate::year(2020)], [], '"published" is not a date variable'];
        yield 'field named id' => ['a', [], [], ['id' => 'x'], '"id" is not a variable name'];
        yield 'field named type' => ['a', [], [], ['type' => 'x'], '"type" is not a variable name'];
        yield 'field named as names' => ['a', [], [], ['author' => 'x'], '"author" is not a variable name'];
        yield 'field named as a date' => ['a', [], [], ['issued' => 'x'], '"issued" is not a variable name'];
        yield 'field with a space' => ['a', [], [], ['a b' => 'x'], '"a b" is not a variable name'];
        yield 'field starting with a digit' => ['a', [], [], ['1a' => 'x'], '"1a" is not a variable name'];
        yield 'field with a new line' => ['a', [], [], ["a\n" => 'x'], "\"a\n\" is not a variable name"];
        yield 'field too long' => ['a', [], [], [str_repeat('a', 65) => 'x'], '"'.str_repeat('a', 65).'" is not a variable name'];
    }

    /**
     * @param array<string, list<Name>> $names
     * @param array<string, CslDate>    $dates
     * @param array<string, string>     $fields
     */
    #[DataProvider('invalidReferences')]
    public function testInvalidReferencesAreRefused(string $id, array $names, array $dates, array $fields, string $problem): void
    {
        $this->expectExceptionObject(InvalidReference::because($problem));

        new Reference($id, ItemType::Book, $names, $dates, $fields);
    }

    public function testTheLongestVariableName(): void
    {
        $name = 'a'.str_repeat('-', 63);

        self::assertSame('x', new Reference('a', ItemType::Book, fields: [$name => 'x'])->field($name));
    }

    public function testStandaloneTypes(): void
    {
        $standalone = array_filter(ItemType::cases(), static fn (ItemType $type): bool => $type->isStandalone());

        self::assertSame(
            ['bill', 'book', 'broadcast', 'classic', 'collection', 'dataset', 'document', 'figure', 'graphic', 'legislation', 'manuscript', 'map', 'motion_picture', 'musical_score', 'pamphlet', 'patent', 'performance', 'periodical', 'regulation', 'report', 'software', 'song', 'standard', 'thesis', 'treaty', 'webpage'],
            array_values(array_map(static fn (ItemType $type): string => $type->value, $standalone)),
        );
    }

    public function testTheMessage(): void
    {
        self::assertSame('Invalid reference: a problem.', InvalidReference::because('a problem')->getMessage());
    }
}
