<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Memory;

use Override;
use Phil\CodeOfConduct\Acceptance;
use Phil\CodeOfConduct\Contract\AcceptanceStore;

/**
 * Acceptances in memory, for tests and demonstrations.
 */
final class InMemoryAcceptanceStore implements AcceptanceStore
{
    /** @var array<string, Acceptance> */
    private array $latest = [];

    #[Override]
    public function latest(string $scope, string $member): ?Acceptance
    {
        return $this->latest[$scope."\x1F".$member] ?? null;
    }

    #[Override]
    public function add(Acceptance $acceptance): void
    {
        $key = $acceptance->scope."\x1F".$acceptance->member;
        $previous = $this->latest[$key] ?? null;

        if (!$previous instanceof Acceptance || $acceptance->version >= $previous->version) {
            $this->latest[$key] = $acceptance;
        }
    }
}
