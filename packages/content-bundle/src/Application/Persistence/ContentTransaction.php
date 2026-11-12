<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Persistence;

use Throwable;

/**
 * The unit of work of an editing or publishing operation: what is added and removed through the repositories
 * is written when the operation flushes, and all of it or nothing is kept.
 *
 * `Infrastructure\Doctrine\DoctrineContentTransaction` is the Doctrine adapter. Called inside a transaction
 * already open — a Doctrine migration's —, it nests in it.
 */
interface ContentTransaction
{
    /**
     * Runs `$operation` in a transaction, flushes, and commits; rolls back and rethrows on failure.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws Throwable whatever `$operation` throws
     */
    public function run(callable $operation): mixed;

    /**
     * Writes what is pending now, inside the running transaction: a row removed and a row of the same
     * dimension added must reach the database in that order, which a single flush does not promise.
     */
    public function flush(): void;
}
