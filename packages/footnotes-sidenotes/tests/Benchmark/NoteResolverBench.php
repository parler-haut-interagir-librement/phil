<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Benchmark;

use Generator;
use Phil\FootnotesSidenotes\Html\NotesRenderer;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\Resolution;
use Phil\FootnotesSidenotes\Text\NotesPlainText;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;

/**
 * A book is hundreds of notes in thousands of blocks, resolved each time a page or a PDF is produced: the walk and the
 * rewriting of the rendered HTML must grow with the document, not with its square.
 *
 * @Groups({"footnotes-sidenotes"})
 *
 * @Revs(1)
 *
 * @Iterations(3)
 */
final class NoteResolverBench
{
    private readonly NoteResolver $resolver;

    private readonly NotesRenderer $renderer;

    /** @var array<int, array{Document, Resolution}> */
    private array $books = [];

    public function __construct()
    {
        $this->resolver = new NoteResolver();
        $this->renderer = new NotesRenderer(HtmlRenderer::default());

        foreach ([100, 1000, 5000] as $notes) {
            $document = $this->book($notes);
            $this->books[$notes] = [$document, $this->resolver->resolve($document)];
        }
    }

    /**
     * @param array{notes: int} $params
     *
     * @ParamProviders("sizes")
     */
    public function benchResolve(array $params): void
    {
        $this->resolver->resolve($this->books[$params['notes']][0]);
    }

    /**
     * @param array{notes: int} $params
     *
     * @ParamProviders("sizes")
     */
    public function benchRenderEndnotes(array $params): void
    {
        $this->renderer->render($this->books[$params['notes']][0], $this->books[$params['notes']][1], Placement::EndOfDocument, 'book');
    }

    /**
     * @param array{notes: int} $params
     *
     * @ParamProviders("sizes")
     */
    public function benchRenderPageFoot(array $params): void
    {
        $this->renderer->render($this->books[$params['notes']][0], $this->books[$params['notes']][1], Placement::PageFoot, 'book');
    }

    /**
     * @param array{notes: int} $params
     *
     * @ParamProviders("sizes")
     */
    public function benchPlainText(array $params): void
    {
        new NotesPlainText()->convert($this->books[$params['notes']][0], $this->books[$params['notes']][1]);
    }

    /**
     * @return Generator<string, array{notes: int}>
     */
    public function sizes(): Generator
    {
        yield '100 notes' => ['notes' => 100];
        yield '1000 notes' => ['notes' => 1000];
        yield '5000 notes' => ['notes' => 5000];
    }

    private function book(int $notes): Document
    {
        $blocks = [];
        $bodies = [];

        for ($index = 0; $index < $notes; ++$index) {
            $blocks[] = new Block('p'.$index, 'paragraph', ['text' => \sprintf('A paragraph of an ordinary length, long enough to look like a page of a book, with a reference<sup data-footnote="fn-%d">x</sup> and some more words after it.', $index)]);
            $blocks[] = new Block('q'.$index, 'paragraph', ['text' => 'A paragraph without any reference, as most of them are in a book of any size at all.']);
            $bodies[] = new Block('n'.$index, 'footnote', ['id' => 'fn-'.$index, 'text' => 'The text of a note with <b>some</b> markup and a few words.']);
        }

        return new Document([...$blocks, ...$bodies]);
    }
}
