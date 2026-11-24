<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Lock;

use Phil\Backup\Exception\BackupFailed;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\LockFactory;

/**
 * One exclusive lock per backup directory, through Symfony Lock, held by every command that writes or reads its sets:
 * a backup and the rotation after it, a restore with the copy taken first and the removal of the older copies, and a
 * restore test. None of them waits: a command that finds the lock taken fails at once, so that a rotation never
 * removes a set another command is writing or testing, and two schedules that overlap never pile up.
 *
 * The lock is named after the directory, so two applications backing up to directories of their own never block each
 * other. With a store whose locks expire, the lock lives `ttl` seconds at most; the flock store of the default
 * factory never expires, and frees the lock when the process ends, whatever the way.
 */
final readonly class BackupLock
{
    public const string PREFIX = 'phil_backup:';

    public function __construct(
        private LockFactory $factory,
        private string $directory,
        private ?float $ttl = null,
    ) {
    }

    /**
     * Runs the work with the lock held, and frees it after, whatever happens.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     *
     * @throws BackupFailed when another command holds the lock, or the lock cannot be taken
     */
    public function exclusively(callable $work): mixed
    {
        $lock = $this->factory->createLock($this->resource(), $this->ttl, false);

        try {
            $acquired = $lock->acquire();
        } catch (LockAcquiringException $lockAcquiringException) {
            throw BackupFailed::because(\sprintf('The lock of the backups in %s cannot be taken: %s', $this->directory, $lockAcquiringException->getMessage()));
        }

        if (!$acquired) {
            throw BackupFailed::because(\sprintf('Another backup, rotation, restore or restore test of %s is running: try again once it ends.', $this->directory));
        }

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /**
     * The name of the lock: the prefix, then the SHA-256 of the directory's path, resolved when it exists.
     */
    public function resource(): string
    {
        $real = realpath($this->directory);

        return self::PREFIX.hash('sha256', false === $real ? $this->directory : $real);
    }
}
