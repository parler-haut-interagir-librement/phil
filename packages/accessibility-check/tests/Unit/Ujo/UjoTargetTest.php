<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Ujo;

use Phil\AccessibilityCheck\Exception\InvalidContent;
use Phil\AccessibilityCheck\Exception\LimitExceeded;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Ujo\BlockVisit;
use Phil\AccessibilityCheck\Ujo\Fragment;
use Phil\AccessibilityCheck\Ujo\PlainFields;
use Phil\AccessibilityCheck\Ujo\UjoReader;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoTarget::class)]
#[CoversClass(UjoReader::class)]
#[CoversClass(BlockVisit::class)]
#[CoversClass(Fragment::class)]
#[CoversClass(PlainFields::class)]
final class UjoTargetTest extends TestCase
{
    public function testTheBlocksAreVisitedInDocumentOrderWithWhereTheyAre(): void
    {
        $document = new Ujo()->decode((string) json_encode(['blocks' => [
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'One']],
            ['id' => 'c', 'type' => 'columns', 'data' => ['cols' => [
                ['blocks' => [['id' => 'c1', 'type' => 'paragraph', 'data' => ['text' => 'Two']]]],
                ['blocks' => [['id' => 'c2', 'type' => 'header', 'data' => ['text' => 'Three', 'level' => 3]]]],
            ]]],
            ['id' => 'q', 'type' => 'quote', 'data' => ['text' => 'Four']],
        ]]));
        $target = UjoTarget::read($document, new Policy(), 'fr');

        self::assertSame(
            ['p' => 'blocks[0]', 'c' => 'blocks[1]', 'c1' => 'blocks[1].data.cols[0].blocks[0]', 'c2' => 'blocks[1].data.cols[1].blocks[0]', 'q' => 'blocks[2]'],
            array_column(array_map(static fn (BlockVisit $visit): array => [$visit->block->id, $visit->pointer], $target->blocks()), 1, 0),
        );
        self::assertSame(['p', 'c1', 'c2'], array_map(static fn (BlockVisit $visit): string => $visit->block->id, $target->blocks('paragraph', 'header')));
        self::assertSame(['q'], array_map(static fn (BlockVisit $visit): string => $visit->block->id, $target->blocks('quote')));
        self::assertSame($document, $target->document);
        self::assertSame('fr', $target->language);
    }

    public function testNestedBlocksOfADocumentNotNormalizedAreVisitedToo(): void
    {
        $document = Document::fromArray(['blocks' => [
            ['id' => 't', 'type' => 'toggle', 'data' => ['text' => 'T', 'blocks' => [
                ['id' => 'n', 'type' => 'paragraph', 'data' => ['text' => '<b>x</b>']],
                ['id' => 'bad id!', 'type' => 'paragraph', 'data' => ['text' => '<i>y</i>']],
                ['type' => 'paragraph', 'data' => 'not data'],
            ]]],
        ]]);
        $target = UjoTarget::read($document, new Policy());

        self::assertSame(['t', 'n'], array_map(static fn (BlockVisit $visit): string => $visit->block->id, $target->blocks()));
        self::assertSame(['blocks[0].data.blocks[0].data.text', 'blocks[0].data.blocks[1].data.text'], array_map(static fn (Fragment $fragment): string => $fragment->pointer, $target->fragments()));
        self::assertSame('n', $target->fragments()[0]->visit->block->id);
        self::assertSame('t', $target->fragments()[1]->visit->block->id);
    }

    public function testTheLanguageOfABlockIsItsTuneOrItsParents(): void
    {
        $document = Document::fromArray(['blocks' => [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => 'x']],
            ['id' => 'b', 'type' => 'toggle', 'data' => ['blocks' => [
                ['id' => 'b1', 'type' => 'paragraph', 'data' => ['text' => '<a href="/">x</a>']],
                ['id' => 'b2', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => ['language' => 'es']]],
            ]], 'tunes' => ['language' => ['language' => 'de', 'direction' => 'ltr']]],
            ['id' => 'c', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => ['direction' => 'rtl']]],
            ['id' => 'd', 'type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['language' => 'it']],
        ]]);
        $target = UjoTarget::read($document, new Policy(), 'fr');

        self::assertSame(['a' => 'fr', 'b' => 'de', 'b1' => 'de', 'b2' => 'es', 'c' => 'fr', 'd' => 'fr'], array_column(array_map(static fn (BlockVisit $visit): array => [$visit->block->id, $visit->language], $target->blocks()), 1, 0));
        self::assertSame('de', $target->fragments()[0]->html->language);
    }

    public function testOnlyTheTextsThatHoldHtmlAreParsed(): void
    {
        $document = Document::fromArray(['blocks' => [
            ['id' => 'i', 'type' => 'image', 'data' => ['file' => ['url' => '/a.png'], 'alt' => 'a <b> c', 'caption' => 'A <i>cat</i>', 'files' => [['caption' => '<b>1</b>'], 'plain', 3]]],
            ['id' => 'c', 'type' => 'code', 'data' => ['code' => '<img src=x>']],
            ['id' => 'h', 'type' => 'chart', 'data' => ['title' => '<b>t</b>']],
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'no tag here']],
        ]]);
        $target = UjoTarget::read($document, new Policy());
        $fragments = $target->fragments();

        self::assertSame(['blocks[0].data.caption', 'blocks[0].data.files[0].caption'], array_map(static fn (Fragment $fragment): string => $fragment->pointer, $fragments));
        self::assertEquals(new Location(null, 'i', '<i>', 'i', 'image', 'blocks[0].data.caption'), $fragments[0]->html->locate($fragments[0]->html->elements('i')[0]));
        self::assertSame($fragments, $target->fragments($target->blocks()[0]));
        self::assertSame([], $target->fragments($target->blocks()[1]));
        self::assertCount(1, UjoTarget::read($document, new Policy(), null, new PlainFields(['code' => ['language']]))->fragments($target->blocks()[1]));
    }

    public function testTheBlocksAreCounted(): void
    {
        $document = Document::fromArray(['blocks' => [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => 'x']],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'y']],
        ]]);

        self::assertCount(2, UjoTarget::read($document, new Policy(maxElements: 2))->blocks());

        $this->expectExceptionObject(LimitExceeded::count(1));

        UjoTarget::read($document, new Policy(maxElements: 1));
    }

    public function testTheNestingIsBounded(): void
    {
        $document = Document::fromArray(['blocks' => [['id' => 'a', 'type' => 'list', 'data' => ['items' => [['content' => 'x']]]]]]);

        self::assertCount(1, UjoTarget::read($document, new Policy(maxDepth: 4))->blocks());

        $this->expectExceptionObject(LimitExceeded::depth(3));

        UjoTarget::read($document, new Policy(maxDepth: 3));
    }

    public function testTheNestingOfBlocksIsBounded(): void
    {
        $document = Document::fromArray(['blocks' => [['id' => 'a', 'type' => 'toggle', 'data' => ['blocks' => [['id' => 'b', 'type' => 'delimiter', 'data' => []]]]]]]);

        self::assertCount(2, UjoTarget::read($document, new Policy(maxDepth: 5))->blocks());

        $this->expectExceptionObject(LimitExceeded::depth(4));

        UjoTarget::read($document, new Policy(maxDepth: 4));
    }

    public function testAFieldThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(InvalidContent::class);

        UjoTarget::read(new Document([new Block('a', 'paragraph', ['text' => "<b>\xFF</b>"])]), new Policy());
    }

    public function testAFieldIsReadByItsKeys(): void
    {
        $block = new Block('a', 'image', ['file' => ['url' => '/a.png'], 'files' => [['caption' => 'c']], 'n' => null]);

        self::assertSame('/a.png', UjoTarget::field($block, 'file', 'url'));
        self::assertSame('c', UjoTarget::field($block, 'files', 0, 'caption'));
        self::assertSame(['url' => '/a.png'], UjoTarget::field($block, 'file'));
        self::assertNull(UjoTarget::field($block, 'file', 'url', 'deeper'));
        self::assertNull(UjoTarget::field($block, 'missing'));
        self::assertNull(UjoTarget::field($block, 'n'));
    }

    public function testTheTextOfAFieldLeavesItsTagsOut(): void
    {
        self::assertSame('A cat &amp; a <dog>', UjoTarget::text('A<b>cat</b>  &amp;amp; a &lt;dog&gt;'));
        self::assertSame('Fish & chips', UjoTarget::text('Fish &amp; chips'));
        self::assertSame('l\'été "x"', UjoTarget::text('l&apos;été &quot;x&quot;'));
        self::assertSame('', UjoTarget::text(['x']));
        self::assertSame('', UjoTarget::text(null));
        self::assertEquals(new Location(), UjoTarget::whole());
    }

    public function testABlockIsLocatedByItsPointer(): void
    {
        $visit = new BlockVisit(new Block('a', 'image'), 'blocks[3]', null);

        self::assertEquals(new Location(block: 'a', blockType: 'image', pointer: 'blocks[3]'), $visit->locate());
        self::assertEquals(new Location(block: 'a', blockType: 'image', pointer: 'blocks[3].data.files[1]'), $visit->locate('files[1]'));
    }

    public function testThePlainFieldsAreThoseOfTheType(): void
    {
        $plain = new PlainFields(['image' => ['credit']]);

        self::assertTrue($plain->isPlain('code', 'code'));
        self::assertFalse($plain->isPlain('code', 'caption'));
        self::assertTrue($plain->isPlain('chart', 'anything'));
        self::assertTrue($plain->isPlain('image', 'credit'));
        self::assertFalse($plain->isPlain('image', 'alt'));
        self::assertFalse($plain->isPlain('paragraph', 'text'));
    }
}
