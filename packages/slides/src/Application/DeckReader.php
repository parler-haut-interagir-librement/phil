<?php

declare(strict_types=1);

namespace Phil\Slides\Application;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Exception\InvalidSource;
use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\Level;
use Phil\Markup\MarkupFormat;
use Phil\Markup\Reader\Reading;
use Phil\Slides\Domain\Exception\DeckRefused;
use Phil\Slides\Domain\Exception\InvalidOption;
use Phil\Slides\Domain\Slicer;
use Phil\Slides\Domain\ValueObject\Deck;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;

/**
 * Decks from what an application keeps: a UJO document or its JSON, what `phil/markup` read of a source, or the source
 * itself — Markdown by default — read by `phil/markup`, whose metadata are the global directives. The separator
 * `---`, the front matter and the `:::` containers are read by `phil/markup` alone; this class only cuts the document.
 */
final readonly class DeckReader
{
    public const int DEFAULT_MAX_SOURCE_BYTES = 1_048_576;

    private DefaultCompiler $compiler;

    public function __construct(
        private Slicer $slicer,
        private Ujo $ujo,
        ?DefaultCompiler $compiler = null,
        private Level $level = Level::All,
        private int $maxSourceBytes = self::DEFAULT_MAX_SOURCE_BYTES,
    ) {
        $this->compiler = $compiler ?? new DefaultCompiler();

        if ($maxSourceBytes < 1) {
            throw InvalidOption::because(\sprintf('A source is read up to at least one byte, not %d.', $maxSourceBytes));
        }
    }

    /**
     * The deck of a document, or of its UJO JSON decoded and checked first.
     *
     * @param array<array-key, mixed> $metadata the global directives
     *
     * @throws UjoException when the JSON is not a valid document
     * @throws DeckRefused
     */
    public function fromDocument(Document|string $document, array $metadata = []): Deck
    {
        return $this->slicer->slice(\is_string($document) ? $this->ujo->decode($document) : $document, $metadata);
    }

    /**
     * The deck of what `phil/markup` read: its document, its metadata as the global directives.
     *
     * @throws DeckRefused
     */
    public function fromReading(Reading $reading): Deck
    {
        return $this->slicer->slice($reading->document, $reading->metadata);
    }

    /**
     * The deck of a source, read by `phil/markup` in its format at the configured level.
     *
     * @throws UnknownFormat when `phil/markup` knows no format of that name
     * @throws InvalidSource when a source in a format with rules breaks them
     * @throws DeckRefused   when the source is too large, or makes a deck past the limits
     */
    public function fromMarkup(string $source, MarkupFormat|string $format = MarkupFormat::Markdown): Deck
    {
        if (\strlen($source) > $this->maxSourceBytes) {
            throw DeckRefused::sourceTooLarge(\strlen($source), $this->maxSourceBytes);
        }

        return $this->fromReading($this->compiler->reader($format, $this->level)->read($source));
    }
}
