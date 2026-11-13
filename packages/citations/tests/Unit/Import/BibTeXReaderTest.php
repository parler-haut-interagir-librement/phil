<?php

declare(strict_types=1);

namespace Phil\Citations\Tests\Unit\Import;

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Import\BibTeXReader;
use Phil\Citations\Import\ImportLimits;
use Phil\Citations\Internal\BibTeXParser;
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

#[CoversClass(BibTeXReader::class)]
#[CoversClass(BibTeXParser::class)]
#[CoversClass(ImportLimits::class)]
#[CoversClass(InvalidImport::class)]
final class BibTeXReaderTest extends TestCase
{
    public function testItReadsAnArticle(): void
    {
        $bibtex = <<<'BIB'
            % a comment line
            @String{jnl = "Journal of Tests"}
            @STRING(short = {JT})
            @preamble{"\newcommand{\noop}[1]{}"}
            @comment{ignored @article{nope, title = {No}} }
            @Article{smith2020,
              Author = {Smith, John A. and Jean-Paul Dupont},
              title = {On {T}esting Caf\'e},
              subtitle = {A Study},
              journal = jnl # " Quarterly",
              year = 2020, month = may, day = {4},
              volume = 12, number = {3}, pages = {45--67},
              doi = {10.1000/xyz}, url = "https://example.org/a",
              keywords = {ignored},
              title = {Second title ignored},
            }
            BIB;

        [$reference] = new BibTeXReader()->read($bibtex);

        self::assertSame('smith2020', $reference->id);
        self::assertSame(ItemType::ArticleJournal, $reference->type);
        self::assertEquals([new Name('Smith', 'John A.'), new Name('Dupont', 'Jean-Paul')], $reference->names(NameVariable::Author));
        self::assertEquals(new CslDate(new PartialDate(2020, 5, 4)), $reference->date(DateVariable::Issued));
        self::assertSame([
            'title' => 'On Testing Café: A Study',
            'container-title' => 'Journal of Tests Quarterly',
            'volume' => '12',
            'page' => '45-67',
            'DOI' => '10.1000/xyz',
            'URL' => 'https://example.org/a',
            'issue' => '3',
        ], $reference->allFields());
    }

    public function testCrossrefFillsWhatTheChildLacks(): void
    {
        $bibtex = <<<'BIB'
            @incollection{chap, author = {Ludwig van Beethoven}, title = {Symphonies}, crossref = {Coll}, pages = {1-10}}
            @book{coll, editor = {Doe, Jane and Roe, Richard}, title = {Collected Works}, publisher = {Pub}, year = 2019, address = {Paris}}
            @inproceedings{lost, title = {Alone}, crossref = {missing}}
            @inproceedings{untitled, title = {Paper}, crossref = {notitle}}
            @proceedings{notitle, year = 2001}
            BIB;

        [$chapter, $collection, $lost, $untitled] = new BibTeXReader()->read($bibtex);

        self::assertSame(ItemType::Chapter, $chapter->type);
        self::assertSame('Collected Works', $chapter->field('container-title'));
        self::assertSame('Symphonies', $chapter->title());
        self::assertSame('Pub', $chapter->field('publisher'));
        self::assertSame('Paris', $chapter->field('publisher-place'));
        self::assertEquals([new Name('Doe', 'Jane'), new Name('Roe', 'Richard')], $chapter->names(NameVariable::Editor));
        self::assertSame(2019, $chapter->date(DateVariable::Issued)?->startYear());
        self::assertSame(ItemType::Book, $collection->type);
        self::assertNull($collection->field('container-title'));
        self::assertNull($lost->field('container-title'));
        self::assertNull($untitled->field('container-title'));
        self::assertSame(2001, $untitled->date(DateVariable::Issued)?->startYear());
    }

    /**
     * @return iterable<string, array{string, ItemType}>
     */
    public static function types(): iterable
    {
        yield 'article' => ['article', ItemType::ArticleJournal];
        yield 'book' => ['book', ItemType::Book];
        yield 'booklet' => ['booklet', ItemType::Pamphlet];
        yield 'collection' => ['collection', ItemType::Book];
        yield 'conference' => ['conference', ItemType::PaperConference];
        yield 'dataset' => ['dataset', ItemType::Dataset];
        yield 'electronic' => ['electronic', ItemType::Webpage];
        yield 'inbook' => ['inbook', ItemType::Chapter];
        yield 'incollection' => ['incollection', ItemType::Chapter];
        yield 'inproceedings' => ['inproceedings', ItemType::PaperConference];
        yield 'manual' => ['manual', ItemType::Report];
        yield 'mastersthesis' => ['mastersthesis', ItemType::Thesis];
        yield 'misc' => ['misc', ItemType::Document];
        yield 'online' => ['online', ItemType::Webpage];
        yield 'patent' => ['patent', ItemType::Patent];
        yield 'phdthesis' => ['phdthesis', ItemType::Thesis];
        yield 'proceedings' => ['proceedings', ItemType::Book];
        yield 'report' => ['report', ItemType::Report];
        yield 'software' => ['software', ItemType::Software];
        yield 'techreport' => ['techreport', ItemType::Report];
        yield 'thesis' => ['thesis', ItemType::Thesis];
        yield 'unpublished' => ['unpublished', ItemType::Manuscript];
        yield 'www' => ['www', ItemType::Webpage];
        yield 'unknown' => ['custom', ItemType::Document];
    }

    #[DataProvider('types')]
    public function testTypes(string $type, ItemType $expected): void
    {
        [$reference] = new BibTeXReader()->read('@'.$type.'{key, title = {T}}');

        self::assertSame($expected, $reference->type);
    }

    public function testFields(): void
    {
        $bibtex = <<<'BIB'
            @phdthesis{t,
              author = {A. Student}, editor = {B. Editor}, translator = {C. Translator},
              title = {Thesis}, school = {University}, institution = {Institute},
              location = {Montréal}, edition = {2}, series = {Series}, isbn = {978-0}, issn = {1234-5678},
              note = {A note}, abstract = {An abstract}, chapter = {4}, language = {french},
              howpublished = {Printed}, number = {12}, urldate = {2021-01-02}, date = {2020-05/2020-06}
            }
            @mastersthesis{m, title = {M}, type = {Mémoire}, organization = {Org}, journaltitle = {J}, booktitle = {B}}
            BIB;

        [$thesis, $masters] = new BibTeXReader()->read($bibtex);

        self::assertSame([
            'title' => 'Thesis',
            'publisher-place' => 'Montréal',
            'edition' => '2',
            'collection-title' => 'Series',
            'ISBN' => '978-0',
            'ISSN' => '1234-5678',
            'note' => 'A note',
            'abstract' => 'An abstract',
            'chapter-number' => '4',
            'language' => 'french',
            'medium' => 'Printed',
            'publisher' => 'University',
            'number' => '12',
            'genre' => 'PhD thesis',
        ], $thesis->allFields());
        self::assertEquals([new Name('Editor', 'B.')], $thesis->names(NameVariable::Editor));
        self::assertEquals([new Name('Translator', 'C.')], $thesis->names(NameVariable::Translator));
        self::assertEquals(new CslDate(new PartialDate(2021, 1, 2)), $thesis->date(DateVariable::Accessed));
        self::assertEquals(new CslDate(new PartialDate(2020, 5), new PartialDate(2020, 6)), $thesis->date(DateVariable::Issued));
        self::assertSame(['title' => 'M', 'genre' => 'Mémoire', 'container-title' => 'J', 'publisher' => 'Org'], $masters->allFields());
    }

    /**
     * @return iterable<string, array{string, ?CslDate}>
     */
    public static function years(): iterable
    {
        yield 'a year' => ['year = 1984', CslDate::year(1984)];
        yield 'a year with a letter' => ['year = {1984a}', CslDate::year(1984)];
        yield 'a year before the era' => ['year = {-44}', CslDate::year(-44)];
        yield 'five digits' => ['year = {19845}', new CslDate(literal: '19845')];
        yield 'text' => ['year = {forthcoming}', new CslDate(literal: 'forthcoming')];
        yield 'year zero' => ['year = {0}', new CslDate(literal: '0')];
        yield 'a month by number' => ['year = 2020, month = {12}', new CslDate(new PartialDate(2020, 12))];
        yield 'a month by name' => ['year = 2020, month = {September}', new CslDate(new PartialDate(2020, 9))];
        yield 'a month macro' => ['year = 2020, month = jan', new CslDate(new PartialDate(2020, 1))];
        yield 'a month out of range' => ['year = 2020, month = {13}', CslDate::year(2020)];
        yield 'month zero' => ['year = 2020, month = {0}', CslDate::year(2020)];
        yield 'an unknown month' => ['year = 2020, month = {Brumaire}', CslDate::year(2020)];
        yield 'a day without a month' => ['year = 2020, day = 3', CslDate::year(2020)];
        yield 'a day that does not exist' => ['year = 2021, month = feb, day = 30', CslDate::year(2021)];
        yield 'no year' => ['month = may', null];
    }

    #[DataProvider('years')]
    public function testYears(string $fields, ?CslDate $expected): void
    {
        [$reference] = new BibTeXReader()->read('@book{key, '.$fields.'}');

        self::assertEquals($expected, $reference->date(DateVariable::Issued));
    }

    public function testSyntaxVariants(): void
    {
        $bibtex = "junk @ email@example.org @misc(paren, title = \"A {\"}quoted{\"} \\& co\"\n) @misc{nofields} @misc{ spaced ,title={x}}\n@misc{same, title = {First}, Title = {Second}}";

        $references = new BibTeXReader()->read($bibtex);

        self::assertSame(['paren', 'nofields', 'spaced', 'same'], array_map(static fn (Reference $reference): string => $reference->id, $references));
        self::assertSame('A "quoted" & co', $references[0]->title());
        self::assertSame('First', $references[3]->title());
    }

    public function testAMacroDefinedWithAMacro(): void
    {
        $bibtex = '@string{a = "x"} @string{b = a # a # {y}} @misc{k, title = b # 2020}';

        self::assertSame('xxy2020', new BibTeXReader()->read($bibtex)[0]->title());
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'no key' => ['@misc{, title = {x}}', 1, 'an entry has no key'];
        yield 'no equals' => ["@misc{k,\n title {x}}", 2, '"=" is expected'];
        yield 'no field name' => ['@misc{k, = {x}}', 1, 'a field name is expected'];
        yield 'an undefined macro' => ['@misc{k, title = nope}', 1, 'the macro "nope" is not defined'];
        yield 'no value' => ['@misc{k, title = }', 1, 'a value is expected'];
        yield 'an unclosed brace' => ["@misc{k, title = {x\n\n", 3, 'a value opened with { is not closed'];
        yield 'an unclosed quote' => ['@misc{k, title = "x', 1, 'a value opened with " is not closed'];
        yield 'an extra closing brace' => ['@misc{k, title = "x}"}', 1, 'a closing brace has no opening one'];
        yield 'an unclosed entry' => ['@misc{k, title = {x}', 1, '"}" is expected'];
        yield 'a wrong closing delimiter' => ['@misc(k, title = {x}}', 1, '")" is expected'];
        yield 'a macro without a name' => ['@string{ = "x"}', 1, 'a macro name is expected'];
        yield 'an unclosed comment' => ['@comment{ never closed', 1, 'a value opened with { is not closed'];
        yield 'a backslash at the end' => ['@misc{k, title = {x\\', 1, 'a value opened with { is not closed'];
    }

    #[DataProvider('invalidFiles')]
    public function testInvalidFilesAreRefused(string $bibtex, int $line, string $problem): void
    {
        try {
            new BibTeXReader()->read($bibtex);
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertSame(\sprintf('The BibTeX cannot be read at line %d: %s.', $line, $problem), $invalidImport->getMessage());
            self::assertSame($line, $invalidImport->inputLine);
        }
    }

    public function testTheLimits(): void
    {
        $limits = new ImportLimits(maxBytes: 100, maxEntries: 2, maxFieldLength: 7, maxDepth: 2, maxNames: 1);
        $reader = new BibTeXReader($limits);

        $references = $reader->read('@misc{a, title = {{{x}}}, author = {A and B}} @misc{b, title = "1234567"}');
        self::assertCount(2, $references);
        self::assertCount(1, $references[0]->names(NameVariable::Author));

        $this->assertRefused($reader, str_repeat(' ', 101), 'the file is longer than 100 bytes');
        $this->assertRefused($reader, '@misc{a} @misc{b} @misc{c}', 'the file holds more than 2 entries');
        $this->assertRefused($reader, '@misc{a, title = "12345678"}', 'a value is longer than 7 bytes');
        $this->assertRefused($reader, '@misc{a, title = "12" # "345678"}', 'a value is longer than 7 bytes');
        $this->assertRefused($reader, '@misc{a, title = {{{{x}}}}}', 'braces are nested deeper than 2');
        $this->assertRefused($reader, "\xFF", 'the file is not UTF-8');
    }

    public function testTheDefaultLimits(): void
    {
        $limits = new ImportLimits();

        self::assertSame(5_000_000, $limits->maxBytes);
        self::assertSame(10_000, $limits->maxEntries);
        self::assertSame(65_536, $limits->maxFieldLength);
        self::assertSame(32, $limits->maxDepth);
        self::assertSame(1_000, $limits->maxNames);
    }

    private function assertRefused(BibTeXReader $reader, string $bibtex, string $problem): void
    {
        try {
            $reader->read($bibtex);
            self::fail('The file was read.');
        } catch (InvalidImport $invalidImport) {
            self::assertStringEndsWith($problem.'.', $invalidImport->getMessage());
        }
    }
}
