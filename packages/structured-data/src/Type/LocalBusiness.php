<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `LocalBusiness`, or one of its kinds — `Store`, `Restaurant`, `CafeOrCoffeeShop`, `EntertainmentBusiness`: an
 * organisation with a place people come to, its address, where it stands, when it opens and how to call it.
 */
final class LocalBusiness
{
    /**
     * @param list<string>         $openingHours the opening hours as schema.org writes them in short, such as "Mo-Fr 10:00-18:00"
     * @param list<string>         $sameAs       its pages elsewhere
     * @param array<string, mixed> $properties   the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        Node|Reference $address,
        ?string $url = null,
        ?string $id = null,
        ?string $telephone = null,
        string|Node|Reference|null $image = null,
        ?string $priceRange = null,
        ?float $latitude = null,
        ?float $longitude = null,
        array $openingHours = [],
        array $sameAs = [],
        string $type = 'LocalBusiness',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'name' => $name,
            'address' => $address,
            'url' => $url,
            'telephone' => $telephone,
            'image' => $image,
            'priceRange' => $priceRange,
            'geo' => Place::coordinates($latitude, $longitude),
            'openingHours' => $openingHours,
            'sameAs' => $sameAs,
        ]), $id);
    }
}
