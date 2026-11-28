<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;

/**
 * A change of the console keys the agent refuses: signed by the wrong key, or one that would leave no key.
 */
final class KeyRefused extends RuntimeException implements SitesConsoleException
{
    public static function stageSignedByNext(): self
    {
        return new self('A next key is staged by a request signed with the current key.');
    }

    public static function retireSignedByRetired(): self
    {
        return new self('A key is retired by a request signed with the key that remains.');
    }

    public static function lastKey(): self
    {
        return new self('The only key of the console cannot be retired.');
    }
}
