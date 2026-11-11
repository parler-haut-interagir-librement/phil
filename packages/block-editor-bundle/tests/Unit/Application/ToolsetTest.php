<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Application;

use Phil\BlockEditorBundle\Application\Exception\InvalidToolset;
use Phil\BlockEditorBundle\Application\Toolset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Toolset::class)]
#[CoversClass(InvalidToolset::class)]
final class ToolsetTest extends TestCase
{
    public function testTheFullPresetHasEveryTool(): void
    {
        $full = Toolset::full();

        self::assertSame(Toolset::BLOCK_TOOLS, $full->blockTools);
        self::assertSame(Toolset::INLINE_TOOLS, $full->inlineTools);
        self::assertEquals($full, new Toolset());
        self::assertEquals($full, Toolset::preset('full'));
    }

    public function testTheReducedPresetIsForSimpleSites(): void
    {
        $reduced = Toolset::reduced();

        self::assertSame(['paragraph', 'header', 'list', 'quote'], $reduced->blockTools);
        self::assertSame(['bold', 'italic', 'link', 'lineBreak'], $reduced->inlineTools);
        self::assertEquals($reduced, Toolset::preset('reduced'));
    }

    public function testAPresetsToolsAreReplacedWhenGiven(): void
    {
        $toolset = Toolset::preset('reduced', ['quote', 'paragraph', 'image', 'paragraph']);

        self::assertSame(['paragraph', 'quote', 'image'], $toolset->blockTools, "in the editor's order, each once");
        self::assertSame(['bold', 'italic', 'link', 'lineBreak'], $toolset->inlineTools);
        self::assertSame(['bold'], Toolset::preset('full', null, ['bold'])->inlineTools);
        self::assertSame(Toolset::BLOCK_TOOLS, Toolset::preset('full', null, ['bold'])->blockTools);
        self::assertSame([], new Toolset(['paragraph'], [])->inlineTools);
    }

    public function testToolsAreAskedFor(): void
    {
        $toolset = Toolset::reduced();

        self::assertTrue($toolset->hasBlock('quote'));
        self::assertFalse($toolset->hasBlock('table'));
        self::assertTrue($toolset->hasInline('link'));
        self::assertFalse($toolset->hasInline('marker'));
        self::assertSame('paragraph,header,list,quote|bold,italic,link,lineBreak', $toolset->key());
        self::assertSame(new Toolset(['quote', 'paragraph'], ['bold'])->key(), new Toolset(['paragraph', 'quote'], ['bold', 'bold'])->key());
    }

    public function testAnUnknownBlockToolIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidToolset('The block editor has no block tool named "statblock", "raw"; the block tools are "paragraph", "header", "list", "quote", "code", "alert", "toggle", "columns", "table", "image", "delimiter".'));

        new Toolset(['paragraph', 'statblock', 'raw']);
    }

    public function testAnUnknownInlineToolIsRefused(): void
    {
        $this->expectException(InvalidToolset::class);
        $this->expectExceptionMessageIsOrContains('The block editor has no inline tool named "footnoteReference"; the inline tools are "bold", ');

        new Toolset(['paragraph'], ['footnoteReference']);
    }

    public function testTheParagraphIsRequired(): void
    {
        $this->expectExceptionObject(new InvalidToolset('A toolset of the block editor needs the "paragraph" tool: it is the block every new line starts as.'));

        new Toolset(['header']);
    }

    public function testAnUnknownPresetIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidToolset('The block editor has no preset named "simple"; the presets are "full" and "reduced".'));

        Toolset::preset('simple');
    }
}
