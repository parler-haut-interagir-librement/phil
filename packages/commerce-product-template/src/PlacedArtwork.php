<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

/**
 * An artwork as a product prints it: the image, the face and the zone of the template it goes in.
 */
final readonly class PlacedArtwork
{
    public function __construct(
        public Face $face,
        public PrintZone $zone,
        public string $path,
    ) {
    }
}
