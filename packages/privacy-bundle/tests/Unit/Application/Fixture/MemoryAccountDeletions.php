<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use RuntimeException;

/**
 * Hands out copies, as a database does: a change counts once it is saved.
 */
final class MemoryAccountDeletions implements AccountDeletionStore
{
    /**
     * @var array<string, AccountDeletion>
     */
    public array $rows = [];

    public int $saves = 0;

    public bool $failing = false;

    #[Override]
    public function find(string $id): ?AccountDeletion
    {
        return isset($this->rows[$id]) ? clone $this->rows[$id] : null;
    }

    #[Override]
    public function openFor(DataSubject $subject): ?AccountDeletion
    {
        foreach ($this->rows as $row) {
            if ($row->isOpen() && $row->subject->equals($subject)) {
                return clone $row;
            }
        }

        return null;
    }

    #[Override]
    public function save(AccountDeletion $deletion): void
    {
        if ($this->failing) {
            throw new RuntimeException('The store is down.');
        }

        $this->rows[$deletion->id] = clone $deletion;
        ++$this->saves;
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->rows, static fn (AccountDeletion $row): bool => $row->isDue($now));

        return \array_slice(array_keys($due), 0, $limit);
    }
}
