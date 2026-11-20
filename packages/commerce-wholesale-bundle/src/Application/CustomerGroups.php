<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Application;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceWholesaleBundle\Domain\Exception\WholesaleRefused;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Model\Membership;
use Phil\CommerceWholesaleBundle\Domain\Repository\CustomerGroupRepository;
use Phil\Money\Money;

/**
 * Who belongs to which group, and whether an order meets its group's terms. The application reads `groupOf()` when a
 * buyer starts an order, and hands it to the core's `OrderModifier::changeAudience()`.
 */
final readonly class CustomerGroups
{
    public function __construct(
        private CustomerGroupRepository $groups,
    ) {
    }

    /**
     * Puts the buyer in the group, or moves them to it.
     */
    public function join(string $buyer, string $groupCode): void
    {
        $group = $this->group($groupCode);
        $membership = $this->groups->membershipOf($buyer);

        if (!$membership instanceof Membership) {
            $this->groups->addMembership(Membership::of($buyer, $group));

            return;
        }

        $membership->moveTo($group);
    }

    public function leave(string $buyer): void
    {
        $membership = $this->groups->membershipOf($buyer);

        if ($membership instanceof Membership) {
            $this->groups->removeMembership($membership);
        }
    }

    /**
     * The code of the buyer's group while it is active; `null` for a buyer of no group.
     */
    public function groupOf(string $buyer): ?string
    {
        $code = $this->groups->membershipOf($buyer)?->group();
        $group = null === $code ? null : $this->groups->findGroup($code);

        return $group instanceof CustomerGroup && $group->isActive() ? $group->code : null;
    }

    /**
     * What the order still lacks to reach its group's minimum: zero once it does, or when it has no group.
     */
    public function shortOfMinimum(Order $order): Money
    {
        $group = null === $order->customerGroup() ? null : $this->groups->findGroup($order->customerGroup());

        if (!$group instanceof CustomerGroup || !$group->isActive() || !$group->currency->equals($order->currency)) {
            return Money::zero($order->currency);
        }

        $short = $group->minimumOrder()->subtract($order->itemsTotal());

        return $short->isPositive() ? $short : Money::zero($order->currency);
    }

    private function group(string $code): CustomerGroup
    {
        return $this->groups->findGroup($code) ?? throw WholesaleRefused::unknownGroup($code);
    }
}
