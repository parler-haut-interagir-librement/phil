<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Exception;

use Phil\SitesConsoleBundle\Domain\Exception\SitesConsoleException;
use RuntimeException;

/**
 * A site, a push or a plan the console does not know.
 */
final class NotFound extends RuntimeException implements SitesConsoleException
{
    public static function site(string $id): self
    {
        return new self(\sprintf('The console knows no site "%s".', $id));
    }

    public static function push(string $id): self
    {
        return new self(\sprintf('The console knows no push "%s".', $id));
    }

    public static function plan(string $id): self
    {
        return new self(\sprintf('The console knows no update plan "%s".', $id));
    }
}
