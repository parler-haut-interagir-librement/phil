<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\Money\Money;

/**
 * A session's end of day: the drawer — float, cash in and out, rounding, what it should hold, what was counted, over
 * or short —, the payments taken by method, the tips, and how many sales were rung up.
 */
final readonly class SessionReport
{
    /**
     * @param array<string, Money> $byMethod the payments taken, by method
     */
    public function __construct(
        public CashSession $session,
        public Money $float,
        public Money $cashSales,
        public Money $cashRefunds,
        public Money $paidIn,
        public Money $paidOut,
        public Money $drops,
        public Money $rounding,
        public array $byMethod,
        public Money $tips,
        public int $sales,
    ) {
    }

    public function expected(): Money
    {
        return $this->session->expected;
    }

    public function counted(): ?Money
    {
        return $this->session->counted;
    }

    public function overShort(): ?Money
    {
        return $this->session->overShort();
    }
}
