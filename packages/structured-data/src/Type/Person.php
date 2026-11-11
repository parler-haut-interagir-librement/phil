<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * A `Person`: a name, a page, a picture, a job and where it is held, and the person's pages elsewhere.
 */
final class Person
{
    /**
     * @param list<string>         $sameAs     the person's pages elsewhere: profiles, an encyclopedia
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        ?string $url = null,
        ?string $id = null,
        string|Node|Reference|null $image = null,
        string|LanguageString|null $jobTitle = null,
        Node|Reference|null $worksFor = null,
        ?string $email = null,
        array $sameAs = [],
        array $properties = [],
    ): Node {
        return Node::of('Person', Properties::merge($properties, [
            'name' => $name,
            'url' => $url,
            'image' => $image,
            'jobTitle' => $jobTitle,
            'worksFor' => $worksFor,
            'email' => $email,
            'sameAs' => $sameAs,
        ]), $id);
    }
}
