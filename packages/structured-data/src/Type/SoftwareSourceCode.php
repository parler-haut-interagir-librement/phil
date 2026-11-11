<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `SoftwareSourceCode`: a code base — a package, a library —, its repository, languages, licence, version, authors
 * and what it runs on.
 */
final class SoftwareSourceCode
{
    /**
     * @param list<string>                             $programmingLanguage
     * @param Node|Reference|list<Node|Reference>|null $author
     * @param list<string>                             $keywords
     * @param array<string, mixed>                     $properties          the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        string $codeRepository,
        ?string $id = null,
        string|LanguageString|null $description = null,
        array $programmingLanguage = [],
        ?string $license = null,
        ?string $version = null,
        Node|Reference|array|null $author = null,
        ?string $url = null,
        ?string $runtimePlatform = null,
        array $keywords = [],
        array $properties = [],
    ): Node {
        return Node::of('SoftwareSourceCode', Properties::merge($properties, [
            'name' => $name,
            'codeRepository' => $codeRepository,
            'description' => $description,
            'programmingLanguage' => $programmingLanguage,
            'license' => $license,
            'version' => $version,
            'author' => $author,
            'url' => $url,
            'runtimePlatform' => $runtimePlatform,
            'keywords' => [] === $keywords ? null : implode(', ', $keywords),
        ]), $id);
    }
}
