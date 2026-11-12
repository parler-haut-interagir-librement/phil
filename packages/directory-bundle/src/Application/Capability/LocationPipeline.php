<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Application\Capability;

use Phil\ContentBundle\Application\DataMapper\SubmittedData;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Pipeline\Normalizer;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\DirectoryBundle\Domain\Capability\LocationCapable;
use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeoException;

/**
 * The place on a map through the pipeline of content-bundle: `location` submitted as `latitude,longitude`, kept on the
 * shared row, carried by publishing, and normalized back to that text.
 */
final readonly class LocationPipeline implements DataMapper, Merger, Normalizer
{
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$unlocalized instanceof LocationCapable || !SubmittedData::has($data, 'location')) {
            return;
        }

        $location = SubmittedData::string($data, 'location');

        try {
            $unlocalized->setLocation(null === $location ? null : Coordinates::fromString($location));
        } catch (GeoException $geoException) {
            throw InvalidContent::because($geoException->getMessage());
        }
    }

    public function merge(DimensionContent $target, DimensionContent $source): void
    {
        if ($target instanceof LocationCapable && $source instanceof LocationCapable && $source->getLocation() instanceof Coordinates) {
            $target->setLocation($source->getLocation());
        }
    }

    public function normalize(DimensionContent $content): array
    {
        return $content instanceof LocationCapable ? ['location' => $content->getLocation()?->toString()] : [];
    }
}
