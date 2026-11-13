<?php

declare(strict_types=1);

namespace Phil\Citations;

/**
 * A formatted citation or bibliography entry: runs of text, some in italics, given as plain text or as HTML whose
 * text is escaped, so it can be written in a page as it is.
 */
final readonly class RichText
{
    /**
     * @param list<array{string, bool}> $runs each text, and whether it is in italics
     */
    private function __construct(
        private array $runs,
    ) {
    }

    public static function text(string $text): self
    {
        return new self('' === $text ? [] : [[$text, false]]);
    }

    public static function italic(string $text): self
    {
        return new self('' === $text ? [] : [[$text, true]]);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * The parts one after the other, a string taken as plain text.
     */
    public static function concat(self|string ...$parts): self
    {
        $runs = [];

        foreach ($parts as $part) {
            array_push($runs, ...($part instanceof self ? $part : self::text($part))->runs);
        }

        return new self($runs);
    }

    /**
     * The parts that are not empty, with the separator between them.
     *
     * @param iterable<self|string> $parts
     */
    public static function join(string $separator, iterable $parts): self
    {
        $joined = [];

        foreach ($parts as $part) {
            $part = $part instanceof self ? $part : self::text($part);

            if (!$part->isEmpty()) {
                $joined[] = [] === $joined ? $part : self::concat($separator, $part);
            }
        }

        return self::concat(...$joined);
    }

    public function isEmpty(): bool
    {
        return [] === $this->runs;
    }

    /**
     * The text ended by a period, unless it already ends with a period, a question mark or an exclamation mark; an
     * empty text stays empty.
     */
    public function sentence(string $closeQuote = '', bool $insideQuotes = false): self
    {
        return $this->punctuate('.', $closeQuote, $insideQuotes);
    }

    /**
     * The text followed by a punctuation mark, unless it already ends with that mark, a question mark or an
     * exclamation mark. After a closing quotation mark, the mark goes inside it when `$insideQuotes`
     * says so: « “Title,” » in American English, « « Titre », » in French.
     */
    public function punctuate(string $mark, string $closeQuote = '', bool $insideQuotes = false): self
    {
        $text = $this->toText();
        $quoted = '' !== $closeQuote && str_ends_with($text, $closeQuote);
        $core = $quoted ? substr($text, 0, -\strlen($closeQuote)) : $text;
        $ended = str_ends_with($core, $mark) || str_ends_with($core, '?') || str_ends_with($core, '!');

        if ($this->isEmpty() || $ended) {
            return $this;
        }

        $runs = $this->runs;
        $last = \count($runs) - 1;

        if ($quoted && $insideQuotes) {
            $runs[$last] = [substr($runs[$last][0], 0, -\strlen($closeQuote)).$mark.$closeQuote, $runs[$last][1]];

            return new self($runs);
        }

        return self::concat($this, $mark);
    }

    public function toText(): string
    {
        return implode('', array_column($this->runs, 0));
    }

    /**
     * The text with its special characters escaped, the runs in italics in `<i>`.
     */
    public function toHtml(): string
    {
        $html = '';

        foreach ($this->runs as [$text, $italic]) {
            $escaped = htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
            $html .= $italic ? '<i>'.$escaped.'</i>' : $escaped;
        }

        return $html;
    }
}
