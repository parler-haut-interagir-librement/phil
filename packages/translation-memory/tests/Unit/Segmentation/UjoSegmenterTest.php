<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit\Segmentation;

use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\SentenceSplitter;
use Phil\TranslationMemory\Segmentation\TranslatableFields;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\SourceSegment;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoSegmenter::class)]
#[CoversClass(TranslatableFields::class)]
#[CoversClass(Granularity::class)]
final class UjoSegmenterTest extends TestCase
{
    public function testCutsEachTextFieldOfEachBlockInReadingOrder(): void
    {
        self::assertSame([
            'b1/text' => 'A <b>title</b>',
            'b2/text' => 'One. Two.',
            'b3/items.0.content' => 'First',
            'b3/items.0.items.0.content' => 'Nested',
            'b4/content.0.0' => 'Cell',
            'b4/content.1.1' => 'Other',
            'b5/caption' => 'Picture',
            'c1/text' => 'In a column',
            'b7/text' => 'After',
        ], $this->pieces(new UjoSegmenter()->segment($this->document())));
    }

    public function testCutsEachFieldBySentence(): void
    {
        $pieces = $this->pieces(new UjoSegmenter(Granularity::Sentence)->segment($this->document()));

        self::assertSame(['b1/text#1', 'b2/text#1', 'b2/text#2', 'b3/items.0.content#1'], \array_slice(array_keys($pieces), 0, 4));
        self::assertSame(['One.', 'Two.'], [$pieces['b2/text#1'], $pieces['b2/text#2']]);
    }

    public function testTakesItsOwnFieldsAndTypes(): void
    {
        $segmenter = new UjoSegmenter(fields: new TranslatableFields(['code', 'url'], ['paragraph']));
        $document = new Document([
            new Block('p', 'paragraph', ['text' => 'Hello', 'url' => 'x']),
            new Block('c', 'code', ['code' => 'echo 1;']),
            new Block('e', 'embed', ['url' => 'https://example.com', 'caption' => 'No']),
        ]);

        self::assertSame(['c/code' => 'echo 1;', 'e/url' => 'https://example.com'], $this->pieces($segmenter->segment($document)));
        self::assertTrue(new TranslatableFields()->isTranslated('paragraph'));
        self::assertFalse(new TranslatableFields()->isTranslated('code'));
        self::assertTrue(new TranslatableFields()->holdsWords('text'));
        self::assertFalse(new TranslatableFields()->holdsWords(null));
    }

    public function testTakesItsOwnSplitter(): void
    {
        $segmenter = new UjoSegmenter(Granularity::Sentence, splitter: new SentenceSplitter([]));
        $document = new Document([new Block('p', 'paragraph', ['text' => 'Ask Dr. Who. Now'])]);

        self::assertSame(['p/text#1' => 'Ask Dr.', 'p/text#2' => 'Who.', 'p/text#3' => 'Now'], $this->pieces($segmenter->segment($document)));
    }

    public function testLeavesOutKeysThatCannotBeNamedAndValuesTooDeep(): void
    {
        $deep = 'Deep';

        for ($level = 0; $level < 15; ++$level) {
            $deep = [$deep];
        }

        $document = new Document([
            new Block('p', 'paragraph', ['text' => 'Kept', 'te.xt' => ['text' => 'No'], 'title' => ['a b' => 'No'], 'content' => $deep, 'name' => [$deep], 'label' => 7]),
            new Block('c', 'code', ['code' => 'x', 'caption' => 'Code']),
        ]);
        $segmenter = new UjoSegmenter();
        $deepKey = 'p/content.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0';

        self::assertSame(['p/text' => 'Kept', $deepKey => 'Deep'], $this->pieces($segmenter->segment($document)));

        $translated = $segmenter->assemble($document, [
            new Segment('p/text', 'Kept')->translate('Gardé'),
            new Segment('p/te.xt.text', 'No')->translate('Non'),
            new Segment('p/title.a b', 'No')->translate('Non'),
            new Segment($deepKey, 'Deep')->translate('Profond'),
            new Segment('p/name.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0', 'Deep')->translate('Profond'),
            new Segment('c/caption', 'Code')->translate('Le code'),
        ]);
        $data = $translated->blocks[0]->data;
        $flat = json_encode([$data, $translated->blocks[1]->data], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        self::assertSame('Gardé', $data['text']);
        self::assertSame(['text' => 'No'], $data['te.xt']);
        self::assertSame(['a b' => 'No'], $data['title']);
        self::assertSame(7, $data['label']);
        self::assertSame(1, substr_count($flat, 'Profond'));
        self::assertStringContainsString('"caption":"Code"', $flat);
        self::assertStringContainsString('[[[[[[[[[[[[[[["Profond"]]]]]]]]]]]]]]]', $flat);
    }

    public function testPutsTranslatedSegmentsBackInTheDocument(): void
    {
        $segmenter = new UjoSegmenter();
        $segments = $this->translate($segmenter->segment($this->document()), ['b1/text' => 'Un <b>titre</b>', 'b3/items.0.items.0.content' => 'Imbriqué', 'c1/text' => 'Dans une colonne', 'b4/content.1.1' => 'Autre']);
        $translated = $segmenter->assemble($this->document(), $segments);

        self::assertSame('Un <b>titre</b>', $translated->blocks[0]->data['text'] ?? null);
        self::assertSame('One. Two.', $translated->blocks[1]->data['text'] ?? null);
        self::assertSame([['content' => 'First', 'items' => [['content' => 'Imbriqué', 'items' => []]]]], $translated->blocks[2]->data['items'] ?? null);
        self::assertSame([['Cell', ''], ['', 'Autre']], $translated->blocks[3]->data['content'] ?? null);
        self::assertSame('Dans une colonne', $translated->find('c1')?->data['text'] ?? null);
        self::assertSame(['cols'], array_keys($translated->find('b6')->data ?? []));
        self::assertSame('After', $translated->blocks[6]->data['text'] ?? null);
    }

    public function testUsesOnlyTheAcceptedStatesAndTheSameSource(): void
    {
        $segmenter = new UjoSegmenter();
        $document = new Document([
            new Block('a', 'paragraph', ['text' => 'One']),
            new Block('b', 'paragraph', ['text' => 'Two']),
            new Block('c', 'paragraph', ['text' => 'Three']),
            new Block('d', 'paragraph', ['text' => 'Four']),
        ]);
        $segments = [
            new Segment('a/text', 'One')->translate('Un'),
            new Segment('b/text', 'Two')->suggest('Deux'),
            new Segment('c/text', 'Trois?')->translate('Trois'),
            new Segment('d/text', 'Four')->translate('Quatre')->review(),
        ];

        self::assertSame(['Un', 'Two', 'Three', 'Quatre'], $this->texts($segmenter->assemble($document, $segments)));
        self::assertSame(['One', 'Deux', 'Three', 'Four'], $this->texts($segmenter->assemble($document, $segments, [SegmentState::Suggested])));
        self::assertSame(['One', 'Two', 'Three', 'Four'], $this->texts($segmenter->assemble($document, [new Segment('a/text', 'One')], [SegmentState::Untranslated])));
    }

    public function testPutsSentencesBackWithTheirWhiteSpace(): void
    {
        $segmenter = new UjoSegmenter(Granularity::Sentence);
        $document = new Document([new Block('p', 'paragraph', ['text' => "  One.  Two. <br> Three! \n"]), new Block('q', 'paragraph', ['text' => ' <br> '])]);
        $segments = $this->translate($segmenter->segment($document), ['p/text#1' => 'Un.', 'p/text#3' => '<br> Trois !']);

        self::assertSame(['p/text#1', 'p/text#2', 'p/text#3'], array_map(static fn (Segment $segment): string => $segment->key, $segments));
        self::assertSame(["  Un.  Two. <br> Trois ! \n", ' <br> '], $this->texts($segmenter->assemble($document, $segments)));
    }

    public function testATextWithoutWordsMakesNoSegmentAndStaysAsItIs(): void
    {
        $segmenter = new UjoSegmenter();
        $document = new Document([new Block('p', 'paragraph', ['text' => ' <br> '])]);

        self::assertSame([], $segmenter->segment($document));
        self::assertSame([' <br> '], $this->texts($segmenter->assemble($document, [new Segment('p/text', ' <br> ', SegmentState::Translated, 'x')])));
        self::assertSame(Granularity::Block, $segmenter->granularity);
    }

    private function document(): Document
    {
        return new Document([
            new Block('b1', 'header', ['text' => 'A <b>title</b>', 'level' => 2]),
            new Block('b2', 'paragraph', ['text' => 'One. Two.']),
            new Block('b3', 'list', ['style' => 'unordered', 'items' => [['content' => 'First', 'items' => [['content' => 'Nested', 'items' => []]]]]]),
            new Block('b4', 'table', ['withHeadings' => false, 'content' => [['Cell', ''], ['', 'Other']]]),
            new Block('b5', 'image', ['file' => ['url' => 'https://example.com/a.png'], 'caption' => 'Picture']),
            new Block('b6', 'columns', ['cols' => [['blocks' => [new Block('c1', 'paragraph', ['text' => 'In a column'])]]]]),
            new Block('b7', 'paragraph', ['text' => 'After']),
            new Block('b8', 'code', ['code' => 'echo "text";']),
        ]);
    }

    /**
     * @param list<SourceSegment>   $pieces
     * @param array<string, string> $targets
     *
     * @return list<Segment>
     */
    private function translate(array $pieces, array $targets): array
    {
        return array_map(static function (SourceSegment $piece) use ($targets): Segment {
            $segment = Segment::fromSource($piece);

            return isset($targets[$piece->key]) ? $segment->translate($targets[$piece->key]) : $segment;
        }, $pieces);
    }

    /**
     * @param list<SourceSegment> $pieces
     *
     * @return array<string, string>
     */
    private function pieces(array $pieces): array
    {
        $texts = [];

        foreach ($pieces as $piece) {
            $texts[$piece->key] = $piece->text;
        }

        return $texts;
    }

    /**
     * @return list<mixed>
     */
    private function texts(Document $document): array
    {
        return array_map(static fn (Block $block): mixed => $block->data['text'] ?? null, $document->blocks);
    }
}
