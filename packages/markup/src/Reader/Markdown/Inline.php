<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

/**
 * Inline content not read yet: pieces of Markdown, and pieces of HTML made by the block parser — the separator of two
 * paragraphs folded into one text, an escaped code block. It is read once every block is known, so a link or a
 * footnote may be defined after the text that uses it.
 */
final readonly class Inline
{
    /**
     * @param list<array{bool, string}> $pieces each a flag, `true` for HTML, and its text
     */
    private function __construct(
        public array $pieces,
    ) {
    }

    public static function markdown(string $markdown): self
    {
        return new self([[false, $markdown]]);
    }

    public static function html(string $html): self
    {
        return new self([[true, $html]]);
    }

    /**
     * A value of a block's data that holds inline content, or empty content when it holds none.
     */
    public static function of(mixed $value): self
    {
        return $value instanceof self ? $value : self::html('');
    }

    /**
     * The pieces of several, an HTML separator between two, gathered in place so that joining many takes linear time.
     *
     * @param array<self> $inlines
     */
    public static function join(array $inlines, string $separator): self
    {
        $pieces = [];

        foreach ($inlines as $inline) {
            if ([] !== $pieces) {
                $pieces[] = [true, $separator];
            }

            foreach ($inline->pieces as $piece) {
                $pieces[] = $piece;
            }
        }

        return new self($pieces);
    }

    /**
     * The Markdown it holds, its HTML pieces left out: what a reader matches a pattern on.
     */
    public function source(): string
    {
        $source = '';

        foreach ($this->pieces as [$html, $text]) {
            $source .= $html ? '' : $text;
        }

        return $source;
    }
}
