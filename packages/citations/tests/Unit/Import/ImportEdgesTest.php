<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Import;

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Import\BibTeXReader;
use Phil\Citations\Import\ImportLimits;
use Phil\Citations\Import\RisReader;
use Phil\Citations\Internal\BibTeXNames;
use Phil\Citations\Internal\BibTeXParser;
use Phil\Citations\Model\CslDate;
use Phil\Citations\Model\DateVariable;
use Phil\Citations\Model\Name;
use Phil\Citations\Model\NameVariable;
use Phil\Citations\Model\PartialDate;
use Phil\Citations\Model\Reference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(BibTeXReader::class)]
#[CoversClass(BibTeXParser::class)]
#[CoversClass(BibTeXNames::class)]
#[CoversClass(RisReader::class)]
#[CoversClass(ImportLimits::class)]
final class ImportEdgesTest extends TestCase
{
    public function testABibTeXFileOfExactlyTheLongestSizeIsRead(): void
    {
        $reader = new BibTeXReader(new ImportLimits(maxBytes: 20));

        self::assertCount(1, $reader->read(str_pad('@misc{a}', 20)));

        try {
            $reader->read(str_pad('@misc{a}', 21));
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertSame(1, $invalidImport->inputLine);
        }
    }

    public function testABibTeXFileThatIsNotUtf8IsRefusedAtItsFirstLine(): void
    {
        try {
            new BibTeXReader()->read("@misc{a}\n\xFF");
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertSame(1, $invalidImport->inputLine);
        }
    }

    public function testCrossrefKeysAreCaseInsensitiveAndTheFirstEntryOfAKeyWins(): void
    {
        $bibtex = '@book{Coll, title = {First}, publisher = {P1}} @book{COLL, title = {Second}, publisher = {P2}} @incollection{chap, title = {C}, crossref = {cOLL}}';

        $chapter = new BibTeXReader()->read($bibtex)[2];

        self::assertSame('P1', $chapter->field('publisher'));
        self::assertSame('First', $chapter->field('container-title'));
    }

    /**
     * @return iterable<string, array{string, ?CslDate}>
     */
    public static function months(): iterable
    {
        yield 'January by number' => ['month = {1}', new CslDate(new PartialDate(2020, 1))];
        yield 'December by number' => ['month = {12}', new CslDate(new PartialDate(2020, 12))];
        yield 'a month beyond December' => ['month = {13}, day = 5', CslDate::year(2020)];
    }

    #[DataProvider('months')]
    public function testMonths(string $fields, CslDate $expected): void
    {
        [$reference] = new BibTeXReader()->read('@book{key, year = 2020, '.$fields.'}');

        self::assertEquals($expected, $reference->date(DateVariable::Issued));
        self::assertSame($expected->start?->month, $reference->date(DateVariable::Issued)?->start?->month);
    }

    public function testSpacesAroundTheParts(): void
    {
        $bibtex = '@misc {k , title = {x} , year = 2020 } @string{ Jnl = "Journal"} @misc{j, journal = JNL # " A", title = jnl}';

        [$first, $second] = new BibTeXReader()->read($bibtex);

        self::assertSame('2020', (string) $first->date(DateVariable::Issued)?->start?->year);
        self::assertSame('Journal A', $second->field('container-title'));
        self::assertSame('Journal', $second->title());
    }

    public function testATrailingCommaBeforeTheClosingBraceIsAllowed(): void
    {
        [$reference] = new BibTeXReader()->read("@misc{k, title = {x},\n  }");

        self::assertSame('x', $reference->title());
    }

    public function testABackslashEscapesTheNextCharacter(): void
    {
        [$first, $second] = new BibTeXReader()->read('@misc{a, title = {x\}y}} @misc{b, title = {\}}, year = 2020}');

        self::assertSame('x}y', $first->title());
        self::assertSame('}', $second->title());
    }

    /**
     * @return iterable<string, array{string, list<Name>}>
     */
    public static function names(): iterable
    {
        yield 'a lowercase last word is the family' => ['Smith de', [new Name('de', 'Smith')]];
        yield 'a lowercase last word of the family part' => ['Berg de, Jan', [new Name('Berg de', 'Jan')]];
        yield 'a lowercase letter with an accent' => ['Jan élan Vries', [new Name('Vries', 'Jan', null, 'élan')]];
        yield 'a titlecase letter is not lowercase' => ["Jan \u{1C5}udo Vries", [new Name('Vries', "Jan \u{1C5}udo")]];
    }

    /**
     * @param list<Name> $expected
     */
    #[DataProvider('names')]
    public function testNames(string $raw, array $expected): void
    {
        self::assertEquals($expected, BibTeXNames::parse($raw, 10));
    }

    public function testARisFileOfExactlyTheLongestSizeIsRead(): void
    {
        $reader = new RisReader(new ImportLimits(maxBytes: 30));

        self::assertCount(1, $reader->read(str_pad("TY  - BOOK\nER  - \n", 30)));

        $this->expectException(InvalidImport::class);

        $reader->read(str_pad("TY  - BOOK\nER  - \n", 31));
    }

    public function testLinesEndedByCarriageReturnsAndLineFeedsAreCountedOnce(): void
    {
        $reader = new RisReader(new ImportLimits(maxFieldLength: 5));

        try {
            $reader->read("TY  - BOOK\r\nTI  - 123456\r\nER  - \r\n");
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertSame(2, $invalidImport->inputLine);
        }

        self::assertSame('T', $reader->read("TY  - BOOK\rTI  - T\rER  - \r")[0]->title());
    }

    public function testATagInTheMiddleOfALineIsText(): void
    {
        [$reference] = new RisReader()->read("TY  - BOOK\nTI  -   Title  \nsee AB  - text\nER  - \n");

        self::assertSame('Title see AB  - text', $reference->title());
        self::assertNull($reference->field('abstract'));
    }

    public function testTheIssuedDateComesFromPyThenY1ThenDa(): void
    {
        $references = new RisReader()->read("TY  - BOOK\nPY  - 2001\nY1  - 2002\nDA  - 2003\nER  - \nTY  - BOOK\nY1  - 2002\nDA  - 2003\nER  - \nTY  - BOOK\nDA  - 2003\nER  - \n");

        self::assertSame([2001, 2002, 2003], array_map(static fn (Reference $reference): ?int => $reference->date(DateVariable::Issued)?->startYear(), $references));
    }

    public function testTheIdTagGivesTheId(): void
    {
        $references = new RisReader()->read("TY  - BOOK\nID  - custom\nAU  - Lee, Ann\nPY  - 2018\nER  - \nTY  - BOOK\nID  - \n  spread\nER  - \n");

        self::assertSame(['custom', 'spread'], array_map(static fn (Reference $reference): string => $reference->id, $references));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, ?string, ?string}>
     */
    public static function risNames(): iterable
    {
        yield 'a suffix with more commas after it' => ['Lee, Ann, Jr., extra', 'Lee', 'Ann', 'Jr., extra', null];
        yield 'a family and a comma' => ['Lee,', 'Lee', null, null, null];
        yield 'a given name alone after a comma' => [', Ann', null, null, null, 'Ann'];
        yield 'a family, no given name, a suffix' => ['Lee, , Jr.', 'Lee', null, 'Jr.', null];
    }

    #[DataProvider('risNames')]
    public function testRisNames(string $value, ?string $family, ?string $given, ?string $suffix, ?string $literal): void
    {
        [$reference] = new RisReader()->read("TY  - BOOK\nAU  - ".$value."\nER  - \n");
        [$name] = $reference->names(NameVariable::Author);

        self::assertSame($family, $name->family);
        self::assertSame($given, $name->given);
        self::assertSame($suffix, $name->suffix);
        self::assertSame($literal, $name->literal);
    }
}
