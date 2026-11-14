<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Migration;

/**
 * How the migration of one database ended: its exit code, and the end of what it wrote, for the operator's console.
 *
 * A run that is stopped gives a result for each database it did not migrate: `interrupted` for a migration that was
 * running and was stopped, `interrupted` and not `started` for one that never began.
 */
final readonly class MigrationResult
{
    public function __construct(
        public MigrationTarget $target,
        public ?int $exitCode,
        public string $output = '',
        public bool $timedOut = false,
        public bool $interrupted = false,
        public bool $started = true,
    ) {
    }

    /**
     * A migration that was running when the run was stopped: the database may be half-migrated.
     */
    public static function interrupted(MigrationTarget $target, string $output = ''): self
    {
        return new self($target, null, $output, interrupted: true);
    }

    /**
     * A migration the stopped run never began: the database is as it was.
     */
    public static function notStarted(MigrationTarget $target): self
    {
        return new self($target, null, interrupted: true, started: false);
    }

    public function succeeded(): bool
    {
        return 0 === $this->exitCode && !$this->timedOut && !$this->interrupted;
    }
}
