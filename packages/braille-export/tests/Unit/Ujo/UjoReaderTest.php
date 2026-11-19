<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Ujo;

use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\ListBlock;
use Phil\BrailleExport\Document\ListItem;
use Phil\BrailleExport\Document\PageBreak;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\Table;
use Phil\BrailleExport\Ujo\UjoReader;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Text\PlainText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoReader::class)]
final class UjoReaderTest extends TestCase
{
    public function testTheStructureOfAUjoDocumentIsKept(): void
    {
        $document = new UjoReader()->read(new Document([
            new Block('a', 'header', ['text' => 'The <b>title</b>', 'level' => 1]),
            new Block('b', 'paragraph', ['text' => 'One<br>two &amp; three']),
            new Block('c', 'list', ['style' => 'ordered', 'meta' => ['start' => 4], 'items' => [
                ['content' => 'first', 'items' => [['content' => 'inner', 'items' => []]]],
                ['content' => 'second', 'items' => []],
                'not an item',
            ]]),
            new Block('d', 'table', ['withHeadings' => true, 'caption' => 'People', 'content' => [['Name', ['text' => '<i>Age</i>']], ['Ann', 12], 'not a row']]),
            new Block('e', 'quote', ['text' => 'Said', 'caption' => 'Someone']),
            new Block('f', 'delimiter', []),
            new Block('g', 'pageBreak', []),
            new Block('h', 'code', ['code' => "first\n\n\nsecond"]),
        ]), 'fr-CA', 'Title');

        self::assertSame('fr-CA', $document->language);
        self::assertSame('Title', $document->title);
        self::assertEquals([
            new Heading('The title', 1),
            new Paragraph("One\ntwo & three"),
            new ListBlock([new ListItem('first', new ListBlock([new ListItem('inner')], true)), new ListItem('second')], true, 4),
            new Table([['Name', 'Age'], ['Ann', ''], []], true, 'People'),
            new Paragraph('Said'),
            new Paragraph('Someone'),
            new PageBreak(),
            new Paragraph('first'),
            new Paragraph('second'),
        ], $document->blocks);
    }

    public function testMissingDataGivesDefaults(): void
    {
        $document = new UjoReader()->read(new Document([
            new Block('a', 'header', ['text' => 'x', 'level' => 9]),
            new Block('b', 'header', ['text' => 'x', 'level' => 0]),
            new Block('c', 'header', ['text' => 'x']),
            new Block('d', 'list', ['style' => 'unordered', 'items' => 'none', 'meta' => ['start' => 'x']]),
            new Block('e', 'list', ['style' => 'ordered', 'meta' => ['start' => -3], 'items' => [['content' => 'x']]]),
            new Block('f', 'list', ['style' => 'ordered', 'meta' => 'x', 'items' => []]),
            new Block('g', 'table', ['content' => 'none']),
            new Block('h', 'unknownType', ['text' => 'x']),
        ]), 'en');

        self::assertEquals([
            new Heading('x', 6),
            new Heading('x', 1),
            new Heading('x', 2),
            new ListBlock([], false, 1),
            new ListBlock([new ListItem('x')], true, 0),
            new ListBlock([], true, 1),
            new Table([], false, ''),
        ], $document->blocks);
        self::assertSame('', $document->title);
    }

    public function testThePlainTextOfOtherBlocksCanBeReplaced(): void
    {
        $text = new PlainText()->with('custom', static fn (): string => 'replaced');
        $document = new UjoReader($text)->read(new Document([new Block('a', 'custom', [])]), 'en');

        self::assertEquals([new Paragraph('replaced')], $document->blocks);
    }

    public function testAParagraphIsOneParagraphWhateverItsLineBreaks(): void
    {
        $document = new UjoReader()->read(new Document([new Block('a', 'paragraph', ['text' => 'one<br><br>two'])]), 'en');

        self::assertEquals([new Paragraph("one\n\ntwo")], $document->blocks);
    }

    public function testAnOrderedListStartsAtOneUnlessItsMetaSaysOtherwise(): void
    {
        $document = new UjoReader()->read(new Document([
            new Block('a', 'list', ['style' => 'ordered', 'meta' => ['counterType' => 'numeric'], 'items' => [['content' => 'x']]]),
        ]), 'en');

        self::assertEquals([new ListBlock([new ListItem('x')], true, 1)], $document->blocks);
    }

    public function testWhiteSpaceAfterTheLastBlankLineIsNoParagraph(): void
    {
        $document = new UjoReader()->read(new Document([new Block('a', 'code', ['code' => "first\n\n  "])]), 'en');

        self::assertEquals([new Paragraph('first')], $document->blocks);
    }
}
