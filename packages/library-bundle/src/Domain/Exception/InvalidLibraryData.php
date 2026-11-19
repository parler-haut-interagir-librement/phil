<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A record, a copy or a loan given a value it cannot take: an ISBN whose check digit is wrong, more minimum players than
 * maximum, a member named by an e-mail address, a loan returned twice.
 */
final class InvalidLibraryData extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
