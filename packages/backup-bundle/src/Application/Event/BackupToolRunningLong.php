<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Application\Event;

/**
 * A client tool has been running for longer than `phil_backup.warn_after` and still runs: a slow backup, or a stuck
 * one if it goes on. Identifiers only: the tool's name, not its command line.
 */
final readonly class BackupToolRunningLong
{
    public function __construct(
        public string $tool,
        public ?int $pid,
        public float $seconds,
    ) {
    }
}
