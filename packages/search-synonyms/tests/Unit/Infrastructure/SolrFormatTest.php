<?php

declare(strict_types=1);

namespace Phil\SearchSynonyms\Tests\Unit\Infrastructure;

use Phil\SearchSynonyms\Domain\Exception\InvalidSynonyms;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymList;
use Phil\SearchSynonyms\Domain\ValueObject\SynonymSet;
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsReader;
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SolrSynonymsReader::class)]
#[CoversClass(SolrSynonymsWriter::class)]
final class SolrFormatTest extends TestCase
{
    public function testAFileIsReadRuleByRule(): void
    {
        $list = new SolrSynonymsReader()->read("\u{FEFF}# Jeux\r\n\r\njdr, Jeu de rôle\n  donjon => donjon,dungeon  \r  # encore\na\\, b, c\\=>d, e\\\\f, g\\h\n\\#hash, tag\nx=y, z\n", 'fr-CA');

        self::assertSame('fr', $list->language);
        self::assertSame([
            [['jdr', 'jeu de rôle'], ['jdr', 'jeu de rôle'], true],
            [['donjon'], ['donjon', 'dungeon'], false],
            [['a, b', 'c=>d', 'e\\f', 'gh'], ['a, b', 'c=>d', 'e\\f', 'gh'], true],
            [['#hash', 'tag'], ['#hash', 'tag'], true],
            [['x=y', 'z'], ['x=y', 'z'], true],
        ], array_map(static fn (SynonymSet $set): array => [$set->from, $set->to, $set->equivalent], $list->sets));
        self::assertSame([], new SolrSynonymsReader()->read('', 'en')->sets);
        self::assertSame([], new SolrSynonymsReader()->read("# only\n\n", 'en')->sets);
        self::assertCount(1, new SolrSynonymsReader()->read(str_repeat(' ', 5000)."a, b\n", 'en')->sets, 'a line is measured once trimmed');
        self::assertCount(1, new SolrSynonymsReader()->read('a, '.str_repeat('b', 98)."\n", 'en')->sets);
    }

    public function testALineThatIsNoRuleIsRefusedWithItsNumber(): void
    {
        foreach ([
            ["a, b\nc", 2, 'Line 2: equivalent synonyms are two phrases or more.'],
            ["a, b\n\na,, b", 3, 'Line 3: a phrase is empty: two commas, or a comma or "=>" with nothing beside it.'],
            ['a, b,', 1, 'Line 1: a phrase is empty: two commas, or a comma or "=>" with nothing beside it.'],
            ['=> b', 1, 'Line 1: a phrase is empty: two commas, or a comma or "=>" with nothing beside it.'],
            ['a =>', 1, 'Line 1: a phrase is empty: two commas, or a comma or "=>" with nothing beside it.'],
            ['a => b => c', 1, 'Line 1: a rule has one "=>" at most.'],
            ['a => A', 1, 'Line 1: one-way synonyms change something: the two sides are the same.'],
            ['a, b\\', 1, 'Line 1: a backslash escapes the character after it, and there is none.'],
            ['a, '.str_repeat('b', 4095), 1, 'Line 1: a line is 4096 bytes at most.'],
        ] as [$text, $line, $message]) {
            try {
                new SolrSynonymsReader()->read($text, 'fr');
                self::fail($message);
            } catch (InvalidSynonyms $invalidSynonyms) {
                self::assertSame([$message, $line], [$invalidSynonyms->getMessage(), $invalidSynonyms->fileLine]);
            }
        }

        foreach ([
            [str_repeat("a, b\n", 10_001), 'Line 10001: a file holds 10000 rules at most.'],
            [str_repeat('#', 1_048_577), 'A synonyms file is 1048576 bytes at most.'],
            ["a, b\xFF", 'A synonyms file is valid UTF-8.'],
            ['a, b', 'A language is a tag such as "fr" or "fr-CA"; "?" is not.'],
        ] as [$text, $message]) {
            try {
                new SolrSynonymsReader()->read($text, str_starts_with($message, 'A language') ? '?' : 'fr');
                self::fail($message);
            } catch (InvalidSynonyms $invalidSynonyms) {
                self::assertSame($message, $invalidSynonyms->getMessage());
            }
        }

        self::assertCount(10_000, new SolrSynonymsReader()->read(str_repeat("a, b\n", 10_000), 'fr')->sets);
        self::assertSame([], new SolrSynonymsReader()->read(str_repeat('#', 1_048_576), 'fr')->sets);
    }

    public function testWhatIsWrittenIsReadBackAsTheSameRules(): void
    {
        $list = SynonymList::of('fr', [
            SynonymSet::equivalent(['jdr', 'jeu de rôle']),
            SynonymSet::oneWay(['donjon'], ['donjon', 'dungeon']),
            SynonymSet::equivalent(['a, b', 'c=>d', 'e\\f', '#hash', 'x#y']),
        ]);
        $text = new SolrSynonymsWriter()->write($list);

        self::assertSame("# Synonyms, language fr\njdr, jeu de rôle\ndonjon => donjon, dungeon\n\\#hash, a\\, b, c\\=>d, e\\\\f, x#y\n", str_replace('a\\, b, c\\=>d, e\\\\f, \\#hash, x#y', '\\#hash, a\\, b, c\\=>d, e\\\\f, x#y', $text));
        self::assertEquals($list, new SolrSynonymsReader()->read($text, 'fr'));
        self::assertSame("# Synonyms, language en\n", new SolrSynonymsWriter()->write(SynonymList::of('en')));
    }

    public function testALineOfExactlyTheLimitIsRead(): void
    {
        $phrases = [];

        for ($i = 0; $i < 40; ++$i) {
            $phrases[] = \sprintf('%02d', $i).str_repeat('x', 96);
        }

        $phrases[] = '40'.str_repeat('x', 94);
        $line = implode(', ', $phrases);

        self::assertSame(SolrSynonymsReader::MAX_LINE, \strlen($line));
        self::assertCount(41, new SolrSynonymsReader()->read($line, 'en')->sets[0]->from);
    }

    public function testALineBreakIsOneLine(): void
    {
        foreach ([["a, b\r\nc", 2], ["a, b\r\n\r\nc", 3], ["a, b\rc", 2], ["a, b\nc", 2]] as [$text, $line]) {
            try {
                new SolrSynonymsReader()->read($text, 'fr');
                self::fail('The line was read.');
            } catch (InvalidSynonyms $invalidSynonyms) {
                self::assertSame($line, $invalidSynonyms->fileLine);
            }
        }
    }

    public function testAPhraseOfSpacesIsEmpty(): void
    {
        $this->expectException(InvalidSynonyms::class);
        $this->expectExceptionMessageIsOrContains('Line 1: a phrase is empty: two commas, or a comma or "=>" with nothing beside it.');
        new SolrSynonymsReader()->read('jdr, , rpg', 'fr');
    }
}
