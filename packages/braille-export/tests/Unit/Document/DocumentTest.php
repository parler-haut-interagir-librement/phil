<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Tests\Unit\Document;

use InvalidArgumentException;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\ListBlock;
use Phil\BrailleExport\Document\ListItem;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\PrintPage;
use Phil\BrailleExport\Document\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[CoversClass(Heading::class)]
#[CoversClass(ListBlock::class)]
#[CoversClass(ListItem::class)]
#[CoversClass(Paragraph::class)]
#[CoversClass(PrintPage::class)]
#[CoversClass(Table::class)]
final class DocumentTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $document = new Document([]);
        $list = new ListBlock([new ListItem('a')]);
        $table = new Table([['a']]);

        self::assertSame('en', $document->language);
        self::assertSame('', $document->title);
        self::assertSame(1, new Heading('a')->level);
        self::assertFalse($list->ordered);
        self::assertSame(1, $list->start);
        self::assertNull($list->items[0]->children);
        self::assertTrue($table->headerRow);
        self::assertSame('', $table->caption);
        self::assertSame('iv', new PrintPage('iv')->number);
        self::assertSame('a', new Paragraph('a')->text);
    }

    public function testHeadingLevelsGoFromOneToSix(): void
    {
        self::assertSame(6, new Heading('a', 6)->level);

        foreach ([0, 7] as $level) {
            try {
                new Heading('a', $level);
                self::fail('The level was taken.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame('The level of a heading is between 1 and 6.', $exception->getMessage());
            }
        }
    }

    public function testAnOrderedListStartsAtZeroOrMore(): void
    {
        self::assertSame(0, new ListBlock([], true, 0)->start);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('An ordered list starts at 0 or more.');

        new ListBlock([], true, -1);
    }
}
