<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Repository\LedgerRepository;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * What this application and a partner owe each other, order by order, until they settle.
 */
final readonly class Ledger
{
    public function __construct(
        private LedgerRepository $ledger,
    ) {
    }

    /**
     * The open balance with a partner in one currency: positive when the partner owes this application.
     */
    public function balance(string $partner, Currency $currency): Money
    {
        $balance = Money::zero($currency);

        foreach ($this->ledger->openEntries($partner) as $entry) {
            if ($entry->amount->currency->equals($currency)) {
                $balance = $balance->add($entry->amount);
            }
        }

        return $balance;
    }

    /**
     * Settles every open entry with a partner in one currency, once the money moved.
     *
     * @return int how many entries were settled
     */
    public function settle(string $partner, Currency $currency, DateTimeImmutable $at): int
    {
        $settled = 0;

        foreach ($this->ledger->openEntries($partner) as $entry) {
            if ($entry->amount->currency->equals($currency)) {
                $entry->settle($at);
                ++$settled;
            }
        }

        return $settled;
    }
}
