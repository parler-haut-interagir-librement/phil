<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Contract\WishListRepository;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\WishList;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\WishListItem;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Wish lists shared by a link, where others buy: a visitor who holds the link reads the list, adds what is still
 * wished for to their own cart, and the units count as bought once that order is paid in full.
 *
 * - **The link is a secret shown once**: only its SHA-256 is stored, and a malformed token is refused without a
 *   lookup.
 * - **A buyer's order names the list it buys from**, one list per order; the owner never learns who bought.
 * - **Units count when the order is paid**, on `OrderPurchased`, never more than are still wished for.
 *
 * Nothing is committed here: the application commits its unit of work.
 */
final readonly class WishLists
{
    public function __construct(
        private WishListRepository $lists,
        private OrderRepository $orders,
        private OrderModifier $modifier,
    ) {
    }

    public function create(string $owner, string $name, DateTimeImmutable $at): WishList
    {
        $list = WishList::create($owner, $name, $at);
        $this->lists->addWishList($list);

        return $list;
    }

    /**
     * Shares the list under a new link, and returns its token, shown once; the old link stops working.
     */
    public function share(WishList $list): string
    {
        [$token, $hash] = SecretToken::create();
        $list->shareUnder($hash);

        return $token;
    }

    /**
     * The list shared under the token, or `null`, whatever the reason.
     */
    public function shared(string $token): ?WishList
    {
        $hash = SecretToken::hash($token);

        return null === $hash ? null : $this->lists->findSharedWishList($hash);
    }

    /**
     * Adds to the buyer's cart a thing still wished for on the list, for no more units than are still wished for, and
     * marks the order as buying from the list.
     */
    public function buyFrom(WishList $list, Order $order, SellableReference $reference, int $quantity, PricingContext $context): OrderLine
    {
        $item = $list->item($reference);

        if (!$item instanceof WishListItem || $quantity < 1 || $quantity > $item->remaining()) {
            throw InvalidValue::because(\sprintf('The wish list %s still wishes for %d of %s.', $list->id, $item?->remaining() ?? 0, $reference->value()));
        }

        if (null !== $order->wishList() && $order->wishList() !== $list->id) {
            throw InvalidValue::because(\sprintf('The order %s already buys from another wish list.', $order->number));
        }

        $order->buyFromWishList($list->id);

        return $this->modifier->add($order, $reference, $quantity, $context);
    }

    /**
     * Counts the units of a paid order towards the list it buys from: what `OrderPurchased` triggers.
     */
    public function recordPurchase(OrderPurchased $event): void
    {
        $order = $this->orders->find($event->orderNumber);
        $listId = $order?->wishList();

        if (null === $listId) {
            return;
        }

        $list = $this->lists->findWishList($listId);

        if (!$list instanceof WishList) {
            return;
        }

        foreach ($order->lines() as $line) {
            $list->recordPurchase($line->reference, $line->quantity);
        }
    }
}
