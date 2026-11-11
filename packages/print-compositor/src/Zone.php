<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * A print zone of a face of a product: its name and its real size in millimetres, the size its print file is made at.
 */
final readonly class Zone
{
    public function __construct(
        public string $name,
        public float $width,
        public float $height,
    ) {
        if ('' === trim($name)) {
            throw InvalidLayout::because('A zone has a name.');
        }

        if ($width <= 0 || $height <= 0) {
            throw InvalidLayout::because(\sprintf('The zone "%s" has a positive size; %s × %s mm given.', $name, $width, $height));
        }
    }

    /**
     * The size of the zone in pixels at the given resolution.
     *
     * @return array{int, int}
     */
    public function pixelSize(int $dpi): array
    {
        return [max(1, Units::pixels($this->width, $dpi)), max(1, Units::pixels($this->height, $dpi))];
    }
}
