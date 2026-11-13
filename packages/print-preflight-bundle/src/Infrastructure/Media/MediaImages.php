<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Infrastructure\Media;

use Phil\MediaBundle\Domain\Model\Media;
use Phil\PrintPreflight\ColourSpace;
use Phil\PrintPreflight\PlacedImage;
use Phil\PrintPreflight\Rectangle;
use Phil\PrintPreflight\Size;
use Phil\PrintPreflightBundle\Application\Exception\InvalidMeasurement;

/**
 * The images of the media library of phil/media-bundle placed on a page: the pixel size is the one the library
 * recorded when the file was uploaded, the identifier the medium's.
 */
final readonly class MediaImages
{
    public function placed(Media $media, Rectangle $frame, ColourSpace $colourSpace = ColourSpace::Rgb, bool $bleeds = false, ?Size $scaledTo = null): PlacedImage
    {
        $file = $media->file();

        if (null === $file->width || null === $file->height) {
            throw InvalidMeasurement::because(\sprintf('The medium %s is not an image of known size, so it cannot be placed.', $media->id));
        }

        return new PlacedImage($media->id, $frame, $file->width, $file->height, $colourSpace, $bleeds, $scaledTo);
    }
}
