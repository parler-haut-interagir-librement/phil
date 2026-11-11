<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `Place`: a name, an address — a `PostalAddress`, or a text —, and where it stands as `GeoCoordinates`.
 */
final class Place
{
    /**
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString|null $name = null,
        string|Node|Reference|null $address = null,
        ?string $id = null,
        ?string $url = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $telephone = null,
        array $properties = [],
    ): Node {
        return Node::of('Place', Properties::merge($properties, [
            'name' => $name,
            'address' => $address,
            'url' => $url,
            'geo' => self::coordinates($latitude, $longitude),
            'telephone' => $telephone,
        ]), $id);
    }

    /**
     * The `GeoCoordinates` of a latitude and a longitude, or `null` unless both are given.
     */
    public static function coordinates(?float $latitude, ?float $longitude): ?Node
    {
        return null === $latitude || null === $longitude ? null : Node::of('GeoCoordinates', ['latitude' => $latitude, 'longitude' => $longitude]);
    }
}
