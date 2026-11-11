<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application\Exception;

use Phil\EditLockBundle\Domain\Exception\EditLockException;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use RuntimeException;

/**
 * The application's policy does not let the holder edit the resource, or take over another holder's lease.
 */
final class EditRefused extends RuntimeException implements EditLockException
{
    public static function edit(LeaseKey $key): self
    {
        return new self(\sprintf('Editing "%s" is not allowed.', $key->resource));
    }

    public static function takeover(LeaseKey $key): self
    {
        return new self(\sprintf('Taking over "%s" is not allowed.', $key->toString()));
    }
}
