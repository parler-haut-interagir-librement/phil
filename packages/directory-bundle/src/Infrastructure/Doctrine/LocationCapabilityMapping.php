<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;
use Phil\DirectoryBundle\Domain\Capability\LocationCapable;

/**
 * The columns of `LocationCapable`: the latitude and the longitude.
 */
final class LocationCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return LocationCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'latitude', 'type' => 'float', 'columnName' => 'location_latitude', 'nullable' => true],
            ['fieldName' => 'longitude', 'type' => 'float', 'columnName' => 'location_longitude', 'nullable' => true],
        ];
    }
}
