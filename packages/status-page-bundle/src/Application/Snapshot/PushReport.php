<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Snapshot;

/**
 * What a push did: the targets that took the snapshot, and the reason each other one did not — the short name of the
 * exception its publisher threw, never its message, which may carry a host or a credential.
 */
final readonly class PushReport
{
    /**
     * @param list<string>          $published
     * @param array<string, string> $failed
     */
    public function __construct(
        public Snapshot $snapshot,
        public array $published,
        public array $failed,
    ) {
    }

    public function isSuccessful(): bool
    {
        return [] === $this->failed;
    }
}
