<?php

declare(strict_types=1);

namespace Phil\Markup\Tests\Unit\Reader;

use Phil\Markup\Reader\Markdown\BaseDialect;
use Phil\Markup\Reader\Markdown\BlockParser;
use Phil\Markup\Reader\Markdown\MarkdownReader;
use Phil\Markup\Reader\Markdown\Statblock;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An HTML block of comments alone is the `note` tune of the block after it, or of the last block when none follows,
 * as the HTML reader keeps the comments of a page: the speaker notes of a Marp deck are read as they are written.
 */
#[CoversClass(MarkdownReader::class)]
#[CoversClass(BlockParser::class)]
#[CoversClass(Statblock::class)]
final class MarkdownNotesTest extends TestCase
{
    public function testTheSpeakerNotesOfAMarpDeckAreNotes(): void
    {
        $deck = <<<'MD'
            ---
            marp: true
            theme: default
            ---

            # First slide

            Some text.

            <!-- What to say on the first slide -->

            ---

            # Second slide

            - one
            - two

            <!--
            Two lines
              of notes
            -->
            MD;
        $reading = new MarkdownReader()->read($deck);

        self::assertSame(['marp' => true, 'theme' => 'default'], $reading->metadata);
        self::assertSame([
            ['header', []],
            ['paragraph', []],
            ['delimiter', ['note' => ['text' => 'What to say on the first slide']]],
            ['header', []],
            ['list', ['note' => ['text' => "Two lines\n  of notes"]]],
        ], $this->blocks($reading->document->blocks));
    }

    /**
     * @return iterable<string, array{string, list<array{string, array<string, mixed>}>}>
     */
    public static function comments(): iterable
    {
        yield 'before a paragraph' => ["<!-- n -->\n\na", [['paragraph', ['note' => ['text' => 'n']]]]];
        yield 'right before a paragraph' => ["<!-- n -->\na", [['paragraph', ['note' => ['text' => 'n']]]]];
        yield 'between paragraphs' => ["a\n\n<!-- n -->\n\nb", [['paragraph', []], ['paragraph', ['note' => ['text' => 'n']]]]];
        yield 'interrupting a paragraph' => ["a\n<!-- n -->\nb", [['paragraph', []], ['paragraph', ['note' => ['text' => 'n']]]]];
        yield 'at the end' => ["a\n\nb\n\n<!-- n -->", [['paragraph', []], ['paragraph', ['note' => ['text' => 'n']]]]];
        yield 'several, one line each' => ["<!-- a -->\n<!-- b -->\n\n# T", [['header', ['note' => ['text' => "a\nb"]]]]];
        yield 'several on one line' => ["<!-- a --> <!-- b -->\n\n# T", [['header', ['note' => ['text' => "a\nb"]]]]];
        yield 'before and at the end' => ["<!-- a -->\n\nx\n\n<!-- b -->", [['paragraph', ['note' => ['text' => "a\nb"]]]]];
        yield 'empty' => ["<!-- -->\n<!---->\n\na", [['paragraph', []]]];
        yield 'empty and not' => ["<!-- -->\n<!-- n -->\n\na", [['paragraph', ['note' => ['text' => 'n']]]]];
        yield 'before code' => ["<!-- n -->\n```\nx\n```", [['code', ['note' => ['text' => 'n']]]]];
        yield 'alone' => ['<!-- n -->', []];
        yield 'inside a paragraph' => ['a <!-- n --> b', [['paragraph', []]]];
    }

    /**
     * @param list<array{string, array<string, mixed>}> $blocks
     */
    #[DataProvider('comments')]
    public function testCommentsAreTheNoteOfTheNextBlock(string $source, array $blocks): void
    {
        self::assertSame($blocks, $this->blocks(new MarkdownReader()->read($source)->document->blocks));
        self::assertSame($blocks, $this->blocks(new MarkdownReader(rawHtml: true)->read($source)->document->blocks), 'raw HTML kept');
    }

    /**
     * Comments that are notes are not shown as text; HTML blocks that hold other things than comments are read as
     * before: escaped as text, or left out where raw HTML is kept.
     */
    public function testANoteIsNotShownAsText(): void
    {
        $escaped = new MarkdownReader()->read("<!-- n --> x\n\n<!-- m -->\n\na")->document->blocks;

        self::assertSame('&lt;!-- n --&gt; x', $escaped[0]->data['text'] ?? null);
        self::assertSame('a', $escaped[1]->data['text'] ?? null);
        self::assertCount(2, $escaped);
        self::assertSame('a', new MarkdownReader(rawHtml: true)->read("<!-- n --> x\n\n<!-- m -->\n\na")->document->blocks[0]->data['text'] ?? null);
        self::assertSame(['a', '&lt;!-- n'], array_map(static fn (Block $block): mixed => $block->data['text'] ?? null, new MarkdownReader()->read("a\n\n<!-- n")->document->blocks));
        self::assertSame([[]], array_map(static fn (Block $block): array => $block->tunes, new MarkdownReader(rawHtml: true)->read("a\n\n<!-- n")->document->blocks));
    }

    /**
     * The note joins the one a dialect gives the block, after it, the block's other tunes kept, and is cut to 10,000 characters.
     */
    public function testANoteJoinsTheNoteOfTheBlockAndIsCut(): void
    {
        $dialect = new class extends BaseDialect {
            public function paragraph(string $text): array
            {
                return str_starts_with($text, '!') ? [substr($text, 1), ['note' => ['text' => 'given'], 'alignment' => ['alignment' => 'center']]] : [$text, []];
            }
        };
        $reader = new MarkdownReader(dialect: $dialect);

        self::assertSame(['note' => ['text' => "given\nn"], 'alignment' => ['alignment' => 'center']], $reader->read("<!-- n -->\n!a")->document->blocks[0]->tunes);
        self::assertSame(['note' => ['text' => "given\nn"], 'alignment' => ['alignment' => 'center']], $reader->read("!a\n\n<!-- n -->")->document->blocks[0]->tunes);

        $note = new MarkdownReader()->read('<!--'.str_repeat('é', 10_001)."-->\n\na")->document->blocks[0]->tunes['note'] ?? null;
        self::assertSame(['text' => str_repeat('é', 10_000)], $note);
    }

    /**
     * Comments inside a container are the notes of its blocks; a container whose blocks are folded into its text, as
     * a quote is, does not keep them.
     */
    public function testCommentsInsideAContainerStayInIt(): void
    {
        $blocks = new MarkdownReader()->read(":::note\n<!-- inside -->\nx\n:::\n\n> <!-- q -->\n> y")->document->blocks;
        $inner = $blocks[0]->data['blocks'] ?? null;

        self::assertIsArray($inner);
        self::assertInstanceOf(Block::class, $inner[0]);
        self::assertSame(['note' => ['text' => 'inside']], $inner[0]->tunes);
        self::assertSame([], $blocks[0]->tunes);
        self::assertSame(['quote', []], [$blocks[1]->type, $blocks[1]->tunes]);
    }

    /**
     * Definition lists that follow each other are one list, its items in order; a block between two starts a new one.
     */
    public function testDefinitionListsThatFollowEachOtherAreJoined(): void
    {
        $blocks = new MarkdownReader()->read("a\n: 1\n\nb\n: 2\n\nc\n: 3\n\np\n\nd\n: 4\n\ne\n: 5")->document->blocks;

        self::assertSame(['definitionList', 'paragraph', 'definitionList'], array_map(static fn (Block $block): string => $block->type, $blocks));
        self::assertSame(['a', 'b', 'c'], $this->terms($blocks[0]));
        self::assertSame(['d', 'e'], $this->terms($blocks[2]));
        self::assertSame(['f'], $this->terms(new MarkdownReader()->read("f\n: 6")->document->blocks[0]));
    }

    /**
     * The blocks of a stat block's section stay in order, its definitions joined into its properties, whatever comes
     * between them.
     */
    public function testTheSectionsOfAStatBlockKeepTheirBlocksInOrder(): void
    {
        $block = new MarkdownReader()->read("{{monster\n# Name\n\n___\n\na\n\nb\n\nHP\n: 10\n\nc\n\nAC\n: 12\n\n## Actions\n\nd\n}}")->document->blocks[0];
        $sections = $block->data['sections'] ?? null;

        self::assertIsArray($sections);
        self::assertCount(2, $sections);
        [$first, $second] = $sections;
        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertIsArray($first['blocks'] ?? null);
        self::assertIsArray($first['properties'] ?? null);
        self::assertSame(['a', 'b', 'c'], array_map(static fn (mixed $part): mixed => $part instanceof Block ? $part->data['text'] ?? null : null, $first['blocks']));
        self::assertSame(['HP', 'AC'], array_column($first['properties'], 'label'));
        self::assertSame('Actions', $second['title'] ?? null);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<array{string, array<string, mixed>}>
     */
    private function blocks(array $blocks): array
    {
        return array_map(static fn (Block $block): array => [$block->type, array_diff_key($block->tunes, ['anchor' => true])], $blocks);
    }

    /**
     * @return list<mixed>
     */
    private function terms(Block $block): array
    {
        return array_map(static fn (mixed $item): mixed => \is_array($item) ? $item['term'] ?? null : null, BlockParser::items($block));
    }
}
