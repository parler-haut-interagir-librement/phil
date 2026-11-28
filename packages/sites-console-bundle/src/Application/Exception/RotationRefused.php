<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;

/**
 * A rotation of the console key that cannot start or go on with the configuration as it is.
 */
final class RotationRefused extends RuntimeException implements SitesConsoleException
{
    public static function noNextKey(): self
    {
        return new self('The console has no next key: set console.next_private_key.');
    }

    public static function sameKey(): self
    {
        return new self('The next key of the console is its current key.');
    }

    public static function otherRotation(string $id): self
    {
        return new self(\sprintf('The rotation %s to another key is not finished; finish it with its own next key first.', $id));
    }

    public static function otherPrevious(string $id): self
    {
        return new self(\sprintf('The rotation %s started from another key than console.private_key; set the key it started from back.', $id));
    }
}
