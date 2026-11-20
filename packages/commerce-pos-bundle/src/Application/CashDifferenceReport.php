<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Model\CashSession;
use Phil\Money\Money;

/**
 * The sessions closed over a period, with what each count found over — positive — or short — negative —, and the
 * totals over and short by currency.
 */
final readonly class CashDifferenceReport
{
    /**
     * @param list<array{session: CashSession, difference: Money, flagged: bool}> $sessions oldest close first
     * @param array<string, Money>                                                $over     by currency code
     * @param array<string, Money>                                                $short    by currency code, positive
     */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public ?string $register,
        public array $sessions,
        public array $over,
        public array $short,
    ) {
    }

    /**
     * @return list<CashSession> the sessions whose difference is beyond the threshold
     */
    public function flagged(): array
    {
        $flagged = [];

        foreach ($this->sessions as $row) {
            if ($row['flagged']) {
                $flagged[] = $row['session'];
            }
        }

        return $flagged;
    }
}
