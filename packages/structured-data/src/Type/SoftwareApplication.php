<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `SoftwareApplication`, or one of its kinds — `WebApplication`, `MobileApplication`, `VideoGame`: its name,
 * category, systems, version, offers, address and download.
 */
final class SoftwareApplication
{
    /**
     * @param Node|Reference|list<Node|Reference>|null $offers
     * @param array<string, mixed>                     $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        ?string $id = null,
        ?string $applicationCategory = null,
        ?string $operatingSystem = null,
        ?string $softwareVersion = null,
        Node|Reference|array|null $offers = null,
        ?string $url = null,
        string|LanguageString|null $description = null,
        ?string $downloadUrl = null,
        string $type = 'SoftwareApplication',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'name' => $name,
            'applicationCategory' => $applicationCategory,
            'operatingSystem' => $operatingSystem,
            'softwareVersion' => $softwareVersion,
            'offers' => $offers,
            'url' => $url,
            'description' => $description,
            'downloadUrl' => $downloadUrl,
        ]), $id);
    }
}
