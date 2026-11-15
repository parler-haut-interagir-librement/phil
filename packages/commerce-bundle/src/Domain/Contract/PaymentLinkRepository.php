<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use Phil\CommerceBundle\Domain\Model\PaymentLink;

/**
 * Finds and stores payment links. `Infrastructure\Doctrine\DoctrinePaymentLinkRepository` is the Doctrine adapter.
 */
interface PaymentLinkRepository
{
    public function findPaymentLinkByTokenHash(string $tokenHash): ?PaymentLink;

    /**
     * @return list<PaymentLink> the order's links, oldest first
     */
    public function paymentLinksOf(string $orderNumber): array;

    public function addPaymentLink(PaymentLink $link): void;
}
