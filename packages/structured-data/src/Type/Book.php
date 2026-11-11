<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use DateTimeInterface;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `Book`: its title, author — a `Person` of that name when given as a text —, ISBN, format, language, pages,
 * publisher and date.
 */
final class Book
{
    /**
     * @param string|Node|Reference|list<Node|Reference> $author
     * @param array<string, mixed>                       $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        string|Node|Reference|array $author,
        ?string $id = null,
        ?string $isbn = null,
        ?string $url = null,
        ?BookFormat $bookFormat = null,
        ?string $inLanguage = null,
        ?int $numberOfPages = null,
        DateTimeInterface|string|null $datePublished = null,
        Node|Reference|null $publisher = null,
        string|Node|Reference|null $image = null,
        string|LanguageString|null $description = null,
        array $properties = [],
    ): Node {
        return Node::of('Book', Properties::merge($properties, [
            'name' => $name,
            'author' => \is_string($author) ? Person::of($author) : $author,
            'isbn' => $isbn,
            'url' => $url,
            'bookFormat' => $bookFormat,
            'inLanguage' => $inLanguage,
            'numberOfPages' => $numberOfPages,
            'datePublished' => $datePublished,
            'publisher' => $publisher,
            'image' => $image,
            'description' => $description,
        ]), $id);
    }
}
