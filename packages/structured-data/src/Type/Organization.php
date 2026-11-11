<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * An `Organization`, or one of its kinds — `Corporation`, `NGO`, `EducationalOrganization`, `PerformingGroup`: its
 * name, address, logo, ways to reach it and its pages elsewhere.
 */
final class Organization
{
    /**
     * @param list<string>         $sameAs     its pages elsewhere: an encyclopedia, its social profiles
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string|LanguageString $name,
        ?string $url = null,
        ?string $id = null,
        string|Node|Reference|null $logo = null,
        string|LanguageString|null $description = null,
        ?string $legalName = null,
        ?string $email = null,
        ?string $telephone = null,
        Node|Reference|null $address = null,
        array $sameAs = [],
        string $type = 'Organization',
        array $properties = [],
    ): Node {
        return Node::of($type, Properties::merge($properties, [
            'name' => $name,
            'legalName' => $legalName,
            'url' => $url,
            'logo' => $logo,
            'description' => $description,
            'email' => $email,
            'telephone' => $telephone,
            'address' => $address,
            'sameAs' => $sameAs,
        ]), $id);
    }
}
