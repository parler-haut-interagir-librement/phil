<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A node of any schema.org type, with the properties every `Thing` has: what the other factories do not cover — a
 * `Recipe`, a `Course`, a `Game` — starts here and adds its own properties.
 */
final class Thing
{
    /**
     * @param list<string>         $sameAs     the addresses of the same thing elsewhere: an encyclopedia, a profile
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string $type,
        string|LanguageString|null $name = null,
        ?string $url = null,
        string|LanguageString|null $description = null,
        string|Node|Reference|null $image = null,
        array $sameAs = [],
        ?string $id = null,
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, ['name' => $name, 'url' => $url, 'description' => $description, 'image' => $image, 'sameAs' => $sameAs]), $id);
    }
}
