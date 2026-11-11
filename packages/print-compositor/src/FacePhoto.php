<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * The photo of one face of a blank product in one variant — the front of the shirt in black — with the rectangle each
 * print zone of that face covers on it, in pixels.
 */
final readonly class FacePhoto
{
    /**
     * @param array<string, PixelBox> $zones by zone name
     */
    public function __construct(
        public string $face,
        public string $variant,
        public Raster $image,
        public array $zones,
    ) {
        if ('' === trim($face) || '' === trim($variant)) {
            throw InvalidLayout::because('A face photo names its face and its variant.');
        }

        foreach ($zones as $name => $box) {
            if (!$box->isInside($image->width, $image->height)) {
                throw InvalidLayout::because(\sprintf('The zone "%s" of the %s photo of "%s" goes past the %d × %d photo.', $name, $face, $variant, $image->width, $image->height));
            }
        }
    }

    public function zone(string $name): PixelBox
    {
        return $this->zones[$name] ?? throw InvalidLayout::because(\sprintf('The %s photo of "%s" places no zone "%s".', $this->face, $this->variant, $name));
    }
}
