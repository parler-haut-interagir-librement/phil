<?php

declare(strict_types=1);

namespace Phil\Listings\Exception;

use Phil\Listings\ListingKind;
use RuntimeException;

/**
 * A kind of listing no policy is declared for. A listing never gets the rules of an undeclared kind by default.
 */
final class UnknownKind extends RuntimeException implements ListingsException
{
    public static function named(ListingKind $kind): self
    {
        return new self(\sprintf('No policy is declared for the kind of listing "%s".', $kind->value));
    }
}
