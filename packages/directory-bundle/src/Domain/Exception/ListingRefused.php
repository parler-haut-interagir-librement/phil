<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * What a directory refuses: a genre it does not have, an editor it cannot name, someone who may not do what they
 * asked, a listing it cannot find.
 */
final class ListingRefused extends DomainException implements DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
