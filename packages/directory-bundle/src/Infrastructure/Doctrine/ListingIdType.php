<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;

/**
 * The column of a listing's identifier: a native `uuid` on PostgreSQL, `BINARY(16)` elsewhere.
 *
 * @extends IdentifierType<ListingId>
 */
final class ListingIdType extends IdentifierType
{
    public const string NAME = 'phil_directory_listing_id';

    protected function identifierClass(): string
    {
        return ListingId::class;
    }
}
