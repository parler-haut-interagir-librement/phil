<?php

declare(strict_types=1);

namespace Phil\Listings\Exception;

use InvalidArgumentException;

/**
 * A value of a listing, a response or a policy that the library cannot work with: an identifier, a title, a
 * criterion, a location, a bound.
 */
final class InvalidListing extends InvalidArgumentException implements ListingsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
