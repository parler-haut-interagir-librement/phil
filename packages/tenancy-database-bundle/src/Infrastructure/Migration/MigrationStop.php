<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Migration;

/**
 * A request to stop a run of migrations, made from a signal handler while `MigrationRunner::run()` waits on its
 * processes. The first request stops the running migrations with SIGTERM and gives them the runner's grace period; a
 * second one, an operator pressing Ctrl+C again, kills them at once.
 */
final class MigrationStop
{
    private ?int $signal = null;

    private int $requests = 0;

    /**
     * @param int $signal the signal that asked for the stop, kept from the first request
     */
    public function request(int $signal): void
    {
        $this->signal ??= $signal;
        ++$this->requests;
    }

    public function isRequested(): bool
    {
        return 0 !== $this->requests;
    }

    /**
     * Whether the stop was asked for again: the running migrations are killed without their grace period.
     */
    public function isForced(): bool
    {
        return $this->requests > 1;
    }

    /**
     * The signal of the first request, or `null` before any.
     */
    public function signal(): ?int
    {
        return $this->signal;
    }
}
