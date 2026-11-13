<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

/**
 * A block of a large-print edition: a heading or a paragraph.
 */
final readonly class LargePrintBlock
{
    private function __construct(
        public string $text,
        public bool $heading,
    ) {
    }

    public static function heading(string $text): self
    {
        return new self($text, true);
    }

    public static function paragraph(string $text): self
    {
        return new self($text, false);
    }
}
