<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * A text laid out in its box: the size it is drawn at, in points, its lines and the size of its ink, in pixels at the
 * resolution it was fitted at.
 */
final readonly class FittedText
{
    /**
     * @param non-empty-list<FittedLine> $lines
     */
    public function __construct(
        public float $size,
        public array $lines,
        public int $width,
        public int $height,
        public int $dpi,
    ) {
    }

    /**
     * The lines as they break.
     *
     * @return non-empty-list<string>
     */
    public function lineTexts(): array
    {
        return array_map(static fn (FittedLine $line): string => $line->text, $this->lines);
    }
}
