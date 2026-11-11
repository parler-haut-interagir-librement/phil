<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application\Exception;

use Phil\EditLockBundle\Domain\Exception\EditLockException;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use RuntimeException;

/**
 * Another holder holds the key, or a part or the whole of its resource; `$lease` is theirs, or `null` when requests
 * kept racing for the key and none could be read.
 */
final class LeaseHeld extends RuntimeException implements EditLockException
{
    private function __construct(
        string $message,
        public readonly ?Lease $lease,
    ) {
        parent::__construct($message);
    }

    public static function by(Lease $lease): self
    {
        return new self(\sprintf('"%s" is held by "%s".', $lease->key()->toString(), $lease->holder()->name), $lease);
    }

    public static function contended(LeaseKey $key): self
    {
        return new self(\sprintf('"%s" kept changing hands; try again.', $key->toString()), null);
    }
}
