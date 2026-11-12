<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * The identifier of a listing, a UUIDv7.
 */
final readonly class ListingId extends Identifier
{
}
