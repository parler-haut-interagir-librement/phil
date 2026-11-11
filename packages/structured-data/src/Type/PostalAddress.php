<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\Node;

/**
 * A `PostalAddress`: its street, locality, region, postal code and country — the country as an ISO 3166-1 code, such
 * as "CA".
 */
final class PostalAddress
{
    /**
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        ?string $streetAddress = null,
        ?string $addressLocality = null,
        ?string $addressRegion = null,
        ?string $postalCode = null,
        ?string $addressCountry = null,
        ?string $postOfficeBoxNumber = null,
        ?string $id = null,
        array $properties = [],
    ): Node {
        return Node::of('PostalAddress', Properties::merge($properties, [
            'streetAddress' => $streetAddress,
            'postOfficeBoxNumber' => $postOfficeBoxNumber,
            'addressLocality' => $addressLocality,
            'addressRegion' => $addressRegion,
            'postalCode' => $postalCode,
            'addressCountry' => $addressCountry,
        ]), $id);
    }
}
