<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Infrastructure;

use Phil\BlockEditorBundle\Application\Toolset;
use Phil\BlockEditorBundle\Infrastructure\Ujo\ToolsetNormalizers;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolsetNormalizers::class)]
final class ToolsetNormalizersTest extends TestCase
{
    private const string TEXT = '<b>b</b> <i>i</i> <u>u</u> <mark>m</mark> <a href="/x">l</a> <a data-mention="user:1">@a</a><br>z';

    private function text(Normalizer $normalizer, string $type = 'paragraph'): string
    {
        $data = $normalizer->normalize(Document::fromArray(['blocks' => [['id' => 'a', 'type' => $type, 'data' => ['text' => self::TEXT]]]]))->blocks[0]->data;

        return \is_string($data['text']) ? $data['text'] : '';
    }

    public function testTheInlineToolsLeftOutAreUnwrapped(): void
    {
        $normalizers = new ToolsetNormalizers(BlockTypeRegistry::default());

        self::assertSame('<b>b</b> <i>i</i> u m <a href="/x">l</a> @a<br>z', $this->text($normalizers->normalizer(Toolset::reduced())));
        self::assertSame('<b>b</b> <i>i</i> <u class="cdx-underline">u</u> <mark class="cdx-marker">m</mark> <a href="/x">l</a> <a data-mention="user:1">@a</a><br>z', $this->text($normalizers->normalizer(Toolset::full())));
        self::assertSame('b i u m l @az', $this->text($normalizers->normalizer(new Toolset(['paragraph'], []))));
    }

    public function testTheBlockTypesLeftOutAreRefusedUnlessForeignBlocksAreKept(): void
    {
        $normalizers = new ToolsetNormalizers(BlockTypeRegistry::default());

        self::assertSame('<b>b</b> <i>i</i> u m <a href="/x">l</a> @a<br>z', $this->text($normalizers->normalizer(Toolset::reduced(), true), 'alert'));
        self::assertSame('<b>b</b> <i>i</i> <u class="cdx-underline">u</u> <mark class="cdx-marker">m</mark> <a href="/x">l</a> <a data-mention="user:1">@a</a><br>z', $this->text($normalizers->normalizer(Toolset::full()), 'alert'));

        $this->expectException(UnknownBlockType::class);
        $this->text($normalizers->normalizer(Toolset::reduced()), 'alert');
    }

    public function testTheTypesOfTheApplicationsRegistryAreTheOnesKept(): void
    {
        $normalizers = new ToolsetNormalizers(BlockTypeRegistry::default()->without('header'));

        $this->expectException(UnknownBlockType::class);
        $this->text($normalizers->normalizer(Toolset::full()), 'header');
    }

    public function testOneNormalizerPerToolsetBuiltOnce(): void
    {
        $normalizers = new ToolsetNormalizers(BlockTypeRegistry::default());

        self::assertSame($normalizers->normalizer(Toolset::reduced()), $normalizers->normalizer(Toolset::preset('reduced')));
        self::assertNotSame($normalizers->normalizer(Toolset::reduced()), $normalizers->normalizer(Toolset::reduced(), true));
        self::assertNotSame($normalizers->normalizer(Toolset::reduced()), $normalizers->normalizer(Toolset::full()));
        self::assertSame($normalizers->normalizer(Toolset::full(), true), $normalizers->normalizer(Toolset::full(), true));
    }

    public function testNoDepthOfItsOwn(): void
    {
        $blocks = [['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'deep']]];

        for ($depth = 0; $depth < 20; ++$depth) {
            $blocks = [['id' => 't'.$depth, 'type' => 'toggle', 'data' => ['text' => '', 'blocks' => $blocks]]];
        }

        $normalized = new ToolsetNormalizers(BlockTypeRegistry::default())->normalizer(Toolset::full())->normalize(Document::fromArray(['blocks' => $blocks]));

        self::assertCount(21, iterator_to_array($normalized->walk(), false));
    }
}
