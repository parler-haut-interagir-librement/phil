<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit;

use Phil\Citations\CslJson;
use Phil\Citations\Exception\InvalidReference;
use Phil\Citations\Internal\DateText;
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

#[CoversClass(CslJson::class)]
#[CoversClass(DateText::class)]
final class CslJsonTest extends TestCase
{
    private const string ITEM = <<<'JSON'
        [{
            "id": "smith2020",
            "type": "article-journal",
            "author": [
                {"family": "Gogh", "given": "Vincent", "non-dropping-particle": "van", "dropping-particle": "de", "suffix": "Jr.", "parse-names": true},
                {"literal": "World Health Organization"},
                "Plato",
                {"family": " ", "literal": "Org"}
            ],
            "editor": "nobody",
            "issued": {"date-parts": [["2020", 5, "1"], [2020, 6]], "circa": "true"},
            "accessed": {"raw": "2021-01-02"},
            "original-date": {"literal": "Spring term"},
            "submitted": {"date-parts": [[2019, 14]]},
            "title": "On Testing",
            "volume": 12,
            "page": 1.5,
            "custom": {"a": 1},
            "keyword": ["x"],
            "DOI": "10.1000/xyz"
        }]
        JSON;

    public function testItReadsAnItem(): void
    {
        [$reference] = new CslJson()->decode(self::ITEM);

        self::assertSame('smith2020', $reference->id);
        self::assertSame(ItemType::ArticleJournal, $reference->type);
        self::assertEquals(
            [
                new Name('Gogh', 'Vincent', 'de', 'van', 'Jr.'),
                Name::literal('World Health Organization'),
                Name::literal('Plato'),
                Name::literal('Org'),
            ],
            $reference->names(NameVariable::Author),
        );
        self::assertSame([], $reference->names(NameVariable::Editor));
        self::assertEquals(new CslDate(new PartialDate(2020, 5, 1), new PartialDate(2020, 6), true), $reference->date(DateVariable::Issued));
        self::assertEquals(new CslDate(new PartialDate(2021, 1, 2)), $reference->date(DateVariable::Accessed));
        self::assertEquals(new CslDate(literal: 'Spring term'), $reference->date(DateVariable::OriginalDate));
        self::assertEquals(new CslDate(new PartialDate(2019, season: 2)), $reference->date(DateVariable::Submitted));
        self::assertSame(['title' => 'On Testing', 'volume' => '12', 'page' => '1.5', 'DOI' => '10.1000/xyz'], $reference->allFields());
    }

    public function testItRoundTrips(): void
    {
        $json = new CslJson();
        $references = $json->decode(self::ITEM);
        $encoded = $json->encode($references);

        self::assertEquals($references, $json->decode($encoded));
        self::assertStringContainsString('"non-dropping-particle": "van"', $encoded);
        self::assertStringContainsString('"circa": true', $encoded);
        self::assertStringContainsString('"literal": "Spring term"', $encoded);
        self::assertStringContainsString('"season": 2', $encoded);
        self::assertStringContainsString('"DOI": "10.1000/xyz"', $encoded);
        self::assertStringNotContainsString('\/', $encoded);
        self::assertStringStartsWith("[\n    {\n        \"id\": \"smith2020\",\n        \"type\": \"article-journal\",", $encoded);
    }

    public function testItEncodesNothingAsAnEmptyList(): void
    {
        self::assertSame('[]', new CslJson()->encode([]));
    }

    public function testItWritesUnicodeAsItIs(): void
    {
        $encoded = new CslJson()->encode([new Reference('a', ItemType::Book, fields: ['title' => 'Café'])]);

        self::assertStringContainsString('"title": "Café"', $encoded);
    }

    public function testASingleItemIsAccepted(): void
    {
        $references = new CslJson()->decode('{"id": 7, "type": "book", "title": "Seven"}');

        self::assertSame('7', $references[0]->id);
        self::assertSame([0], array_keys($references));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawDates(): iterable
    {
        yield 'a year' => ['2020', '2020'];
        yield 'a month' => ['2020-05', '2020-5'];
        yield 'a day' => ['2020-05-01', '2020-5-1'];
        yield 'a range' => ['2019/2020', '2019 to 2020'];
        yield 'a range of days' => ['2019-01-02/2019-03-04', '2019-1-2 to 2019-3-4'];
        yield 'slashes' => ['2020/05/01/', '2020-5-1'];
        yield 'slashes without a day' => ['2020/05', '2020-5'];
        yield 'a year before the era' => ['-44', '-44'];
        yield 'text' => ['circa 1900', 'literal circa 1900'];
        yield 'a date that does not exist' => ['2020-02-30', 'literal 2020-02-30'];
        yield 'a second part that is text' => ['2020/later', 'literal 2020/later'];
        yield 'too many digits' => ['20201', 'literal 20201'];
        yield 'a new line' => ["2020\n", '2020'];
    }

    #[DataProvider('rawDates')]
    public function testRawDates(string $raw, string $expected): void
    {
        $date = DateText::parse($raw);
        $describe = static fn (?PartialDate $date): string => $date instanceof PartialDate ? implode('-', $date->parts()) : '';
        $text = null !== $date->literal ? 'literal '.$date->literal : $describe($date->start).($date->end instanceof PartialDate ? ' to '.$describe($date->end) : '');

        self::assertSame($expected, $text);
        self::assertFalse($date->circa);
    }

    public function testARawDateKeepsItsCirca(): void
    {
        self::assertTrue(DateText::parse('2020', true)->circa);
        self::assertTrue(DateText::parse('around', true)->circa);
    }

    public function testAMonthThatIsNotANumberIsLeftOut(): void
    {
        [$reference] = new CslJson()->decode('[{"id": "a", "type": "book", "issued": {"date-parts": [[2020, 1e48, "x"]]}}]');

        self::assertEquals(CslDate::year(2020), $reference->date(DateVariable::Issued));
    }

    public function testADateWithoutPartsOrRawIsItsLiteral(): void
    {
        [$reference] = new CslJson()->decode('[{"id": "a", "type": "book", "issued": {"literal": "n.d.", "circa": 1}}]');

        self::assertEquals(new CslDate(null, null, true, 'n.d.'), $reference->date(DateVariable::Issued));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'not JSON' => ['[', 'Invalid reference: the CSL-JSON is not JSON: Syntax error'];
        yield 'too deep' => [str_repeat('[', 17).str_repeat(']', 17), 'Invalid reference: the CSL-JSON is not JSON: Maximum stack depth exceeded'];
        yield 'a string' => ['"text"', 'Invalid reference: the CSL-JSON is not a list of items.'];
        yield 'an item that is not an object' => ['[1]', 'Invalid reference: an item of the CSL-JSON is not an object.'];
        yield 'no id' => ['[{"type": "book"}]', 'Invalid reference: an item has no id.'];
        yield 'an id that is not text' => ['[{"id": true, "type": "book"}]', 'Invalid reference: an item has no id.'];
        yield 'no type' => ['[{"id": "a"}]', 'Invalid reference: the item "a" has no type of CSL 1.0.2.'];
        yield 'a type that is not text' => ['[{"id": "a", "type": 1}]', 'Invalid reference: the item "a" has no type of CSL 1.0.2.'];
        yield 'an unknown type' => ['[{"id": "a", "type": "tweet"}]', 'Invalid reference: the item "a" has no type of CSL 1.0.2.'];
        yield 'a name that is a number' => ['[{"id": "a", "type": "book", "author": [1]}]', 'Invalid reference: a name is not an object.'];
        yield 'a date that is text' => ['[{"id": "a", "type": "book", "issued": "2020"}]', 'Invalid reference: a date is not an object.'];
        yield 'date parts without a year' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [["x"]]}}]', 'Invalid reference: the date-parts of a date do not start with a year.'];
        yield 'date parts that are not a list' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [2020]}}]', 'Invalid reference: the date-parts of a date do not start with a year.'];
        yield 'a variable named by a number' => ['[{"id": "a", "type": "book", "5": "x"}]', 'Invalid reference: "5" is not a variable name.'];
        yield 'a huge year' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [[1e48]]}}]', 'Invalid reference: the date-parts of a date do not start with a year.'];
        yield 'a year of six digits' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [["123456"]]}}]', 'Invalid reference: the date-parts of a date do not start with a year.'];
        yield 'a year beyond five digits' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [[100000]]}}]', 'Invalid reference: the date-parts of a date do not start with a year.'];
        yield 'a year of five digits' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [[-99999]]}}]', 'Invalid reference: the year -99999 is outside -9999 to 9999 or is 0.'];
        yield 'a year of five digits as text' => ['[{"id": "a", "type": "book", "issued": {"date-parts": [["99999"]]}}]', 'Invalid reference: the year 99999 is outside -9999 to 9999 or is 0.'];
        yield 'an empty date' => ['[{"id": "a", "type": "book", "issued": {"date-parts": []}}]', 'Invalid reference: a date needs a start, or a literal alone.'];
    }

    #[DataProvider('invalidJson')]
    public function testInvalidJsonIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidReference::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CslJson()->decode($json);
    }

    public function testTheSizeIsBounded(): void
    {
        $json = new CslJson(maxBytes: 10);

        self::assertCount(0, $json->decode('[        ]'));

        $this->expectExceptionObject(InvalidReference::because('the CSL-JSON is longer than 10 bytes'));

        $json->decode('[         ]');
    }

    public function testTheItemsAreBounded(): void
    {
        $json = new CslJson(maxItems: 1);

        self::assertCount(1, $json->decode('[{"id": "a", "type": "book"}]'));

        $this->expectExceptionObject(InvalidReference::because('the CSL-JSON holds more than 1 items'));

        $json->decode('[{"id": "a", "type": "book"}, {"id": "b", "type": "book"}]');
    }

    public function testTheDefaultsAcceptALargeFile(): void
    {
        $items = array_fill(0, 10_000, ['id' => 'a', 'type' => 'book']);

        self::assertCount(10_000, new CslJson()->decode(json_encode($items, \JSON_THROW_ON_ERROR)));
    }
}
