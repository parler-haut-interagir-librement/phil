<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\CslJson;
use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Internal\DateText;
use Phil\Citations\Internal\LatexText;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\ItemType;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;
use Phil\Citations\ReferenceList;
use Phil\Citations\RichText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CslJson::class)]
#[CoversClass(DateText::class)]
#[CoversClass(LatexText::class)]
#[CoversClass(PartialDate::class)]
#[CoversClass(Reference::class)]
#[CoversClass(ReferenceList::class)]
#[CoversClass(RichText::class)]
final class EdgesTest extends TestCase
{
    public function testTheDefaultSizeLimitIsFiveMillionBytes(): void
    {
        $json = new CslJson();

        self::assertSame([], $json->decode('['.str_repeat(' ', 4_999_998).']'));

        $this->expectExceptionObject(InvalidReference::because('the CSL-JSON is longer than 5000000 bytes'));

        $json->decode('['.str_repeat(' ', 4_999_999).']');
    }

    public function testTheDefaultItemLimitIsTenThousand(): void
    {
        $this->expectExceptionObject(InvalidReference::because('the CSL-JSON holds more than 10000 items'));

        new CslJson()->decode(json_encode(array_fill(0, 10_001, ['id' => 'a', 'type' => 'book']), \JSON_THROW_ON_ERROR));
    }

    public function testTheNestingLimitIsSixteen(): void
    {
        $json = new CslJson();

        try {
            $json->decode(str_repeat('[', 15).str_repeat(']', 15));
            self::fail('The JSON was read.');
        } catch (InvalidReference $invalidReference) {
            self::assertSame('Invalid reference: an item has no id.', $invalidReference->getMessage());
        }

        $this->expectExceptionObject(InvalidReference::because('the CSL-JSON is not JSON: Maximum stack depth exceeded'));

        $json->decode(str_repeat('[', 16).str_repeat(']', 16));
    }

    public function testNamesGivenAsAnObjectBecomeAList(): void
    {
        [$reference] = new CslJson()->decode('[{"id": "a", "type": "book", "author": {"first": {"family": "X"}, "second": {"family": "Y"}}}]');

        self::assertSame([0, 1], array_keys($reference->names(NameVariable::Author)));
    }

    public function testThePartsOfANameAreTrimmed(): void
    {
        [$reference] = new CslJson()->decode('[{"id": "a", "type": "book", "author": [{"family": " Gogh ", "given": "  V  "}, {"family": "  ", "literal": "Org"}]}]');
        [$person, $organisation] = $reference->names(NameVariable::Author);

        self::assertSame('Gogh', $person->family);
        self::assertSame('V', $person->given);
        self::assertNull($organisation->family);
        self::assertSame('Org', $organisation->literal);
    }

    public function testDecemberIsAMonthAndNotASeason(): void
    {
        [$reference] = new CslJson()->decode('[{"id": "a", "type": "book", "issued": {"date-parts": [[2020, 12], [2021, "5\n"]]}}]');
        $date = $reference->date(DateVariable::Issued);

        self::assertSame(12, $date?->start?->month);
        self::assertNull($date->start->season);
        self::assertNull($date->end?->month);
    }

    public function testADateOfTextHasTheLiteralWithoutItsBlanks(): void
    {
        self::assertSame('circa 1900', DateText::parse(" circa 1900\t")->literal);
        self::assertSame('2019/2020/2021', DateText::parse('2019/2020/2021')->literal);
        self::assertEquals(new CslDate(new PartialDate(2019), new PartialDate(2020)), DateText::parse('2019 / 2020'));
    }

    public function testAccentsOnLettersOfFourBytesAndBracesAroundMoreThanOneLetter(): void
    {
        self::assertSame("😀\u{302}", LatexText::plain('\\^😀'));
        self::assertSame('áb', LatexText::plain("\\'{ab}"));
    }

    public function testAQuoteIsClosedAfterThePunctuationOnlyWhenTheTermsSaySo(): void
    {
        self::assertSame('“Title”.', RichText::text('“Title”')->sentence('”')->toText());
        self::assertSame('“Title”,', RichText::text('“Title”')->punctuate(',', '”')->toText());
        self::assertSame('“Title.”', RichText::text('“Title”')->sentence('”', true)->toText());
        self::assertSame('.”', RichText::text('.”')->punctuate('.', '”', true)->toText());
        self::assertSame('<i>A</i>Title.”', RichText::concat(RichText::italic('A'), 'Title”')->punctuate('.', '”', true)->toHtml());
    }

    public function testADayOfFebruaryBeforeTheCommonEraFollowsALeapYear(): void
    {
        self::assertSame([-44, 2, 29], new PartialDate(-44, 2, 29)->parts());
    }

    public function testAVariableNamedByANumberIsRefused(): void
    {
        $refused = static function (callable $make): string {
            try {
                $make();
            } catch (InvalidReference $invalidReference) {
                return $invalidReference->getMessage();
            }

            return '';
        };

        self::assertSame('Invalid reference: "5" is not a name variable.', $refused(static fn (): Reference => new Reference('a', ItemType::Book, names: [5 => [new Name('X')]])));
        self::assertSame('Invalid reference: "5" is not a date variable.', $refused(static fn (): Reference => new Reference('a', ItemType::Book, dates: [5 => CslDate::year(2020)])));
        self::assertSame('Invalid reference: "5" is not a variable name.', $refused(static fn (): Reference => new Reference('a', ItemType::Book, fields: [5 => 'x'])));
    }

    public function testDuplicatesFoundByDoiIgnoreBlanks(): void
    {
        $list = new ReferenceList([
            self::book('a', 'One', doi: ' 10.1/x '),
            self::book('b', 'Two', doi: '10.1/x'),
        ]);

        self::assertSame('a', $list->get('b')->id);
    }

    /**
     * @return iterable<string, array{list<Reference>, string}>
     */
    public static function keysThatAreNotAlike(): iterable
    {
        yield 'a DOI that looks like the key of a work' => [[self::book('a', 'One', doi: 'work:cafe||'), self::book('b', 'Cafe')], 'b'];
        yield 'a DOI that looks like the key of an id' => [[self::book('a', 'One', doi: 'id:c'), self::book('cdoi:', '')], 'cdoi:'];
        yield 'an id that looks like the key of a work' => [[self::book('work:cafe||', ''), self::book('c', 'Cafe')], 'c'];
        yield 'an id that looks like the key of a DOI' => [[self::book('a', 'One', doi: 'qid:'), self::book('doi:q', '')], 'doi:q'];
    }

    /**
     * @param list<Reference> $references
     */
    #[DataProvider('keysThatAreNotAlike')]
    public function testTheKeysOfDifferentKindsNeverCollide(array $references, string $id): void
    {
        self::assertSame($id, new ReferenceList($references)->get($id)->id);
    }

    private static function book(string $id, string $title, ?string $doi = null): Reference
    {
        return new Reference($id, ItemType::Book, [], [], array_filter(['title' => $title, 'DOI' => (string) $doi], static fn (string $value): bool => '' !== $value));
    }
}
