<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Benchmark;

use Generator;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;

/**
 * A UJO document is decoded and checked each time an author saves, and rendered to HTML each time it is published.
 * Each step must cost time in proportion to the number of blocks: documents of 10, 100 and 1 000 blocks mixing every
 * common type, and a hostile paragraph of inline tags nested deep, are read, checked and rendered. A document is
 * rendered as the normalizer returned it, which is not checked again.
 *
 * @BeforeMethods("setUp")
 */
final class UjoBench
{
    private readonly Ujo $ujo;

    private string $json = '';

    private Document $document;

    private Document $unchecked;

    public function __construct()
    {
        $this->ujo = new Ujo();
        $this->document = new Document();
        $this->unchecked = new Document();
    }

    /**
     * @param array{blocks: int, shape: string} $params
     */
    public function setUp(array $params): void
    {
        $document = [
            'time' => 1_700_000_000_000,
            'version' => '2.31.0',
            'blocks' => 'hostile' === $params['shape'] ? $this->hostile($params['blocks']) : $this->blocks($params['blocks']),
        ];
        $this->json = json_encode($document, \JSON_THROW_ON_ERROR);
        $this->document = $this->ujo->decode($this->json);
        $this->unchecked = Document::fromArray($document);
    }

    /**
     * @ParamProviders("documents")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchDecode(): void
    {
        $this->ujo->decode($this->json);
    }

    /**
     * @ParamProviders("documents")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchValidate(): void
    {
        $this->ujo->normalize($this->unchecked);
    }

    /**
     * @ParamProviders("documents")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchRender(): void
    {
        $this->ujo->toHtml($this->document);
    }

    /**
     * @return Generator<string, array{blocks: int, shape: string}>
     */
    public function documents(): Generator
    {
        yield '10 blocks' => ['blocks' => 10, 'shape' => 'mixed'];
        yield '100 blocks' => ['blocks' => 100, 'shape' => 'mixed'];
        yield '1 000 blocks' => ['blocks' => 1000, 'shape' => 'mixed'];
        yield 'inline tags 1 000 deep' => ['blocks' => 1000, 'shape' => 'hostile'];
        yield 'inline tags 5 000 deep' => ['blocks' => 5000, 'shape' => 'hostile'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function blocks(int $count): array
    {
        $blocks = [['id' => 'toc', 'type' => 'toc', 'data' => []]];
        for ($i = 0; $i < $count; ++$i) {
            $blocks[] = match ($i % 6) {
                0 => ['id' => 'h'.$i, 'type' => 'header', 'data' => ['text' => 'Section <i>'.$i.'</i>', 'level' => 2]],
                1 => ['id' => 'p'.$i, 'type' => 'paragraph', 'data' => ['text' => str_repeat('Some <b>bold</b> words, a <a href="https://example.com/'.$i.'">link</a> and <i>more</i>. ', 5)]],
                2 => ['id' => 'l'.$i, 'type' => 'list', 'data' => ['style' => 'unordered', 'items' => [
                    ['content' => 'one', 'items' => [['content' => 'one.one', 'items' => [['content' => 'deep']]]]],
                    ['content' => '<b>two</b>'],
                ]]],
                3 => ['id' => 't'.$i, 'type' => 'table', 'data' => ['withHeadings' => true, 'content' => array_fill(0, 5, ['a', '<b>b</b>', 'c', 'd', 'e'])]],
                4 => ['id' => 'c'.$i, 'type' => 'code', 'data' => ['code' => "if (a < b) {\n    return a;\n}", 'language' => 'php']],
                default => ['id' => 'q'.$i, 'type' => 'quote', 'data' => ['text' => 'Said <i>well</i>', 'caption' => 'Ada']],
            };
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hostile(int $depth): array
    {
        return [['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => str_repeat('<b><i>', $depth).'x']]];
    }
}
