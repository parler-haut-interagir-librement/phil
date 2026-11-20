<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * The cash-difference report: the sessions closed over a period, each with what its count found over or short, and
 * the totals by currency. A difference beyond the threshold is flagged for someone to look at.
 */
final readonly class CashDifferences
{
    /**
     * @param int|null $threshold the difference, in minor units, beyond which a session is flagged; none when null
     */
    public function __construct(
        private CashSessionRepository $sessions,
        private ?int $threshold = null,
    ) {
        if (null !== $threshold && $threshold < 0) {
            throw InvalidValue::because('A cash-difference threshold is zero or more.');
        }
    }

    /**
     * @param string|null $register one register's sessions, or every register's
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to, ?string $register = null): CashDifferenceReport
    {
        if ($to <= $from) {
            throw InvalidValue::because('A cash-difference report ends after it starts.');
        }

        $rows = [];
        $over = [];
        $short = [];

        foreach ($this->sessions->closedBetween($from, $to, $register) as $session) {
            $difference = $session->overShort() ?? Money::zero($session->currency());
            $code = $difference->currency->code;
            $zero = Money::zero($difference->currency);
            $over[$code] = ($over[$code] ?? $zero)->add($difference->isPositive() ? $difference : $zero);
            $short[$code] = ($short[$code] ?? $zero)->add($difference->isNegative() ? $difference->abs() : $zero);
            $rows[] = ['session' => $session, 'difference' => $difference, 'flagged' => null !== $this->threshold && abs($difference->minorUnits) > $this->threshold];
        }

        ksort($over);
        ksort($short);

        return new CashDifferenceReport($from, $to, $register, $rows, $over, $short);
    }
}
