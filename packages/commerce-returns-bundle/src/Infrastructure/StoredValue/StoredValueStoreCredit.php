<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\StoredValue;

use DateTimeImmutable;
use Override;
use Phil\CommerceReturnsBundle\Application\Port\StoreCreditGranter;
use Phil\CommerceStoredValueBundle\Application\StoreCredits;
use Phil\Money\Money;

/**
 * Store credit through `phil/commerce-stored-value-bundle`, registered when that module is.
 */
final readonly class StoredValueStoreCredit implements StoreCreditGranter
{
    public function __construct(
        private StoreCredits $storeCredits,
    ) {
    }

    #[Override]
    public function grant(string $customer, Money $amount, DateTimeImmutable $at, string $note, string $orderNumber): void
    {
        $this->storeCredits->grant($customer, $amount, $at, $note, $orderNumber);
    }
}
