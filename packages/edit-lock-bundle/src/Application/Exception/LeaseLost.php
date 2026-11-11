<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application\Exception;

use Phil\EditLockBundle\Domain\Exception\EditLockException;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use RuntimeException;

/**
 * The holder no longer holds the key: it was released, taken over, or passed to another holder once expired.
 */
final class LeaseLost extends RuntimeException implements EditLockException
{
    public static function of(LeaseKey $key): self
    {
        return new self(\sprintf('"%s" is no longer held.', $key->toString()));
    }
}
