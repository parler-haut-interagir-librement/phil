<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Html\InlineProfile;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Html\InlineTool;
use Phil\Ujo\Html\Renderer\LayoutRenderer;
use Phil\Ujo\Html\Renderer\ListRenderer;
use Phil\Ujo\Html\Renderer\MediaRenderer;
use Phil\Ujo\Html\Renderer\TextRenderer;
use Phil\Ujo\Html\Rendering;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockType;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Normalizer::class)]
#[CoversClass(Context::class)]
#[CoversClass(UnknownBlockType::class)]
#[CoversClass(Shape::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Link::class)]
#[CoversClass(Html::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Lazy::class)]
#[CoversClass(Boolean::class)]
#[CoversClass(StandardTypes::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(Tunes::class)]
#[CoversClass(BlockTypeRegistry::class)]
#[CoversClass(TuneRegistry::class)]
#[CoversClass(EmbedServices::class)]
#[CoversClass(InlineSanitizer::class)]
#[CoversClass(InlineProfile::class)]
#[CoversClass(InlineTool::class)]
#[CoversClass(HtmlRenderer::class)]
#[CoversClass(Rendering::class)]
#[CoversClass(TextRenderer::class)]
#[CoversClass(ListRenderer::class)]
#[CoversClass(MediaRenderer::class)]
#[CoversClass(LayoutRenderer::class)]
#[CoversClass(PlainText::class)]
#[CoversClass(Block::class)]
#[CoversClass(Document::class)]
final class NormalizerTest extends UjoTestCase
{
    public function testADocumentKeepsItsTimeAndVersion(): void
    {
        $document = new Normalizer()->normalize(new Document([new Block('a', 'paragraph', ['text' => '<b>t</b>'])], 5, '2.31.7'));

        self::assertEquals(new Document([new Block('a', 'paragraph', ['text' => '<b>t</b>'])], 5, '2.31.7'), $document);
    }

    public function testADocumentTheNormalizerReturnedIsNotCheckedAgain(): void
    {
        $normalizer = new Normalizer();
        $unchecked = new Document([new Block('a', 'paragraph', ['text' => '<em>t</em>'])]);
        $document = $normalizer->normalize($unchecked);

        self::assertSame($document, $normalizer->normalize($document));
        self::assertNotSame($document, $normalizer->normalize($unchecked));
        self::assertNotSame($document, new Normalizer()->normalize($document));
    }

    public function testNestedBlocksAreNormalizedAgainstTheirOwnType(): void
    {
        $block = self::normalize('columns', ['cols' => [['blocks' => [['id' => 'h', 'type' => 'header', 'data' => ['text' => '<em>x</em>'], 'tunes' => ['alignmentTune' => ['alignment' => 'center']]]]]]]);

        self::assertEquals([new Block('h', 'header', ['text' => '<i>x</i>', 'level' => 2], ['alignment' => ['alignment' => 'center']])], $block->children());
    }

    public function testFlatChildrenAreFoldedIntoTheirToggle(): void
    {
        $document = self::normalizer()->normalize(Document::fromArray(['blocks' => [
            ['id' => 'a', 'type' => 'toggle', 'data' => ['text' => 'A', 'status' => 'open', 'fk' => 'k', 'items' => 3]],
            ['id' => 'b', 'type' => 'toggle', 'data' => ['text' => 'B', 'status' => 'closed', 'items' => 1]],
            ['id' => 'c', 'type' => 'paragraph', 'data' => ['text' => 'c']],
            ['id' => 'd', 'type' => 'paragraph', 'data' => ['text' => 'd']],
            ['id' => 'e', 'type' => 'accordion', 'data' => ['title' => 'E', 'settings' => ['blockCount' => 1, 'defaultExpanded' => false]]],
            ['id' => 'f', 'type' => 'paragraph', 'data' => ['text' => 'f']],
            ['id' => 'g', 'type' => 'toggle', 'data' => ['text' => 'G', 'items' => 0]],
        ]]));

        self::assertSame([
            ['id' => 'a', 'type' => 'toggle', 'data' => ['text' => 'A', 'open' => true, 'blocks' => [
                ['id' => 'b', 'type' => 'toggle', 'data' => ['text' => 'B', 'open' => false, 'blocks' => [['id' => 'c', 'type' => 'paragraph', 'data' => ['text' => 'c']]]]],
                ['id' => 'd', 'type' => 'paragraph', 'data' => ['text' => 'd']],
            ]]],
            ['id' => 'e', 'type' => 'toggle', 'data' => ['text' => 'E', 'open' => false, 'blocks' => [['id' => 'f', 'type' => 'paragraph', 'data' => ['text' => 'f']]]]],
            ['id' => 'g', 'type' => 'toggle', 'data' => ['text' => 'G', 'open' => false, 'blocks' => []]],
        ], array_map(static fn (Block $block): array => $block->toArray(), $document->blocks));
    }

    public function testANestedToggleIsNotFoldedAgain(): void
    {
        $block = self::normalize('toggle', ['text' => 't', 'items' => 5, 'blocks' => []]);

        self::assertSame(['text' => 't', 'open' => false, 'blocks' => []], $block->data);
    }

    public function testACountThatIsNotANumberFoldsNothing(): void
    {
        self::assertSame(['text' => 't', 'open' => false, 'blocks' => []], self::normalize('toggle', ['text' => 't', 'items' => '2'])->data);
        self::assertSame(['text' => 'T', 'open' => false, 'blocks' => []], self::normalize('accordion', ['title' => 'T', 'settings' => 'x'])->data);
        self::assertInvalid('The UJO document is invalid at blocks[0]: the block cannot hold -2 blocks after it.', 'accordion', ['title' => 'T', 'settings' => ['blockCount' => -2]]);
    }

    public function testAFoldNeedsItsChildren(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[1]: the block holds the 2 blocks after it, and only 1 follow.');

        self::normalizer()->normalize(Document::fromArray(['blocks' => [
            ['id' => 'z', 'type' => 'paragraph', 'data' => ['text' => 'z']],
            ['id' => 'a', 'type' => 'toggle', 'data' => ['text' => 'A', 'items' => 2]],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'b']],
        ]]));
    }

    public function testTheCountOfTheToggleBlockWinsOverASettingsCount(): void
    {
        $document = self::normalizer()->normalize(Document::fromArray(['blocks' => [
            ['id' => 'a', 'type' => 'toggle', 'data' => ['text' => 'A', 'items' => 1, 'settings' => ['blockCount' => 2]]],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'b']],
            ['id' => 'c', 'type' => 'paragraph', 'data' => ['text' => 'c']],
        ]]));

        self::assertCount(2, $document->blocks);
    }

    public function testAFoldMayTakeTheLastBlock(): void
    {
        $document = self::normalizer()->normalize(Document::fromArray(['blocks' => [
            ['id' => 'a', 'type' => 'toggle', 'data' => ['text' => 'A', 'items' => 1]],
            ['id' => 'b', 'type' => 'paragraph', 'data' => ['text' => 'b']],
        ]]));

        self::assertCount(1, $document->blocks);
    }

    public function testIdentifiersAreUniqueInTheDocument(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].data.blocks[1].id: the identifier "x" is already used by the block at blocks[0].data.blocks[0].');

        self::normalizer()->normalize(Document::fromArray(['blocks' => [
            ['id' => 'w', 'type' => 'wide', 'data' => ['blocks' => [
                ['id' => 'x', 'type' => 'paragraph', 'data' => ['text' => 'a']],
                ['id' => 'x', 'type' => 'paragraph', 'data' => ['text' => 'b']],
            ]]],
        ]]));
    }

    public function testIdentifiersOfTwoDocumentsAreIndependent(): void
    {
        $normalizer = self::normalizer();
        $document = new Document([new Block('a', 'paragraph', ['text' => 't'])]);

        self::assertEquals($normalizer->normalize($document), $normalizer->normalize($document));
    }

    public function testNestingIsLimited(): void
    {
        $nested = static fn (int $depth): Document => new Document([self::wide($depth)]);
        $normalizer = new Normalizer(maxDepth: 2);

        self::assertCount(1, $normalizer->normalize($nested(1))->blocks);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].data.blocks[0].data.blocks[0].data.blocks: blocks are nested deeper than 2 levels.');

        $normalizer->normalize($nested(2));
    }

    public function testTheDepthIsCountedPerBranch(): void
    {
        $normalizer = new Normalizer(maxDepth: 1);
        $document = new Document([new Block('c', 'columns', ['cols' => [['blocks' => [new Block('a', 'paragraph', ['text' => 'a'])]], ['blocks' => [new Block('b', 'paragraph', ['text' => 'b'])]]]])]);

        self::assertCount(2, $normalizer->normalize($document)->blocks[0]->children());
        self::assertSame(8, Normalizer::DEFAULT_MAX_DEPTH);
    }

    public function testTheNumberOfBlocksIsLimited(): void
    {
        $normalizer = new Normalizer(maxBlocks: 3);
        $document = new Document([new Block('a', 'paragraph', ['text' => 'a']), new Block('w', 'wide', ['blocks' => [new Block('b', 'paragraph', ['text' => 'b'])]])]);

        self::assertCount(2, $normalizer->normalize($document)->blocks);
        self::assertSame(10_000, Normalizer::DEFAULT_MAX_BLOCKS);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[1].data.blocks[1]: the document holds more blocks than the 3 it may.');

        $normalizer->normalize($document->withBlocks([$document->blocks[0], new Block('w', 'wide', ['blocks' => [new Block('b', 'paragraph', ['text' => 'b']), new Block('c', 'paragraph', ['text' => 'c'])]])]));
    }

    public function testTheBlocksOfEachNormalizationAreCountedAfresh(): void
    {
        $normalizer = new Normalizer(maxBlocks: 1);
        $document = new Document([new Block('a', 'paragraph', ['text' => 'a'])]);

        self::assertCount(1, $normalizer->normalize($document)->blocks);
        self::assertCount(1, $normalizer->normalize(new Document([new Block('b', 'paragraph', ['text' => 'b'])]))->blocks);
    }

    public function testADocumentHoldsTenTablesOfContentsAndTenListsOfAbbreviations(): void
    {
        $ten = static fn (string $type): array => array_map(static fn (int $index): Block => new Block($type.$index, $type), range(1, 10));

        self::assertCount(21, self::normalizer()->normalize(new Document([...$ten('toc'), ...$ten('abbreviations'), new Block('h', 'header', ['text' => 'h'])]))->blocks);
        self::assertSame([10, 10, null], [LayoutTypes::toc()->maxPerDocument, LayoutTypes::abbreviations()->maxPerDocument, LayoutTypes::index()->maxPerDocument]);

        foreach (['toc', 'abbreviations'] as $type) {
            try {
                self::normalizer()->normalize(new Document([...$ten($type), new Block('w', 'wide', ['blocks' => [new Block('u', $type)]])]));
                self::fail('Eleven blocks of type '.$type.' are accepted.');
            } catch (InvalidDocument $invalidDocument) {
                self::assertSame('The UJO document is invalid at blocks[10].data.blocks[0]: the document holds more "'.$type.'" blocks than the 10 it may.', $invalidDocument->getMessage());
            }
        }
    }

    public function testHtmlIsLimitedInLengthByItsProfile(): void
    {
        $normalizer = new Normalizer(new BlockTypeRegistry(StandardTypes::paragraph(), StandardTypes::raw()), maxHtmlLength: 3, maxRawLength: 8);

        self::assertSame(['ééa', '<i>x</i>'], array_map(static fn (Block $block): mixed => $block->data['text'] ?? $block->data['html'], $normalizer->normalize(new Document([new Block('a', 'paragraph', ['text' => 'ééa']), new Block('b', 'raw', ['html' => '<i>x</i>'])]))->blocks));
        self::assertSame([100_000, 50_000], [Normalizer::DEFAULT_MAX_HTML_LENGTH, Normalizer::DEFAULT_MAX_RAW_LENGTH]);

        foreach ([[new Block('a', 'paragraph', ['text' => '<b>a</b>']), 'blocks[0].data.text: the HTML is longer than 3 characters.'], [new Block('b', 'raw', ['html' => '<i>xy</i>']), 'blocks[0].data.html: the HTML is longer than 8 characters.']] as [$block, $message]) {
            try {
                $normalizer->normalize(new Document([$block]));
                self::fail('The HTML is accepted: '.$message);
            } catch (InvalidDocument $invalidDocument) {
                self::assertSame('The UJO document is invalid at '.$message, $invalidDocument->getMessage());
            }
        }
    }

    public function testNestedBlocksAreAList(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.blocks: a list of blocks is expected.', 'wide', ['blocks' => ['a' => []]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.blocks: a list of blocks is expected.', 'wide', ['blocks' => 'x']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.blocks[0]: a block must be an object.', 'wide', ['blocks' => ['x']]);
    }

    public function testAnUnknownTypeIsRefused(): void
    {
        try {
            new Normalizer()->normalize(new Document([new Block('a', 'paragraph', ['text' => 't']), new Block('b', 'raw', ['html' => 'x'])]));
            self::fail('A raw block is accepted.');
        } catch (UnknownBlockType $unknownBlockType) {
            self::assertSame('The block type "raw" at blocks[1] is not registered.', $unknownBlockType->getMessage());
            self::assertSame('raw', $unknownBlockType->type);
        }
    }

    public function testTheRegistriesAndTheSanitizerAreThoseGiven(): void
    {
        $normalizer = new Normalizer(
            new BlockTypeRegistry(StandardTypes::paragraph()),
            new TuneRegistry(),
            new InlineSanitizer(['rich' => InlineProfile::plain()]),
        );

        self::assertSame(['text' => 'a'], $normalizer->normalize(new Document([new Block('a', 'paragraph', ['text' => '<b>a</b>'])]))->blocks[0]->data);

        $this->expectException(UnknownBlockType::class);
        $this->expectExceptionMessageIsOrContains('The tune "alignment" at blocks[0].tunes.alignment is not registered.');

        $normalizer->normalize(new Document([new Block('a', 'paragraph', ['text' => 'a'], ['alignment' => ['alignment' => 'left']])]));
    }

    public function testTheDataOfAnUpgradedTypeIsChecked(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data: an object is expected.', 'paragraph', ['x']);
    }

    private static function wide(int $depth): Block
    {
        return new Block('w'.$depth, 'wide', ['blocks' => 0 === $depth ? [] : [self::wide($depth - 1)]]);
    }

    public function testTheTypesGivenAreTheOnlyOnes(): void
    {
        $normalizer = new Normalizer(new BlockTypeRegistry(new BlockType('sparkle', new Shape())));

        self::assertSame('sparkle', $normalizer->normalize(new Document([new Block('a', 'sparkle')]))->blocks[0]->type);
    }

    public function testEveryTuneIsKeptAndNamesItsPath(): void
    {
        self::assertSame(['alignment' => ['alignment' => 'left'], 'textVariant' => 'citation'], self::normalize('paragraph', ['text' => 't'], ['alignmentTune' => ['alignment' => 'left'], 'textVariant' => 'citation'])->tunes);
        self::assertInvalid('The UJO document is invalid at blocks[0].tunes.alignmentTune.alignment: this value is required.', 'paragraph', ['text' => 't'], ['alignmentTune' => []]);
    }

    public function testCalumkColumnsKeepTheirOtherValuesChecked(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.extra: this value is not part of the data.', 'columns', ['cols' => [['time' => 1, 'blocks' => []]], 'extra' => 1]);
    }
}
