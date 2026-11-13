<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * The size of a fixed-layout page, in CSS pixels, written in the `viewport` meta element of its content document.
 */
final readonly class Viewport
{
    private const int LARGEST = 100_000;

    /**
     * @throws InvalidBook
     */
    public function __construct(public int $width, public int $height)
    {
        if ($width < 1 || $height < 1 || $width > self::LARGEST || $height > self::LARGEST) {
            throw InvalidBook::because(\sprintf('A viewport is from 1 to %d pixels wide and high; %d × %d given.', self::LARGEST, $width, $height));
        }
    }

    public function content(): string
    {
        return 'width='.$this->width.', height='.$this->height;
    }
}
