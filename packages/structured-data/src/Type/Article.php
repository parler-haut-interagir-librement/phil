<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use DateTimeInterface;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * An `Article`, or one of its kinds — `NewsArticle`, `BlogPosting`, `TechArticle`: its headline, author, dates,
 * pictures, publisher and the page it is the main content of.
 */
final class Article
{
    /**
     * @param Node|Reference|list<Node|Reference>|null               $author
     * @param string|Node|Reference|list<string|Node|Reference>|null $image
     * @param array<string, mixed>                                   $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $headline,
        ?string $id = null,
        Node|Reference|array|null $author = null,
        DateTimeInterface|string|null $datePublished = null,
        DateTimeInterface|string|null $dateModified = null,
        string|Node|Reference|array|null $image = null,
        Node|Reference|null $publisher = null,
        ?string $url = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        Node|Reference|null $mainEntityOfPage = null,
        string $type = 'Article',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'headline' => $headline,
            'author' => $author,
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'image' => $image,
            'publisher' => $publisher,
            'url' => $url,
            'description' => $description,
            'inLanguage' => $inLanguage,
            'mainEntityOfPage' => $mainEntityOfPage,
        ]), $id);
    }
}
