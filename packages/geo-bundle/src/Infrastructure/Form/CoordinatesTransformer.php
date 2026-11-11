<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\Form;

use Override;
use Phil\Geo\Coordinates;
use Phil\Geo\Exception\GeoException;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between `Coordinates` and the two fields of a `CoordinatesType`.
 *
 * @implements DataTransformerInterface<Coordinates, array{latitude?: ?float, longitude?: ?float}>
 */
final readonly class CoordinatesTransformer implements DataTransformerInterface
{
    #[Override]
    public function transform(mixed $value): array
    {
        return $value instanceof Coordinates
            ? ['latitude' => $value->latitude, 'longitude' => $value->longitude]
            : ['latitude' => null, 'longitude' => null];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?Coordinates
    {
        $latitude = $value['latitude'] ?? null;
        $longitude = $value['longitude'] ?? null;

        if (null === $latitude && null === $longitude) {
            return null;
        }

        if (null === $latitude || null === $longitude) {
            throw new TransformationFailedException('A position has both a latitude and a longitude.');
        }

        try {
            return Coordinates::of($latitude, $longitude);
        } catch (GeoException $geoException) {
            throw new TransformationFailedException($geoException->getMessage(), 0, $geoException);
        }
    }
}
