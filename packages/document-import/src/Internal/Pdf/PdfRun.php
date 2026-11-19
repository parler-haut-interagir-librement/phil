<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Media;

/**
 * What the content of a page shows, in the order it draws it: a run of text — its text, where it starts and ends on
 * its baseline, its size, bold and italic — or an image.
 *
 * @internal
 */
final readonly class PdfRun
{
    public function __construct(
        public string $text,
        public float $x,
        public float $end,
        public float $y,
        public float $size,
        public bool $bold = false,
        public bool $italic = false,
        public ?Media $image = null,
        public ?string $link = null,
    ) {
    }

    public function withLink(?string $link): self
    {
        return new self($this->text, $this->x, $this->end, $this->y, $this->size, $this->bold, $this->italic, $this->image, $link);
    }
}
