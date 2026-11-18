<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

/**
 * Where a document is cut into slides: at its separator blocks — `delimiter`, the `---` of Markdown, and
 * `pageBreak` —, at its headings up to a level, at both, or, `auto`, at its separators when it holds one at the top
 * level and at its headings otherwise.
 */
enum SplitMode: string
{
    case Auto = 'auto';
    case Separators = 'separators';
    case Headings = 'headings';
    case Both = 'both';

    /**
     * The mode a document is cut with: `auto` settled by whether the document holds a separator.
     */
    public function resolve(bool $hasSeparator): self
    {
        if (self::Auto !== $this) {
            return $this;
        }

        return $hasSeparator ? self::Separators : self::Headings;
    }

    public function cutsAtSeparators(): bool
    {
        return self::Headings !== $this;
    }

    public function cutsAtHeadings(): bool
    {
        return self::Headings === $this || self::Both === $this;
    }
}
