<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Contract\OrderChangeRepository;
use Phil\CommerceBundle\Domain\Contract\OrderJournal;
use Phil\CommerceBundle\Domain\Contract\PaymentLinkRepository;
use Phil\CommerceBundle\Domain\Contract\RefundRepository;
use Phil\CommerceBundle\Domain\Contract\WishListRepository;
use Phil\CommerceBundle\Domain\Model\OrderChange;
use Phil\CommerceBundle\Domain\Model\OrderStateChange;
use Phil\CommerceBundle\Domain\Model\PaymentLink;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\CommerceBundle\Domain\Model\WishList;

/**
 * The stores of wish lists, payment links, changes, the journal and refunds, in memory, in the order added.
 */
final class InMemoryCommerceStore implements WishListRepository, PaymentLinkRepository, OrderChangeRepository, OrderJournal, RefundRepository
{
    /** @var list<WishList> */
    public array $wishLists = [];

    /** @var list<PaymentLink> */
    public array $links = [];

    /** @var list<OrderChange> */
    public array $changes = [];

    /** @var list<OrderStateChange> */
    public array $moves = [];

    /** @var list<Refund> */
    public array $refunds = [];

    #[Override]
    public function findWishList(string $id): ?WishList
    {
        return array_find($this->wishLists, static fn (WishList $list): bool => $list->id === $id);
    }

    #[Override]
    public function findSharedWishList(string $tokenHash): ?WishList
    {
        return array_find($this->wishLists, static fn (WishList $list): bool => $list->isSharedUnder($tokenHash));
    }

    #[Override]
    public function wishListsOf(string $owner, int $limit = 100): array
    {
        return \array_slice(array_values(array_filter($this->wishLists, static fn (WishList $list): bool => $list->owner === $owner)), 0, $limit);
    }

    #[Override]
    public function addWishList(WishList $list): void
    {
        $this->wishLists[] = $list;
    }

    #[Override]
    public function removeWishList(WishList $list): void
    {
        $this->wishLists = array_values(array_filter($this->wishLists, static fn (WishList $kept): bool => $kept !== $list));
    }

    #[Override]
    public function findPaymentLinkByTokenHash(string $tokenHash): ?PaymentLink
    {
        return array_find($this->links, static fn (PaymentLink $link): bool => $link->tokenHash() === $tokenHash);
    }

    #[Override]
    public function paymentLinksOf(string $orderNumber): array
    {
        return array_values(array_filter($this->links, static fn (PaymentLink $link): bool => $link->orderNumber === $orderNumber));
    }

    #[Override]
    public function addPaymentLink(PaymentLink $link): void
    {
        $this->links[] = $link;
    }

    #[Override]
    public function findOrderChange(string $id): ?OrderChange
    {
        return array_find($this->changes, static fn (OrderChange $change): bool => $change->id === $id);
    }

    #[Override]
    public function changesOf(string $orderNumber): array
    {
        return array_values(array_filter($this->changes, static fn (OrderChange $change): bool => $change->orderNumber === $orderNumber));
    }

    #[Override]
    public function addOrderChange(OrderChange $change): void
    {
        $this->changes[] = $change;
    }

    #[Override]
    public function record(OrderStateChange $change): void
    {
        $this->moves[] = $change;
    }

    #[Override]
    public function movesOf(string $orderNumber, int $limit = 100): array
    {
        return \array_slice(array_values(array_filter($this->moves, static fn (OrderStateChange $move): bool => $move->orderNumber === $orderNumber)), 0, $limit);
    }

    #[Override]
    public function findRefund(string $id): ?Refund
    {
        return array_find($this->refunds, static fn (Refund $refund): bool => $refund->id === $id);
    }

    #[Override]
    public function refundsOf(string $paymentId): array
    {
        return array_values(array_filter($this->refunds, static fn (Refund $refund): bool => $refund->paymentId === $paymentId));
    }

    #[Override]
    public function addRefund(Refund $refund): void
    {
        $this->refunds[] = $refund;
    }
}
