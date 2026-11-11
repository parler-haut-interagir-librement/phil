<?php

declare(strict_types=1);

namespace Phil\TextDiff;

use Phil\TextDiff\Exception\InvalidConfiguration;
use Phil\TextDiff\Exception\InvalidText;
use Phil\TextDiff\Ujo\DocumentDiff;
use Phil\TextDiff\Ujo\DocumentDiffer;
use Phil\TextDiff\Ujo\DocumentFormatter;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\UnknownBlockType;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;

/**
 * The library in one object: two texts compared by words, lines, paragraphs or HTML, two UJO documents compared block
 * by block, and either difference written as HTML.
 *
 * `$maxEdits` bounds the search of each comparison: two texts that differ by more tokens than that, once their common
 * start and end are set aside, are shown as the old middle deleted and the new one inserted. `$normalizer` checks each
 * block of a document difference before it is rendered as HTML: give the one the application reads its documents with,
 * so that it knows the same types.
 */
final readonly class TextDiff
{
    private Myers $myers;

    private HtmlFormatter $formatter;

    private Normalizer $normalizer;

    /**
     * @throws InvalidConfiguration when `$maxEdits` is negative
     */
    public function __construct(int $maxEdits = 1000, ?Normalizer $normalizer = null)
    {
        if ($maxEdits < 0) {
            throw InvalidConfiguration::maxEdits($maxEdits);
        }

        $this->myers = new Myers($maxEdits);
        $this->formatter = new HtmlFormatter();
        $this->normalizer = $normalizer ?? new Normalizer();
    }

    /**
     * @throws InvalidText when a text is not valid UTF-8
     */
    public function compare(string $old, string $new, Tokenizer $tokenizer = Tokenizer::Words): Diff
    {
        return new Diff($this->myers->diff($tokenizer->tokens($old), $tokenizer->tokens($new)), $tokenizer);
    }

    /**
     * @throws InvalidText when a text is not valid UTF-8
     */
    public function words(string $old, string $new): Diff
    {
        return $this->compare($old, $new, Tokenizer::Words);
    }

    /**
     * @throws InvalidText when a text is not valid UTF-8
     */
    public function lines(string $old, string $new): Diff
    {
        return $this->compare($old, $new, Tokenizer::Lines);
    }

    /**
     * @throws InvalidText when a text is not valid UTF-8
     */
    public function paragraphs(string $old, string $new): Diff
    {
        return $this->compare($old, $new, Tokenizer::Paragraphs);
    }

    /**
     * Two HTML texts compared by words, their tags never cut.
     *
     * @throws InvalidText when a text is not valid UTF-8
     */
    public function html(string $old, string $new): Diff
    {
        return $this->compare($old, $new, Tokenizer::Html);
    }

    /**
     * Two UJO documents compared block by block, by block identifier, then field by field inside each block kept.
     *
     * @throws InvalidText when a text field is not valid UTF-8
     */
    public function documents(Document $old, Document $new): DocumentDiff
    {
        return new DocumentDiffer($this, $this->myers)->diff($old, $new);
    }

    /**
     * A difference of texts as HTML with `<ins>` and `<del>`.
     */
    public function toHtml(Diff $diff): string
    {
        return $this->formatter->format($diff);
    }

    /**
     * A difference of documents as HTML: each block normalized, then rendered by `phil/ujo` — by `$renderer`, or its
     * default renderer — in a `<div>` that says what happened to it, changed blocks marked word by word.
     *
     * @throws InvalidDocument  when a block breaks its type
     * @throws UnknownBlockType when the normalizer or the renderer does not know a block's type
     */
    public function documentToHtml(DocumentDiff $diff, ?HtmlRenderer $renderer = null): string
    {
        return new DocumentFormatter($this, $renderer ?? HtmlRenderer::default(), $this->normalizer)->format($diff);
    }
}
