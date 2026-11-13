<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Benchmark;

use Generator;
use Phil\CommentsBundle\Application\UjoPassages;
use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Anchoring\Reanchoring;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\Ujo\Block;
use Phil\Ujo\Document;

/**
 * Every save of a document moves each of its threads: a passage found again in a document of a hundred blocks must
 * cost well under a millisecond, and a text of hostile shape — a field of a hundred thousand characters where the
 * passage occurs at every place, overlapping, or nowhere — a time bounded by its length.
 *
 * @BeforeMethods("setUp")
 */
final class AnchoringBench
{
    private Anchor $anchor;

    /** @var list<Passage> */
    private array $passages = [];

    private Document $document;

    public function __construct()
    {
        $this->anchor = Anchor::of('b50', 'text', 10, 'the passage');
        $this->document = new Document();
    }

    /**
     * @param array{shape: string} $params
     */
    public function setUp(array $params): void
    {
        $this->document = new Document(array_map(
            static fn (int $i): Block => new Block('b'.$i, 'paragraph', ['text' => 'Paragraph '.$i.' with <b>the passage</b> &amp; some <i>words</i> around it.']),
            range(1, 100),
        ));
        [$this->anchor, $this->passages] = match ($params['shape']) {
            'document' => [Anchor::of('b50', 'text', 13, 'the passage', 'Paragraph 50 with ', ' & some words'), UjoPassages::of($this->document)],
            'moved' => [Anchor::of('b999', 'text', 13, 'the passage', 'Paragraph 999 with ', ' & some words'), UjoPassages::of($this->document)],
            'gone' => [Anchor::of('b50', 'text', 13, 'a passage nobody wrote'), UjoPassages::of($this->document)],
            'overlapping' => [Anchor::of(null, null, 50_000, 'aaaa', 'aaaa', 'aaaa'), [new Passage(null, null, str_repeat('a', 100_000))]],
            default => [Anchor::of(null, null, 50_000, 'é ', 'é é', 'é é'), [new Passage(null, null, str_repeat('é ', 50_000))]],
        };
    }

    /**
     * @ParamProviders("shapes")
     *
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchLocate(): void
    {
        Reanchoring::locate($this->anchor, $this->passages);
    }

    /**
     * @ParamProviders("document")
     *
     * @Revs(100)
     *
     * @Iterations(3)
     */
    public function benchPassagesOfADocument(): void
    {
        UjoPassages::of($this->document);
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function shapes(): Generator
    {
        foreach (['document', 'moved', 'gone', 'overlapping', 'multibyte'] as $shape) {
            yield $shape => ['shape' => $shape];
        }
    }

    /**
     * @return Generator<string, array{shape: string}>
     */
    public function document(): Generator
    {
        yield 'document' => ['shape' => 'document'];
    }
}
