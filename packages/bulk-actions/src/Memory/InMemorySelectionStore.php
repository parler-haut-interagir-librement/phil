<?php

declare(strict_types=1);

namespace Phil\BulkActions\Memory;

use DateTimeImmutable;
use Phil\BulkActions\Contract\SelectionStore;
use Phil\BulkActions\Selection\PendingSelection;

/**
 * A selection store in an array, for tests and for a single process. It keeps what toArray() gives, as a real store
 * would.
 */
final class InMemorySelectionStore implements SelectionStore
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function save(PendingSelection $selection): void
    {
        $this->rows[$selection->fingerprint] = $selection->toArray();
    }

    public function peek(string $fingerprint): ?PendingSelection
    {
        $row = $this->rows[$fingerprint] ?? null;

        return null === $row ? null : PendingSelection::fromArray($row);
    }

    public function take(string $fingerprint): ?PendingSelection
    {
        $selection = $this->peek($fingerprint);
        unset($this->rows[$fingerprint]);

        return $selection;
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    /**
     * Forgets the selections that expired before the moment given.
     */
    public function forgetExpired(DateTimeImmutable $before): void
    {
        foreach ($this->rows as $fingerprint => $row) {
            if (PendingSelection::fromArray($row)->expiresAt < $before) {
                unset($this->rows[$fingerprint]);
            }
        }
    }
}
