<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The ORM mapping of `Phil\Geo\Coordinates`, in attributes: two columns of decimal degrees, so that a query can narrow
 * places to a bounding box on an index. An embedded `position` maps to `position_latitude` and `position_longitude`.
 *
 * `phil/geo` stays free of Doctrine, so the attributes live on this class, which mirrors the properties of
 * `Coordinates`; `CoordinatesMappingDriver` reads them for `Coordinates`. It is never instantiated.
 *
 * @internal
 */
#[ORM\Embeddable]
final readonly class CoordinatesEmbeddable
{
    private function __construct(
        #[ORM\Column(name: 'latitude', type: Types::FLOAT)]
        public float $latitude,
        #[ORM\Column(name: 'longitude', type: Types::FLOAT)]
        public float $longitude,
    ) {
    }
}
