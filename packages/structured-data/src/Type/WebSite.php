<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `WebSite`: its name and address, who publishes it, its language and, given the address of its search with
 * `{search_term_string}` where the words go, the `SearchAction` that lets a search engine offer a search box.
 */
final class WebSite
{
    public const string SEARCH_TERM = '{search_term_string}';

    /**
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        string $url,
        ?string $id = null,
        string|LanguageString|null $alternateName = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        Node|Reference|null $publisher = null,
        ?string $searchUrl = null,
        array $properties = [],
    ): Node {
        return Node::of('WebSite', Properties::merge($properties, [
            'name' => $name,
            'alternateName' => $alternateName,
            'url' => $url,
            'description' => $description,
            'inLanguage' => $inLanguage,
            'publisher' => $publisher,
            'potentialAction' => null === $searchUrl ? null : Node::of('SearchAction', [
                'target' => Node::of('EntryPoint', ['urlTemplate' => $searchUrl]),
                'query-input' => 'required name=search_term_string',
            ]),
        ]), $id);
    }
}
