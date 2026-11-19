<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Application\Exception;

use RuntimeException;

final class ShortLinkNotFound extends RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(\sprintf('There is no short link %d.', $id));
    }
}
