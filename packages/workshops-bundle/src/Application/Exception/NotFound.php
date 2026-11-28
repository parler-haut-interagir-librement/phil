<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Application\Exception;

use RuntimeException;

/**
 * A workshop, a session or a registration that does not exist.
 */
final class NotFound extends RuntimeException
{
    public static function workshop(string $id): self
    {
        return new self(\sprintf('No workshop "%s".', mb_substr($id, 0, 64)));
    }

    public static function session(string $id): self
    {
        return new self(\sprintf('No session "%s".', mb_substr($id, 0, 64)));
    }

    public static function registration(string $id): self
    {
        return new self(\sprintf('No registration "%s".', mb_substr($id, 0, 64)));
    }
}
