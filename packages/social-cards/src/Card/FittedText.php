<?php

declare(strict_types=1);

namespace Phil\SocialCards\Card;

/**
 * A text fitted to a box: its lines, the size in pixels they are drawn at, and whether the end was cut.
 */
final readonly class FittedText
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public array $lines,
        public int $size,
        public bool $truncated = false,
    ) {
    }

    /**
     * The height of the lines in pixels, at a line height of 1.25 times the size.
     */
    public function height(): int
    {
        return \count($this->lines) * self::lineHeight($this->size);
    }

    public static function lineHeight(int $size): int
    {
        return (int) round($size * 1.25);
    }
}
