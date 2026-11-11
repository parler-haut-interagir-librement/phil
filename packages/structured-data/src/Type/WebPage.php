<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use DateTimeInterface;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `WebPage`, or one of its kinds — `AboutPage`, `ContactPage`, `CollectionPage`, `ProfilePage`, `ItemPage`: its
 * address, its name, the site it is part of, its breadcrumb and its main image.
 */
final class WebPage
{
    /**
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string $url,
        ?string $id = null,
        string|LanguageString|null $name = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        Node|Reference|null $isPartOf = null,
        Node|Reference|null $breadcrumb = null,
        string|Node|Reference|null $primaryImageOfPage = null,
        DateTimeInterface|string|null $datePublished = null,
        DateTimeInterface|string|null $dateModified = null,
        string $type = 'WebPage',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'inLanguage' => $inLanguage,
            'isPartOf' => $isPartOf,
            'breadcrumb' => $breadcrumb,
            'primaryImageOfPage' => $primaryImageOfPage,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
        ]), $id);
    }
}
