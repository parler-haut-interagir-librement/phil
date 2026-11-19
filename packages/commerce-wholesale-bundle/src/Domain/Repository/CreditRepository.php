<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Domain\Repository;

use Phil\CommerceWholesaleBundle\Domain\Model\CreditAccount;
use Phil\CommerceWholesaleBundle\Domain\Model\Receivable;

interface CreditRepository
{
    public function findAccount(string $buyer): ?CreditAccount;

    public function addAccount(CreditAccount $account): void;

    public function findReceivable(string $id): ?Receivable;

    public function receivableOfPayment(string $payment): ?Receivable;

    public function addReceivable(Receivable $receivable): void;

    /**
     * @return list<Receivable> the buyer's receivables, oldest first
     */
    public function receivablesOf(string $buyer): array;
}
