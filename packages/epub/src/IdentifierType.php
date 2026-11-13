<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * The scheme of an identifier, written as an ONIX code list 5 refinement in the package document; a UUID carries its
 * scheme in its `urn:uuid:` value and needs none.
 */
enum IdentifierType: string
{
    case Uuid = 'uuid';
    case Isbn = 'isbn';
    case Doi = 'doi';
    case Urn = 'urn';
    case Proprietary = 'proprietary';

    /**
     * The ONIX code list 5 value of the scheme, or null for a UUID.
     */
    public function onixCode(): ?string
    {
        return match ($this) {
            self::Uuid => null,
            self::Isbn => '15',
            self::Doi => '06',
            self::Urn => '22',
            self::Proprietary => '01',
        };
    }
}
