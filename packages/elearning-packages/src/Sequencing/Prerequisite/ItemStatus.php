<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing\Prerequisite;

use Closure;
use Override;

/**
 * An item and the statuses that meet it: `passed` and `completed` for an item named alone, the status compared for
 * `item = "status"`, every other status for `item <> "status"`.
 *
 * @internal built by `Prerequisites::parse()`; not part of the public API
 */
final readonly class ItemStatus extends Node
{
    /**
     * @param list<string> $statuses
     */
    public function __construct(
        private string $identifier,
        private array $statuses,
        private bool $negated = false,
    ) {
    }

    #[Override]
    public function isMet(Closure $status): bool
    {
        return \in_array($status($this->identifier), $this->statuses, true) !== $this->negated;
    }

    #[Override]
    public function identifiers(): array
    {
        return [$this->identifier];
    }
}
