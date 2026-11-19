<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Domain\Model\Receivable;
use Phil\CommerceWholesaleBundle\Domain\Repository\CreditRepository;

/**
 * Credit accounts and receivables, in memory.
 */
final class InMemoryCredit implements CreditRepository
{
    /** @var array<string, CreditAccount> */
    public array $accounts = [];

    /** @var list<Receivable> */
    public array $receivables = [];

    #[Override]
    public function findAccount(string $buyer): ?CreditAccount
    {
        return $this->accounts[$buyer] ?? null;
    }

    #[Override]
    public function addAccount(CreditAccount $account): void
    {
        $this->accounts[$account->buyer] = $account;
    }

    #[Override]
    public function findReceivable(string $id): ?Receivable
    {
        return array_find($this->receivables, static fn (Receivable $receivable): bool => $receivable->id === $id);
    }

    #[Override]
    public function receivableOfPayment(string $payment): ?Receivable
    {
        return array_find($this->receivables, static fn (Receivable $receivable): bool => $receivable->payment === $payment);
    }

    #[Override]
    public function addReceivable(Receivable $receivable): void
    {
        $this->receivables[] = $receivable;
    }

    #[Override]
    public function receivablesOf(string $buyer): array
    {
        return array_values(array_filter($this->receivables, static fn (Receivable $receivable): bool => $receivable->buyer === $buyer));
    }
}
