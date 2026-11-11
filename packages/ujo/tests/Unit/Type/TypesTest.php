<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Type;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
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
use Phil\Ujo\Schema\TextOrShape;
use Phil\Ujo\Tests\Unit\UjoTestCase;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\EmbedServices;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\MediaTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TableGrid;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(StandardTypes::class)]
#[CoversClass(CommunityTypes::class)]
#[CoversClass(LayoutTypes::class)]
#[CoversClass(Tunes::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(Context::class)]
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
#[CoversClass(MediaTypes::class)]
#[CoversClass(TableGrid::class)]
#[CoversClass(TextOrShape::class)]
final class TypesTest extends UjoTestCase
{
    private const array EMBED = ['service' => 'youtube', 'source' => 'https://youtu.be/x', 'embed' => 'https://www.youtube.com/embed/x'];

    public function testTheDefaultRegistryHoldsEveryTypeButRaw(): void
    {
        self::assertSame([
            'paragraph', 'header', 'list', 'quote', 'warning', 'delimiter', 'code', 'table', 'image', 'linkTool', 'attaches', 'embed', 'personality',
            'alert', 'toggle', 'columns', 'math', 'mermaid', 'chart', 'button', 'gallery', 'video', 'audio',
            'pageBreak', 'columnBreak', 'pageStyle', 'wide', 'columnFlow', 'spacer', 'pageNumber', 'runningFooter', 'footnote', 'toc', 'index', 'abbreviations', 'watermark', 'note', 'descriptive', 'statblock', 'definitionList', 'credit', 'banner', 'decoration', 'qrCode', 'container',
        ], BlockTypeRegistry::default()->names());
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, array<array-key, mixed>}>
     */
    public static function minimalData(): iterable
    {
        yield 'paragraph' => ['paragraph', ['text' => 't'], ['text' => 't']];
        yield 'header' => ['header', ['text' => 't'], ['text' => 't', 'level' => 2]];
        yield 'list' => ['list', ['style' => 'unordered', 'items' => [['content' => 'a']]], ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'a', 'meta' => [], 'items' => []]]]];
        yield 'quote' => ['quote', ['text' => 't'], ['text' => 't', 'caption' => '', 'alignment' => 'left']];
        yield 'warning' => ['warning', [], ['title' => '', 'message' => '']];
        yield 'delimiter' => ['delimiter', [], []];
        yield 'code' => ['code', ['code' => '<x>'], ['code' => '<x>']];
        yield 'raw' => ['raw', ['html' => '<p onclick="x">a</p>'], ['html' => '<p>a</p>']];
        yield 'table' => ['table', [], ['withHeadings' => false, 'stretched' => false, 'content' => []]];
        yield 'image' => ['image', ['file' => ['url' => '/a.png']], ['file' => ['url' => '/a.png'], 'caption' => '', 'withBorder' => false, 'withBackground' => false, 'stretched' => false]];
        yield 'linkTool' => ['linkTool', ['link' => 'https://a.example'], ['link' => 'https://a.example', 'meta' => []]];
        yield 'attaches' => ['attaches', ['file' => ['url' => '/f.pdf']], ['file' => ['url' => '/f.pdf'], 'title' => '']];
        yield 'embed' => ['embed', self::EMBED, [...self::EMBED, 'caption' => '']];
        yield 'personality' => ['personality', ['name' => 'n'], ['name' => 'n', 'description' => '']];
        yield 'alert' => ['alert', ['text' => 't'], ['type' => 'info', 'align' => 'left', 'text' => 't']];
        yield 'toggle' => ['toggle', ['text' => 't'], ['text' => 't', 'open' => false, 'blocks' => []]];
        yield 'columns' => ['columns', ['cols' => [['blocks' => []]]], ['cols' => [['blocks' => []]]]];
        yield 'math' => ['math', ['latex' => 'x'], ['latex' => 'x']];
        yield 'mermaid' => ['mermaid', ['code' => 'x'], ['code' => 'x', 'caption' => '']];
        yield 'chart' => ['chart', ['type' => 'bar', 'data' => ['labels' => ['a']]], ['type' => 'bar', 'data' => ['labels' => ['a']]]];
        yield 'button' => ['button', ['link' => '/go', 'text' => 'Go'], ['link' => '/go', 'text' => 'Go']];
        yield 'gallery' => ['gallery', ['files' => [['url' => '/a.png']]], ['files' => [['url' => '/a.png']], 'style' => 'fit']];
        yield 'pageBreak' => ['pageBreak', [], []];
        yield 'columnBreak' => ['columnBreak', [], []];
        yield 'pageStyle' => ['pageStyle', [], ['classes' => []]];
        yield 'wide' => ['wide', ['blocks' => []], ['blocks' => []]];
        yield 'columnFlow' => ['columnFlow', ['blocks' => []], ['count' => 2, 'blocks' => []]];
        yield 'spacer' => ['spacer', [], ['lines' => 1]];
        yield 'pageNumber' => ['pageNumber', [], ['mode' => 'auto']];
        yield 'runningFooter' => ['runningFooter', ['text' => 't'], ['text' => 't']];
        yield 'footnote' => ['footnote', ['id' => 'fn1', 'text' => 't'], ['id' => 'fn1', 'text' => 't']];
        yield 'toc' => ['toc', [], ['title' => '', 'depth' => 3, 'wide' => false]];
        yield 'index' => ['index', ['entries' => [['term' => 'a']]], ['title' => '', 'columns' => 2, 'entries' => [['term' => 'a', 'pages' => '', 'children' => []]]]];
        yield 'watermark' => ['watermark', ['text' => 'x'], ['text' => 'x']];
        yield 'note' => ['note', ['blocks' => []], ['title' => '', 'blocks' => []]];
        yield 'descriptive' => ['descriptive', ['blocks' => []], ['title' => '', 'blocks' => []]];
        yield 'statblock' => ['statblock', ['name' => 'n'], ['kind' => 'monster', 'name' => 'n', 'subtitle' => '', 'frame' => false, 'wide' => false, 'sections' => []]];
        yield 'definitionList' => ['definitionList', ['items' => [['term' => 't', 'definitions' => ['d']]]], ['items' => [['term' => 't', 'definitions' => ['d']]]]];
        yield 'credit' => ['credit', ['text' => 't'], ['title' => '', 'text' => 't']];
        yield 'banner' => ['banner', ['text' => 'b'], ['text' => 'b']];
        yield 'decoration' => ['decoration', [], ['kind' => 'stain', 'variant' => 1]];
        yield 'qrCode' => ['qrCode', ['data' => 'x'], ['data' => 'x']];
        yield 'container' => ['container', ['classes' => ['c'], 'blocks' => []], ['classes' => ['c'], 'blocks' => []]];
        yield 'video' => ['video', ['sources' => [['url' => '/v.mp4']]], ['sources' => [['url' => '/v.mp4']], 'tracks' => [], 'controls' => true, 'loop' => false, 'muted' => false, 'caption' => '']];
        yield 'audio' => ['audio', ['sources' => [['url' => '/a.ogg']], 'tracks' => [['url' => '/a.vtt']]], ['sources' => [['url' => '/a.ogg']], 'tracks' => [['url' => '/a.vtt', 'kind' => 'subtitles', 'default' => false]], 'controls' => true, 'loop' => false, 'muted' => false, 'caption' => '']];
        yield 'math in MathML' => ['math', ['mathml' => '<math><mi>x</mi></math>'], ['mathml' => '<math><mi>x</mi></math>']];
        yield 'abbreviations' => ['abbreviations', [], ['title' => '']];
        yield 'rich table' => ['table', ['caption' => 'c', 'align' => ['', 'left'], 'content' => [[['text' => 'a', 'colspan' => 2, 'header' => true, 'align' => 'right']], ['b', ['text' => '<b>c</b>', 'rowspan' => 1]]]], ['withHeadings' => false, 'stretched' => false, 'caption' => 'c', 'align' => ['', 'left'], 'content' => [[['text' => 'a', 'colspan' => 2, 'header' => true, 'align' => 'right']], ['b', ['text' => '<b>c</b>', 'rowspan' => 1]]]]];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('minimalData')]
    public function testTheLeastDataIsCompletedWithTheDefaults(string $type, array $data, array $expected): void
    {
        $block = self::normalize($type, $data);

        self::assertSame($type, $block->type);
        self::assertSame($expected, $block->data);
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function fullData(): iterable
    {
        yield 'header' => ['header', ['text' => 't', 'level' => 3]];
        yield 'list' => ['list', ['style' => 'ordered', 'meta' => ['start' => 3, 'counterType' => 'upper-roman'], 'items' => [['content' => 'a', 'meta' => ['checked' => true], 'items' => [['content' => 'b', 'meta' => [], 'items' => []]]]]]];
        yield 'quote' => ['quote', ['text' => 't', 'caption' => 'c', 'alignment' => 'center']];
        yield 'warning' => ['warning', ['title' => 't', 'message' => 'm']];
        yield 'delimiter' => ['delimiter', ['style' => 'line', 'lineWidth' => 25, 'lineThickness' => 2]];
        yield 'code' => ['code', ['code' => 'x', 'language' => 'c++']];
        yield 'table' => ['table', ['withHeadings' => true, 'stretched' => true, 'content' => [['a', 'b'], ['c', 'd']]]];
        yield 'image' => ['image', ['file' => ['url' => 'https://a.example/b.png', 'width' => 3], 'caption' => 'c', 'alt' => 'a', 'withBorder' => true, 'withBackground' => true, 'stretched' => true, 'unsplash' => ['author' => 'A', 'profileLink' => 'https://unsplash.com/@a']]];
        yield 'linkTool' => ['linkTool', ['link' => 'https://a.example', 'meta' => ['title' => 't', 'site_name' => 's', 'description' => 'd', 'image' => ['url' => '/i.png', 'width' => 1], 'extra' => 'kept']]];
        yield 'attaches' => ['attaches', ['file' => ['url' => '/f.pdf', 'size' => 91, 'name' => 'f.pdf', 'extension' => 'pdf', 'id' => 7], 'title' => 'F']];
        yield 'embed' => ['embed', [...self::EMBED, 'width' => 580, 'height' => 320, 'caption' => 'c']];
        yield 'personality' => ['personality', ['name' => 'n', 'description' => 'd', 'link' => 'https://a.example', 'photo' => '/p.png']];
        yield 'alert' => ['alert', ['type' => 'danger', 'align' => 'center', 'text' => 't']];
        yield 'toggle' => ['toggle', ['text' => 't', 'open' => true, 'blocks' => []]];
        yield 'mermaid' => ['mermaid', ['code' => 'x', 'caption' => 'c']];
        yield 'gallery' => ['gallery', ['files' => [['url' => '/a.png', 'caption' => 'c', 'id' => 1]], 'source' => 's', 'style' => 'slider']];
        yield 'pageStyle' => ['pageStyle', ['variant' => 'partCover', 'classes' => ['a']]];
        yield 'columnFlow' => ['columnFlow', ['count' => 3, 'blocks' => []]];
        yield 'spacer' => ['spacer', ['lines' => 4]];
        yield 'pageNumber' => ['pageNumber', ['mode' => 'fixed', 'value' => '12']];
        yield 'toc' => ['toc', ['title' => 'Contents', 'depth' => 2, 'wide' => true]];
        yield 'index' => ['index', ['title' => 'Index', 'columns' => 5, 'entries' => [['term' => 'a', 'pages' => '3', 'children' => [['term' => 'b', 'pages' => '4', 'children' => []]]]]]];
        yield 'note' => ['note', ['title' => 't', 'blocks' => []]];
        yield 'statblock' => ['statblock', ['kind' => 'spell', 'name' => 'n', 'subtitle' => 's', 'frame' => true, 'wide' => true, 'sections' => [['title' => 'Actions', 'properties' => [['label' => 'AC', 'value' => '15']], 'abilities' => [['label' => 'STR', 'score' => 10, 'modifier' => '+0']], 'blocks' => []]], 'record' => 'creature:7']];
        yield 'credit' => ['credit', ['title' => 't', 'text' => 'x']];
        yield 'decoration' => ['decoration', ['kind' => 'ornament', 'variant' => 8, 'color' => '#BBAD82']];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('fullData')]
    public function testEveryValueOfTheShapeIsKeptInItsOrder(string $type, array $data): void
    {
        self::assertSame($data, self::normalize($type, $data)->data);
    }

    public function testTheRecordOfAStatBlockMayBeNull(): void
    {
        self::assertNull(self::normalize('statblock', ['name' => 'n', 'record' => null])->data['record']);
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string}>
     */
    public static function requiredValues(): iterable
    {
        foreach ([
            ['paragraph', ['text' => 't'], 'text'],
            ['header', ['text' => 't'], 'text'],
            ['list', ['style' => 'unordered', 'items' => []], 'style'],
            ['list', ['style' => 'unordered', 'items' => []], 'items'],
            ['list', ['style' => 'unordered', 'items' => [['content' => 'a']]], 'items.0.content'],
            ['quote', ['text' => 't'], 'text'],
            ['code', ['code' => 't'], 'code'],
            ['raw', ['html' => 't'], 'html'],
            ['image', ['file' => ['url' => '/a']], 'file'],
            ['image', ['file' => ['url' => '/a']], 'file.url'],
            ['image', ['file' => ['url' => '/a'], 'unsplash' => ['author' => 'a', 'profileLink' => '/p']], 'unsplash.author'],
            ['image', ['file' => ['url' => '/a'], 'unsplash' => ['author' => 'a', 'profileLink' => '/p']], 'unsplash.profileLink'],
            ['linkTool', ['link' => 'https://a.example'], 'link'],
            ['linkTool', ['link' => 'https://a.example', 'meta' => ['image' => ['url' => '/i']]], 'meta.image.url'],
            ['attaches', ['file' => ['url' => '/a']], 'file'],
            ['attaches', ['file' => ['url' => '/a']], 'file.url'],
            ['embed', self::EMBED, 'service'],
            ['embed', self::EMBED, 'source'],
            ['embed', self::EMBED, 'embed'],
            ['personality', ['name' => 'n'], 'name'],
            ['alert', ['text' => 't'], 'text'],
            ['toggle', ['text' => 't'], 'text'],
            ['columns', ['cols' => [['blocks' => []]]], 'cols'],
            ['columns', ['cols' => [['blocks' => []]]], 'cols.0.blocks'],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'sources'],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'sources.0.url'],
            ['audio', ['sources' => [['url' => '/a.ogg']], 'tracks' => [['url' => '/a.vtt']]], 'tracks.0.url'],
            ['mermaid', ['code' => 'x'], 'code'],
            ['button', ['link' => '/a', 'text' => 'b'], 'link'],
            ['button', ['link' => '/a', 'text' => 'b'], 'text'],
            ['gallery', ['files' => [['url' => '/a']]], 'files'],
            ['gallery', ['files' => [['url' => '/a']]], 'files.0.url'],
            ['wide', ['blocks' => []], 'blocks'],
            ['columnFlow', ['blocks' => []], 'blocks'],
            ['runningFooter', ['text' => 't'], 'text'],
            ['footnote', ['id' => 'a', 'text' => 't'], 'id'],
            ['footnote', ['id' => 'a', 'text' => 't'], 'text'],
            ['index', ['entries' => [['term' => 'a']]], 'entries.0.term'],
            ['watermark', ['text' => 't'], 'text'],
            ['note', ['blocks' => []], 'blocks'],
            ['descriptive', ['blocks' => []], 'blocks'],
            ['statblock', ['name' => 'n'], 'name'],
            ['statblock', ['name' => 'n', 'sections' => [['properties' => [['label' => 'a', 'value' => 'b']]]]], 'sections.0.properties.0.label'],
            ['statblock', ['name' => 'n', 'sections' => [['properties' => [['label' => 'a', 'value' => 'b']]]]], 'sections.0.properties.0.value'],
            ['statblock', ['name' => 'n', 'sections' => [['abilities' => [['label' => 'a', 'score' => 1]]]]], 'sections.0.abilities.0.label'],
            ['statblock', ['name' => 'n', 'sections' => [['abilities' => [['label' => 'a', 'score' => 1]]]]], 'sections.0.abilities.0.score'],
            ['definitionList', ['items' => [['term' => 't', 'definitions' => ['d']]]], 'items'],
            ['definitionList', ['items' => [['term' => 't', 'definitions' => ['d']]]], 'items.0.term'],
            ['definitionList', ['items' => [['term' => 't', 'definitions' => ['d']]]], 'items.0.definitions'],
            ['credit', ['text' => 't'], 'text'],
            ['banner', ['text' => 't'], 'text'],
            ['qrCode', ['data' => 't'], 'data'],
            ['container', ['classes' => ['a'], 'blocks' => []], 'classes'],
            ['container', ['classes' => ['a'], 'blocks' => []], 'blocks'],
        ] as [$type, $data, $path]) {
            yield $type.' '.$path => [$type, $data, $path];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('requiredValues')]
    public function testARequiredValueCannotBeLeftOut(string $type, array $data, string $path): void
    {
        self::normalize($type, $data);

        self::assertInvalid('The UJO document is invalid at '.self::at('data.'.$path).': this value is required.', $type, self::unset($data, $path));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, ?int, ?int}>
     */
    public static function bounds(): iterable
    {
        foreach ([
            ['header', ['text' => 't'], 'level', 1, 6],
            ['list', ['style' => 'ordered', 'items' => []], 'meta.start', 0, null],
            ['delimiter', [], 'lineWidth', 1, 100],
            ['delimiter', [], 'lineThickness', 1, 20],
            ['attaches', ['file' => ['url' => '/a']], 'file.size', 0, null],
            ['embed', self::EMBED, 'width', 1, 10_000],
            ['embed', self::EMBED, 'height', 1, 10_000],
            ['columnFlow', ['blocks' => []], 'count', 2, 6],
            ['spacer', [], 'lines', 1, 50],
            ['toc', [], 'depth', 1, 6],
            ['index', [], 'columns', 1, 6],
            ['decoration', [], 'variant', 1, 99],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'width', 1, 10_000],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'height', 1, 10_000],
            ['table', ['content' => [[['text' => 'a']]]], 'content.0.0.colspan', 1, 100],
            ['table', ['content' => [[['text' => 'a']]]], 'content.0.0.rowspan', 1, 1000],
        ] as [$type, $data, $path, $min, $max]) {
            yield $type.' '.$path => [$type, $data, $path, $min, $max];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('bounds')]
    public function testAWholeNumberStaysWithinItsBounds(string $type, array $data, string $path, ?int $min, ?int $max): void
    {
        $at = 'The UJO document is invalid at '.self::at('data.'.$path).': ';

        foreach (array_filter([$min, $max], is_int(...)) as $bound) {
            self::assertSame($bound, $this->read(self::normalize($type, self::set($data, $path, $bound))->data, $path));
        }

        if (null !== $min) {
            self::assertInvalid($at.\sprintf('%d is below the minimum %d.', $min - 1, $min), $type, self::set($data, $path, $min - 1));
        }

        if (null !== $max) {
            self::assertInvalid($at.\sprintf('%d is above the maximum %d.', $max + 1, $max), $type, self::set($data, $path, $max + 1));
        }

        self::assertInvalid($at.'a whole number is expected.', $type, self::set($data, $path, '3'));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, list<string>}>
     */
    public static function choices(): iterable
    {
        foreach ([
            ['list', ['items' => []], 'style', ['unordered', 'ordered', 'checklist']],
            ['list', ['style' => 'ordered', 'items' => []], 'meta.counterType', ['numeric', 'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha']],
            ['quote', ['text' => 't'], 'alignment', ['left', 'center']],
            ['delimiter', [], 'style', ['star', 'dash', 'line']],
            ['alert', ['text' => 't'], 'type', ['info', 'primary', 'secondary', 'success', 'warning', 'danger', 'light', 'dark']],
            ['alert', ['text' => 't'], 'align', ['left', 'center', 'right']],
            ['gallery', ['files' => [['url' => '/a']]], 'style', ['fit', 'slider']],
            ['pageStyle', [], 'variant', ['frontCover', 'insideCover', 'partCover', 'backCover']],
            ['pageNumber', [], 'mode', ['auto', 'fixed', 'skip', 'reset']],
            ['statblock', ['name' => 'n'], 'kind', ['monster', 'spell', 'item', 'other']],
            ['decoration', [], 'kind', ['stain', 'ornament']],
            ['video', ['sources' => [['url' => '/v.mp4']], 'tracks' => [['url' => '/t.vtt']]], 'tracks.0.kind', ['captions', 'subtitles', 'descriptions', 'chapters', 'metadata']],
            ['table', ['content' => [[['text' => 'a']]]], 'content.0.0.align', ['left', 'center', 'right']],
            ['table', ['content' => [['a']]], 'align.0', ['', 'left', 'center', 'right']],
        ] as [$type, $data, $path, $choices]) {
            yield $type.' '.$path => [$type, $data, $path, $choices];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $choices
     */
    #[DataProvider('choices')]
    public function testAChoiceIsOneOfItsList(string $type, array $data, string $path, array $choices): void
    {
        foreach ($choices as $choice) {
            self::assertSame($choice, $this->read(self::normalize($type, self::set($data, $path, $choice))->data, $path));
        }

        self::assertInvalid('The UJO document is invalid at '.self::at('data.'.$path).': one of "'.implode('", "', $choices).'" is expected.', $type, self::set($data, $path, 'nope'));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, int}>
     */
    public static function lengths(): iterable
    {
        foreach ([
            ['code', ['code' => 'x'], 'language', 32],
            ['image', ['file' => ['url' => '/a'], 'unsplash' => ['author' => 'a', 'profileLink' => '/p']], 'unsplash.author', 200],
            ['image', ['file' => ['url' => '/a']], 'alt', 1000],
            ['linkTool', ['link' => 'https://a.example'], 'meta.title', 500],
            ['linkTool', ['link' => 'https://a.example'], 'meta.site_name', 200],
            ['linkTool', ['link' => 'https://a.example'], 'meta.description', 2000],
            ['attaches', ['file' => ['url' => '/a']], 'file.name', 255],
            ['attaches', ['file' => ['url' => '/a']], 'file.extension', 16],
            ['attaches', ['file' => ['url' => '/a']], 'title', 255],
            ['personality', ['name' => 'n'], 'name', 200],
            ['personality', ['name' => 'n'], 'description', 2000],
            ['math', ['latex' => 'x'], 'latex', 20_000],
            ['mermaid', ['code' => 'x'], 'code', 20_000],
            ['button', ['link' => '/a', 'text' => 't'], 'text', 200],
            ['gallery', ['files' => [['url' => '/a']]], 'source', 500],
            ['pageNumber', [], 'value', 16],
            ['toc', [], 'title', 200],
            ['index', [], 'title', 200],
            ['abbreviations', [], 'title', 200],
            ['index', ['entries' => [['term' => 'a']]], 'entries.0.term', 200],
            ['index', ['entries' => [['term' => 'a']]], 'entries.0.pages', 200],
            ['watermark', ['text' => 't'], 'text', 100],
            ['statblock', ['name' => 'n'], 'name', 200],
            ['statblock', ['name' => 'n', 'sections' => [['title' => 't']]], 'sections.0.title', 200],
            ['statblock', ['name' => 'n', 'sections' => [['properties' => [['label' => 'a', 'value' => 'b']]]]], 'sections.0.properties.0.label', 200],
            ['statblock', ['name' => 'n', 'sections' => [['abilities' => [['label' => 'a', 'score' => 1]]]]], 'sections.0.abilities.0.label', 16],
            ['statblock', ['name' => 'n', 'sections' => [['abilities' => [['label' => 'a', 'score' => 1]]]]], 'sections.0.abilities.0.modifier', 16],
            ['statblock', ['name' => 'n'], 'record', 200],
            ['credit', ['text' => 't'], 'title', 200],
            ['banner', ['text' => 't'], 'text', 100],
            ['qrCode', ['data' => 't'], 'data', 2048],
            ['embed', self::EMBED, 'title', 200],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'title', 200],
            ['video', ['sources' => [['url' => '/v.mp4']], 'tracks' => [['url' => '/t.vtt']]], 'tracks.0.label', 100],
        ] as [$type, $data, $path, $length]) {
            yield $type.' '.$path => [$type, $data, $path, $length];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('lengths')]
    public function testATextStaysWithinItsLength(string $type, array $data, string $path, int $length): void
    {
        $text = str_repeat('c', $length);

        self::assertSame($text, $this->read(self::normalize($type, self::set($data, $path, $text))->data, $path));
        self::assertInvalid('The UJO document is invalid at '.self::at('data.'.$path).\sprintf(': the text is longer than %d characters.', $length), $type, self::set($data, $path, $text.'c'));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, list<string>, list<string>}>
     */
    public static function patterns(): iterable
    {
        foreach ([
            ['code', ['code' => 'x'], 'language', ['php', 'C#', 'objective-c', 'a_b.c+'], ['', 'p hp', 'é']],
            ['pageStyle', [], 'classes.0', ['a', 'A-b_9', str_repeat('a', 64)], ['', 'a b', 'é', str_repeat('a', 65)]],
            ['footnote', ['id' => 'a', 'text' => 't'], 'id', ['fn-1'], ['fn 1', '']],
            ['decoration', [], 'color', ['#BBAD82', '#000fff'], ['#BBAD8', '#BBAD82A', 'BBAD82', '#GGGGGG']],
            ['qrCode', ['data' => 'x'], 'data', ["\n", 'é'], ['']],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'sources.0.type', ['video/mp4', 'video/webm; codecs="vp9"', 'audio/x-m4a'], ['mp4', 'Video/MP4', 'video/mp4;', "video/mp4\n"]],
            ['audio', ['sources' => [['url' => '/a.ogg']], 'tracks' => [['url' => '/t.vtt']]], 'tracks.0.language', ['fr', 'pt-BR'], ['fr_CA', 'f']],
        ] as [$type, $data, $path, $valid, $invalid]) {
            yield $type.' '.$path => [$type, $data, $path, $valid, $invalid];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $valid
     * @param list<string>            $invalid
     */
    #[DataProvider('patterns')]
    public function testATextIsOfItsForm(string $type, array $data, string $path, array $valid, array $invalid): void
    {
        foreach ($valid as $text) {
            self::assertSame($text, $this->read(self::normalize($type, self::set($data, $path, $text))->data, $path));
        }

        foreach ($invalid as $text) {
            self::assertInvalid('The UJO document is invalid at '.self::at('data.'.$path).': "'.$text.'" is not of the expected form.', $type, self::set($data, $path, $text));
        }
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, int, ?int}>
     */
    public static function counts(): iterable
    {
        foreach ([
            ['columns', ['cols' => [['blocks' => []]]], 'cols', 1, 6, ['blocks' => []]],
            ['gallery', ['files' => [['url' => '/a']]], 'files', 1, null, ['url' => '/a']],
            ['definitionList', ['items' => [['term' => 't', 'definitions' => ['d']]]], 'items', 1, null, ['term' => 't', 'definitions' => ['d']]],
            ['definitionList', ['items' => [['term' => 't', 'definitions' => ['d']]]], 'items.0.definitions', 1, null, 'd'],
            ['container', ['classes' => ['a'], 'blocks' => []], 'classes', 1, null, 'a'],
            ['video', ['sources' => [['url' => '/v.mp4']]], 'sources', 1, 8, ['url' => '/v.mp4']],
        ] as [$type, $data, $path, $min, $max, $item]) {
            yield $type.' '.$path => [$type, $data, $path, $min, $max, $item];
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('counts')]
    public function testAListHoldsItsNumberOfItems(string $type, array $data, string $path, int $min, ?int $max, mixed $item): void
    {
        $at = 'The UJO document is invalid at '.self::at('data.'.$path).': ';
        $range = $min.' to '.($max ?? 'any number').' are expected.';

        self::assertCount($min, $this->list($this->read(self::normalize($type, self::set($data, $path, array_fill(0, $min, $item)))->data, $path)));
        self::assertInvalid($at.($min - 1).' items, where '.$range, $type, self::set($data, $path, array_fill(0, $min - 1, $item)));

        if (null !== $max) {
            self::assertCount($max, $this->list($this->read(self::normalize($type, self::set($data, $path, array_fill(0, $max, $item)))->data, $path)));
            self::assertInvalid($at.($max + 1).' items, where '.$range, $type, self::set($data, $path, array_fill(0, $max + 1, $item)));
        }
    }

    public function testTheTypesOfThePlayersAndOfTheLanguageAreTheirOwn(): void
    {
        self::assertSame(['video', 'audio', 'abbreviations', 'language'], [MediaTypes::video()->name, MediaTypes::audio()->name, LayoutTypes::abbreviations()->name, Tunes::language()->name]);

        $type = 'audio/'.str_repeat('c', 94);
        self::assertSame($type, $this->read(self::normalize('audio', ['sources' => [['url' => '/a.ogg', 'type' => $type]]])->data, 'sources.0.type'));
        self::assertInvalid('The UJO document is invalid at '.self::at('data.sources.0.type').': the text is longer than 100 characters.', 'audio', ['sources' => [['url' => '/a.ogg', 'type' => $type.'c']]]);
    }

    public function testAPlayerHasUpToSixteenTracks(): void
    {
        $tracks = array_fill(0, 16, ['url' => '/t.vtt']);

        self::assertSame($tracks, array_map(static fn (mixed $track): mixed => \is_array($track) ? ['url' => $track['url'] ?? null] : $track, (array) self::normalize('audio', ['sources' => [['url' => '/a.ogg']], 'tracks' => $tracks])->data['tracks']));
        self::assertInvalid('The UJO document is invalid at blocks[0].data.tracks: 17 items, where 0 to 16 are expected.', 'audio', ['sources' => [['url' => '/a.ogg']], 'tracks' => [...$tracks, ['url' => '/t.vtt']]]);
    }

    public function testAFormulaIsInLatexOrInMathMl(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.latex: a formula in LaTeX or in MathML is required.', 'math', []);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.latex: a formula in LaTeX or in MathML is required.', 'math', ['mathml' => '<b></b>']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.mathml: one <math> element is expected.', 'math', ['mathml' => 'x<math><mi>y</mi></math>']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.mathml: one <math> element is expected.', 'math', ['mathml' => '<math><mi>y</mi></math>z']);
        self::assertSame(['mathml' => '<math><mi>y</mi></math>'], self::normalize('math', ['mathml' => '<math><mi>y</mi></math>'])->data);
        self::assertSame(['latex' => '', 'mathml' => ''], self::normalize('math', ['latex' => '', 'mathml' => '<b></b>'])->data);
    }

    public function testRichCellsSpanColumnsAndRows(): void
    {
        self::assertSame([[2, [0]], [2, [0, 1]]], TableGrid::layout([[['text' => 'a', 'colspan' => 2]], ['b', 'c']]));
        self::assertSame([[3, [0, 1, 2]], [3, [1]], [3, [0, 1]]], TableGrid::layout([[['rowspan' => 2], 'b', ['rowspan' => 3]], ['e'], ['f', ['rowspan' => 1]]]));
        self::assertSame([[1, [0]], [0, []]], TableGrid::layout([['a'], 'not a row']));
        self::assertSame([[3, [0, 1, 2]], [3, [1]], [3, [0, 1]], [3, [0, 1, 2]]], TableGrid::layout([[['rowspan' => 2], 'b', ['rowspan' => 3]], ['e'], ['f', 'g'], ['h', 'i', 'j']]));
        self::assertSame([[2, [0, 1]], [3, [0]], [4, [3]], [4, [3]], [4, [3]], [1, [0]]], TableGrid::layout([['a', ['rowspan' => 3]], [['colspan' => 3, 'rowspan' => 4]], ['x'], ['y'], ['z'], ['w']]));
        self::assertSame(1, TableGrid::span(['colspan' => '2'], 'colspan'));
        self::assertSame(1, TableGrid::span(['colspan' => 0], 'colspan'));
        self::assertSame(3, TableGrid::span(['rowspan' => 3], 'rowspan'));
        self::normalize('table', ['content' => [[['text' => 'a', 'rowspan' => 2], 'b'], ['c']]]);
        self::normalize('table', ['content' => [[['text' => 'a', 'colspan' => 3]], ['b', 'c', 'd']]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.content[1]: every row must have as many cells as the first.', 'table', ['content' => [[['text' => 'a', 'rowspan' => 2], 'b'], ['c', 'd']]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.align: there are more alignments than columns.', 'table', ['align' => ['left', 'left'], 'content' => [['a']]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.align: there are more alignments than columns.', 'table', ['align' => ['left']]);
        self::assertSame(['left'], self::normalize('table', ['align' => ['left'], 'content' => [['a']]])->data['align']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.content[0][0]: an object is expected.', 'table', ['content' => [[1]]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.content[0][0].text: this value is required.', 'table', ['content' => [[['colspan' => 2]]]]);
    }

    public function testATableHasRowsOfOneLength(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.content[2]: every row must have as many cells as the first.', 'table', ['content' => [['a', 'b'], ['c', 'd'], ['e']]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.content[1]: every row must have as many cells as the first.', 'table', ['content' => [['a'], ['c', 'd']]]);
    }

    public function testAnEmbedFramesAnHttpsAddressOnAHostOfItsService(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.embed: the address is not on a host of youtube.', 'embed', [...self::EMBED, 'embed' => 'https://player.vimeo.com/video/1']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.embed: an address in https is expected.', 'embed', [...self::EMBED, 'embed' => 'http://www.youtube.com/embed/x']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.service: one of "youtube", "vimeo", "facebook", "instagram", "twitter", "twitch-video", "twitch-channel", "miro", "gfycat", "imgur", "vine", "aparat", "yandex-music-track", "yandex-music-album", "yandex-music-playlist", "coub", "codepen", "pinterest", "github", "reddit", "figma", "whimsical" is expected.', 'embed', [...self::EMBED, 'service' => 'myspace']);
    }

    public function testAddressesFollowTheirField(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.link: an address in http, https is expected.', 'linkTool', ['link' => '/relative']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.source: an address in http, https is expected.', 'embed', [...self::EMBED, 'source' => '/x']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.link: an address in http, https is expected.', 'personality', ['name' => 'n', 'link' => '/x']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.file.url: an address in http, https, or relative, is expected.', 'image', ['file' => ['url' => 'javascript:alert(1)']]);
        self::assertSame('/x', self::normalize('personality', ['name' => 'n', 'photo' => '/x'])->data['photo']);
    }

    public function testRichTextIsSanitized(): void
    {
        self::assertSame(['text' => '<b>a</b>'], self::normalize('paragraph', ['text' => '<strong onclick="x">a</strong><script>b</script>'])->data);
        self::assertSame(['html' => '<p>a</p><img src="/i.png">'], self::normalize('raw', ['html' => '<p class="x">a</p><img src="/i.png" onerror="x"><img src="javascript:x">'])->data);
        self::assertSame(['code' => '<b>a</b>'], self::normalize('code', ['code' => '<b>a</b>'])->data);
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, array<array-key, mixed>, array<string, mixed>}>
     */
    public static function upgrades(): iterable
    {
        yield 'alignment of a paragraph' => ['paragraph', ['text' => 't', 'alignment' => 'justify'], 'paragraph', ['text' => 't'], ['alignment' => ['alignment' => 'justify']]];
        yield 'alignment and anchor of a header' => ['header', ['text' => 't', 'level' => 1, 'alignment' => 'right', 'anchor' => 'intro'], 'header', ['text' => 't', 'level' => 1], ['alignment' => ['alignment' => 'right'], 'anchor' => ['anchor' => 'intro']]];
        yield 'list 1' => ['list', ['style' => 'ordered', 'items' => ['a', 'b']], 'list', ['style' => 'ordered', 'meta' => [], 'items' => [['content' => 'a', 'meta' => [], 'items' => []], ['content' => 'b', 'meta' => [], 'items' => []]]], []];
        yield 'checklist' => ['checklist', ['items' => [['text' => 'a', 'checked' => true], ['text' => 'b']]], 'list', ['style' => 'checklist', 'meta' => [], 'items' => [['content' => 'a', 'meta' => ['checked' => true], 'items' => []], ['content' => 'b', 'meta' => ['checked' => false], 'items' => []]]], []];
        yield 'nested checklist' => ['list', ['style' => 'none', 'items' => [['content' => 'a', 'checked' => null, 'items' => [['content' => 'b', 'checked' => true]]]]], 'list', ['style' => 'checklist', 'meta' => [], 'items' => [['content' => 'a', 'meta' => ['checked' => false], 'items' => [['content' => 'b', 'meta' => ['checked' => true], 'items' => []]]]]], []];
        yield 'line numbers of a code block' => ['code', ['code' => 'x', 'showlinenumbers' => true], 'code', ['code' => 'x'], []];
        yield 'simple image' => ['image', ['url' => '/a.png', 'caption' => 'c', 'withBorder' => true], 'image', ['file' => ['url' => '/a.png'], 'caption' => 'c', 'withBorder' => true, 'withBackground' => false, 'stretched' => false], []];
        yield 'link' => ['link', ['link' => 'https://a.example'], 'linkTool', ['link' => 'https://a.example', 'meta' => []], []];
        yield 'message of an alert' => ['alert', ['message' => 'm'], 'alert', ['type' => 'info', 'align' => 'left', 'text' => 'm'], []];
        yield 'status of a toggle' => ['toggle', ['text' => 't', 'status' => 'closed', 'fk' => 'f'], 'toggle', ['text' => 't', 'open' => false, 'blocks' => []], []];
        yield 'open status of a toggle' => ['toggle', ['text' => 't', 'status' => 'open'], 'toggle', ['text' => 't', 'open' => true, 'blocks' => []], []];
        yield 'accordion' => ['accordion', ['title' => 'T', 'settings' => ['defaultExpanded' => true]], 'toggle', ['text' => 'T', 'open' => true, 'blocks' => []], []];
        yield 'calumk columns' => ['columns', ['cols' => [['time' => 1, 'blocks' => [], 'version' => '2']]], 'columns', ['cols' => [['blocks' => []]]], []];
        yield 'LaTeX' => ['Math', ['math' => 'x^2'], 'math', ['latex' => 'x^2'], []];
        yield 'button' => ['AnyButton', ['link' => '/a', 'text' => 'b'], 'button', ['link' => '/a', 'text' => 'b'], []];
        yield 'carousel' => ['carousel', [['url' => '/a.png', 'caption' => 'c']], 'gallery', ['files' => [['url' => '/a.png', 'caption' => 'c']], 'style' => 'slider'], []];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<array-key, mixed> $expected
     * @param array<string, mixed>    $tunes
     */
    #[DataProvider('upgrades')]
    public function testOlderAndThirdPartyFormsAreRead(string $type, array $data, string $canonical, array $expected, array $tunes): void
    {
        $block = self::normalize($type, $data);

        self::assertSame($canonical, $block->type);
        self::assertSame($expected, $block->data);
        self::assertSame($tunes, $block->tunes);
    }

    public function testUpgradesLeaveTheCanonicalFormAlone(): void
    {
        self::assertInvalid('The UJO document is invalid at blocks[0].data.title: this value is not part of the data.', 'toggle', ['text' => 't', 'title' => 'u']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.message: this value is not part of the data.', 'alert', ['text' => 't', 'message' => 'm']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.math: this value is not part of the data.', 'math', ['latex' => 'a', 'math' => 'b']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.url: this value is not part of the data.', 'image', ['file' => ['url' => '/a'], 'url' => '/b']);
        self::assertSame(['style' => 'checklist', 'meta' => [], 'items' => [['content' => 'a', 'meta' => [], 'items' => []]]], self::normalize('list', ['style' => 'checklist', 'items' => [['content' => 'a']]])->data);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.items[0]: an object is expected.', 'list', ['style' => 'unordered', 'items' => [1]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.items[0]: an object is expected.', 'checklist', ['items' => [1]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.items[0].items[0]: an object is expected.', 'list', ['style' => 'none', 'items' => [['content' => 'a', 'checked' => true, 'items' => [1]]]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.items[1]: an object is expected.', 'list', ['style' => 'ordered', 'items' => ['a', 2]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.cols[0]: an object is expected.', 'columns', ['cols' => [1]]);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.cols: a list is expected.', 'columns', ['cols' => 'x']);
        self::assertInvalid('The UJO document is invalid at blocks[0].data.files: this value is required.', 'carousel', []);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function read(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            self::assertIsArray($data);
            $data = $data[$key] ?? null;
        }

        return $data;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function list(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }
}
