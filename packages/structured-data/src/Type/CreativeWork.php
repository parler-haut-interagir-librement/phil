<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use DateTimeInterface;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `CreativeWork`, or one of its kinds the other factories leave out — a `Game`, a `Course`, a `Dataset`: its name,
 * author, address, description, language, dates, picture and licence.
 */
final class CreativeWork
{
    /**
     * @param Node|Reference|list<Node|Reference>|null $author
     * @param array<string, mixed>                     $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        ?string $id = null,
        Node|Reference|array|null $author = null,
        ?string $url = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        DateTimeInterface|string|null $datePublished = null,
        DateTimeInterface|string|null $dateModified = null,
        string|Node|Reference|null $image = null,
        ?string $license = null,
        string $type = 'CreativeWork',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'name' => $name,
            'author' => $author,
            'url' => $url,
            'description' => $description,
            'inLanguage' => $inLanguage,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'image' => $image,
            'license' => $license,
        ]), $id);
    }
}
