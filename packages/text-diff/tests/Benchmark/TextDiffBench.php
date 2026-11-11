<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Benchmark;

use Generator;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * Two versions are compared each time an editor opens the history of a page. Texts of 1, 10 and 50 KB with a few edits
 * cost time in proportion to their length; texts rewritten through stop at the bound of edits; a Chinese text, written
 * without spaces, is compared character by character; documents of 100 and 1 000 blocks are compared block by block.
 *
 * @BeforeMethods("setUp")
 */
final class TextDiffBench
{
    private readonly TextDiff $diff;

    private string $old = '';

    private string $new = '';

    /** The difference written, kept so that writing it is not dropped as unused. */
    public string $html = '';

    private Document $oldDocument;

    private Document $newDocument;

    public function __construct()
    {
        $this->diff = new TextDiff();
        $this->oldDocument = new Document();
        $this->newDocument = new Document();
    }

    /**
     * @param array{shape: string, size: int} $params
     */
    public function setUp(array $params): void
    {
        $han = 'han' === $params['shape'];
        $words = intdiv($params['size'], 6);
        $old = [];

        for ($i = 0; $i < $words; ++$i) {
            $old[] = $han ? mb_chr(0x4E00 + $i % 97).mb_chr(0x4F00 + $i % 89) : 'word'.($i % 97);
        }

        $new = $old;

        for ($i = 0; $i < $words; $i += 'rewritten' === $params['shape'] ? 1 : 50) {
            $new[$i] = $han ? '他' : 'other';
        }

        $this->old = implode($han ? '' : ' ', $old);
        $this->new = implode($han ? '' : ' ', $new);
        $this->oldDocument = $this->document($params['size'], 0);
        $this->newDocument = $this->document($params['size'], 10);
    }

    /**
     * @ParamProviders("texts")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchWords(): void
    {
        $this->html = $this->diff->toHtml($this->diff->words($this->old, $this->new));
    }

    /**
     * @ParamProviders("documents")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchDocuments(): void
    {
        $this->diff->documents($this->oldDocument, $this->newDocument);
    }

    /**
     * @return Generator<string, array{shape: string, size: int}>
     */
    public function texts(): Generator
    {
        foreach ([1_000, 10_000, 50_000] as $bytes) {
            yield \sprintf('a few edits, %d KB', intdiv($bytes, 1000)) => ['shape' => 'edited', 'size' => $bytes];
        }

        yield 'Chinese, a few edits, 10 KB' => ['shape' => 'han', 'size' => 10_000];
        yield 'rewritten, 10 KB' => ['shape' => 'rewritten', 'size' => 10_000];
        yield 'rewritten, 50 KB' => ['shape' => 'rewritten', 'size' => 50_000];
    }

    /**
     * @return Generator<string, array{shape: string, size: int}>
     */
    public function documents(): Generator
    {
        yield '100 blocks' => ['shape' => 'edited', 'size' => 100];
        yield '1 000 blocks' => ['shape' => 'edited', 'size' => 1000];
    }

    private function document(int $blocks, int $every): Document
    {
        $list = [];

        for ($i = 0; $i < $blocks; ++$i) {
            $edited = 0 !== $every && 0 === $i % $every;
            $list[] = new Block('b'.$i, 'paragraph', ['text' => ($edited ? 'Changed ' : 'Some ').'text of block '.$i.' with a few more words.']);
        }

        return new Document($list);
    }
}
