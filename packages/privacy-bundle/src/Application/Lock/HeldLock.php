<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Lock;

use Exception;
use Phil\PrivacyBundle\Application\Exception\LockLost;
use Symfony\Component\Lock\LockInterface;

/**
 * A lock of `symfony/lock` held during work made of steps, renewed between them.
 *
 * `renew()` puts the expiry off by the lock's lifetime, then asks the store whether the lock is still this process's:
 * a lease the store no longer grants to this process, because it expired or another process took it meanwhile, throws
 * `LockLost` before the next step starts. A failing store throws it too: a lock that cannot be confirmed is not held. Each step, one exporter, one eraser or one
 * purge order, must then end within the lifetime of the lock, however long the whole work takes.
 *
 * `release()` gives the lock up once, and does nothing once it was lost: the store may have given it to another
 * process. A store that fails to release it is ignored: the lease expires on its own.
 */
final class HeldLock
{
    private bool $held = true;

    public function __construct(
        private readonly LockInterface $lock,
    ) {
    }

    /**
     * @throws LockLost when the lock expired and is no longer this process's, or the store cannot confirm it
     */
    public function renew(): void
    {
        if (!$this->held) {
            throw LockLost::create();
        }

        try {
            $this->lock->refresh();
            $acquired = $this->lock->isAcquired();
        } catch (Exception $exception) {
            $this->held = false;

            throw LockLost::create($exception);
        }

        if (!$acquired) {
            $this->held = false;

            throw LockLost::create();
        }
    }

    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        $this->held = false;

        try {
            $this->lock->release();
        } catch (Exception) {
            // The lock expires on its own: the work it guarded is over either way.
        }
    }
}
