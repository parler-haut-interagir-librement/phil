<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * A store where the request is cancelled, or removed, between the first read and the read made under the lock.
 */
class StaleFirstRead implements AccountDeletionStore
{
    private int $reads = 0;

    public function __construct(
        private readonly MemoryAccountDeletions $store,
        private readonly string $id,
        private readonly bool $removed = false,
    ) {
    }

    #[Override]
    public function find(string $id): ?AccountDeletion
    {
        $found = $this->store->find($id);

        if (2 === ++$this->reads && $this->removed) {
            unset($this->store->rows[$this->id]);

            return null;
        }

        if (2 === $this->reads) {
            $this->store->rows[$this->id]->setPlace(DeletionPlace::Cancelled);
            $found = $this->store->find($id);
        }

        return $found;
    }

    #[Override]
    public function openFor(DataSubject $subject): ?AccountDeletion
    {
        return $this->store->openFor($subject);
    }

    #[Override]
    public function save(AccountDeletion $deletion): void
    {
        $this->store->save($deletion);
    }

    #[Override]
    public function dueIdentifiers(DateTimeImmutable $now, int $limit): array
    {
        return $this->store->dueIdentifiers($now, $limit);
    }
}
