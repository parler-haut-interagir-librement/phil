<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Import;

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Import\ImportLimits;
use Phil\Citations\Import\RisReader;
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

#[CoversClass(RisReader::class)]
#[CoversClass(InvalidImport::class)]
final class RisReaderTest extends TestCase
{
    public function testItReadsARecord(): void
    {
        $ris = "\u{FEFF}TY  - JOUR\r\nAU  - Lee, Ann, Jr.\r\nA1  - Park, Bo\r\nAU  - Acme Corp\r\nAU  - ,\r\nED  - Doe, Jane\r\nA3  - Series, Ed\r\nA4  - Tran, Slator\r\n"
            ."TI  - Hello\r\n  world\r\nT1  - Ignored title\r\nT2  - Science\r\nJA  - Sci.\r\nPY  - 2018/03/04/\r\nVL  - 7\r\nIS  - 2\r\nSP  - 1\r\nEP  - 9\r\n"
            ."PB  - Pub\r\nCY  - Paris\r\nDO  - 10.1/x\r\nUR  - https://example.org\r\nSN  - 1234-5678\r\nAB  - Abstract\r\nN1  - Note\r\nET  - 2\r\nLA  - en\r\nKW  - ignored\r\nER  - \r\n";

        [$reference] = new RisReader()->read($ris);

        self::assertSame('lee2018', $reference->id);
        self::assertSame(ItemType::ArticleJournal, $reference->type);
        self::assertEquals([new Name('Lee', 'Ann', suffix: 'Jr.'), Name::literal('Acme Corp'), new Name('Park', 'Bo')], $reference->names(NameVariable::Author));
        self::assertEquals([new Name('Doe', 'Jane')], $reference->names(NameVariable::Editor));
        self::assertEquals([new Name('Series', 'Ed')], $reference->names(NameVariable::CollectionEditor));
        self::assertEquals([new Name('Tran', 'Slator')], $reference->names(NameVariable::Translator));
        self::assertEquals(new CslDate(new PartialDate(2018, 3, 4)), $reference->date(DateVariable::Issued));
        self::assertSame([
            'title' => 'Hello world',
            'container-title' => 'Science',
            'container-title-short' => 'Sci.',
            'volume' => '7',
            'issue' => '2',
            'publisher' => 'Pub',
            'publisher-place' => 'Paris',
            'DOI' => '10.1/x',
            'URL' => 'https://example.org',
            'abstract' => 'Abstract',
            'note' => 'Note',
            'edition' => '2',
            'language' => 'en',
            'page' => '1-9',
            'ISSN' => '1234-5678',
        ], $reference->allFields());
    }

    public function testIdsAreMadeApart(): void
    {
        $ris = "TY  - BOOK\nAU  - Lee, Ann\nPY  - 2018\nSN  - 978\nSP  - 5\nER  -\n"
            ."TY  - BOOK\nAU  - Lée, Ann\nY1  - 2018\nER  - \n"
            ."TY  - BOOK\nAU  - Lee, Ann\nDA  - 2018/01/01\nER  - \n"
            ."TY  - GEN\nTI  - No author\nER  - \n"
            ."TY  - GEN\nID  - ref5\nER  - \n"
            ."TY  - GEN\nAU  - Lee, Ann\nER  - \n"
            ."TY  - GEN\nAU  - 李, 安\nPY  - 2018\nER  - \n";

        $references = new RisReader()->read($ris);

        self::assertSame(['lee2018', 'lee2018a', 'lee2018b', 'ref4', 'ref5', 'ref6', 'li2018'], array_map(static fn (Reference $reference): string => $reference->id, $references));
        self::assertSame('978', $references[0]->field('ISBN'));
        self::assertSame('5', $references[0]->field('page'));
        self::assertSame(ItemType::Document, $references[3]->type);
    }

    /**
     * @return iterable<string, array{string, ItemType}>
     */
    public static function types(): iterable
    {
        $types = [
            'ABST' => ItemType::ArticleJournal, 'BILL' => ItemType::Bill, 'BLOG' => ItemType::PostWeblog,
            'BOOK' => ItemType::Book, 'CASE' => ItemType::LegalCase, 'CHAP' => ItemType::Chapter,
            'COMP' => ItemType::Software, 'CONF' => ItemType::PaperConference, 'CPAPER' => ItemType::PaperConference,
            'DATA' => ItemType::Dataset, 'DICT' => ItemType::EntryDictionary, 'EBOOK' => ItemType::Book,
            'ECHAP' => ItemType::Chapter, 'EDBOOK' => ItemType::Book, 'EJOUR' => ItemType::ArticleJournal,
            'ELEC' => ItemType::Webpage, 'ENCYC' => ItemType::EntryEncyclopedia, 'FIGURE' => ItemType::Figure,
            'GEN' => ItemType::Document, 'HEAR' => ItemType::Hearing, 'ICOMM' => ItemType::PersonalCommunication,
            'JFULL' => ItemType::Periodical, 'JOUR' => ItemType::ArticleJournal, 'LEGAL' => ItemType::Legislation,
            'MAP' => ItemType::Map, 'MGZN' => ItemType::ArticleMagazine, 'MPCT' => ItemType::MotionPicture,
            'MUSIC' => ItemType::MusicalScore, 'NEWS' => ItemType::ArticleNewspaper, 'PAMP' => ItemType::Pamphlet,
            'PAT' => ItemType::Patent, 'PCOMM' => ItemType::PersonalCommunication, 'RPRT' => ItemType::Report,
            'SER' => ItemType::Book, 'SLIDE' => ItemType::Speech, 'SOUND' => ItemType::Song, 'STAND' => ItemType::Standard,
            'STAT' => ItemType::Legislation, 'THES' => ItemType::Thesis, 'UNPB' => ItemType::Manuscript,
            'VIDEO' => ItemType::MotionPicture, 'WEB' => ItemType::Webpage, 'XYZ' => ItemType::Document,
        ];

        foreach ($types as $tag => $type) {
            yield $tag => [$tag, $type];
        }
    }

    #[DataProvider('types')]
    public function testTypes(string $tag, ItemType $expected): void
    {
        [$reference] = new RisReader()->read("TY  - {$tag}\nER  - \n");

        self::assertSame($expected, $reference->type);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function serials(): iterable
    {
        yield 'journal' => ['JOUR', 'ISSN', 'ISBN'];
        yield 'magazine' => ['MGZN', 'ISSN', 'ISBN'];
        yield 'newspaper' => ['NEWS', 'ISSN', 'ISBN'];
        yield 'periodical' => ['JFULL', 'ISSN', 'ISBN'];
        yield 'book' => ['BOOK', 'ISBN', 'ISSN'];
    }

    #[DataProvider('serials')]
    public function testSerialNumbers(string $type, string $field, string $other): void
    {
        [$reference] = new RisReader()->read("TY  - {$type}\nSN  - 1\nER  - \n");

        self::assertSame('1', $reference->field($field));
        self::assertNull($reference->field($other));
    }

    public function testOtherTagsFillTheSameFields(): void
    {
        $ris = "TY  - CHAP\nT1  - Title\nBT  - Book\nPP  - Place\nN2  - Abstract\nEP  - 12\nER  - \n"
            ."TY  - JOUR\nCT  - Title\nJO  - Journal\nJ2  - J.\nER  - \nTY  - JOUR\nJF  - Full\nER  - \n";

        [$chapter, $article, $full] = new RisReader()->read($ris);

        self::assertSame(['title' => 'Title', 'container-title' => 'Book', 'publisher-place' => 'Place', 'abstract' => 'Abstract', 'page' => '12'], $chapter->allFields());
        self::assertSame(['title' => 'Title', 'container-title' => 'Journal', 'container-title-short' => 'J.'], $article->allFields());
        self::assertSame('Full', $full->field('container-title'));
    }

    public function testTextOutsideRecordsAndBlankLinesAreLeftOut(): void
    {
        $references = new RisReader()->read("Exported by a tool\n\nTY  - BOOK\n\nTI  - T\n   \nER  - \nafter\n");

        self::assertSame('T', $references[0]->title());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'a tag outside a record' => ["TI  - T\n", 1, 'the tag TI is outside a record'];
        yield 'a record inside a record' => ["TY  - BOOK\nTY  - BOOK\n", 2, 'a record starts before the one before it ends'];
        yield 'no end' => ["TY  - BOOK\nTI  - T\n", 3, 'the last record has no ER tag'];
        yield 'an end outside a record' => ["ER  - \n", 1, 'the tag ER is outside a record'];
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFilesAreRefused(string $ris, int $line, string $problem): void
    {
        try {
            new RisReader()->read($ris);
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertSame(\sprintf('The RIS cannot be read at line %d: %s.', $line, $problem), $invalidImport->getMessage());
            self::assertSame($line, $invalidImport->inputLine);
        }
    }

    public function testTheLimits(): void
    {
        $reader = new RisReader(new ImportLimits(maxBytes: 200, maxEntries: 1, maxFieldLength: 5, maxNames: 1));
        $references = $reader->read("TY  - BOOK\nAU  - A\nAU  - B\nTI  - 12345\nER  - \n");

        self::assertCount(1, $references[0]->names(NameVariable::Author));
        $this->assertRefused($reader, str_repeat(' ', 201), 'the file is longer than 200 bytes', 1);
        $this->assertRefused($reader, "TY  - BOOK\nER  - \nTY  - BOOK\nER  - \n", 'the file holds more than 1 records', 3);
        $this->assertRefused($reader, "TY  - BOOK\nTI  - 123456\nER  - \n", 'a value is longer than 5 bytes', 2);
        $this->assertRefused($reader, "TY  - BOOK\nTI  - 123\n456\nER  - \n", 'a value is longer than 5 bytes', 3);
        $this->assertRefused($reader, "TY  - BOOK\nTI  - \xFF\nER  - \n", 'the file is not UTF-8', 1);
    }

    private function assertRefused(RisReader $reader, string $ris, string $problem, int $line): void
    {
        try {
            $reader->read($ris);
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertSame(\sprintf('The RIS cannot be read at line %d: %s.', $line, $problem), $invalidImport->getMessage());
        }
    }
}
