<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Lock;

use Closure;
use Override;
use Phil\UserSecretsBundle\Application\Contract\RefreshLock;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Symfony\Component\Lock\LockFactory;

/**
 * The refresh lock over Symfony's Lock component: one lock per secret, its key a hash of the owner and the name, held
 * at most `$ttl` seconds so that a process that dies does not keep it, and released once the refresh is done.
 */
final readonly class SymfonyRefreshLock implements RefreshLock
{
    public const string PREFIX = 'phil_user_secrets.refresh.';

    public function __construct(
        private LockFactory $locks,
        private float $ttl = 30.0,
    ) {
    }

    #[Override]
    public function synchronized(Owner $owner, SecretName $name, Closure $critical): mixed
    {
        $lock = $this->locks->createLock(self::key($owner, $name), $this->ttl);
        $lock->acquire(true);

        try {
            return $critical();
        } finally {
            $lock->release();
        }
    }

    /**
     * The key of a secret's lock: neither the owner nor the name shows in the store of the locks.
     */
    public static function key(Owner $owner, SecretName $name): string
    {
        return self::PREFIX.hash('sha256', json_encode([$owner->value, $name->value], \JSON_THROW_ON_ERROR));
    }
}
