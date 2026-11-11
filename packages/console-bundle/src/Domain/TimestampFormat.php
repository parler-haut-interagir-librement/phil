<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain;

use DateTimeImmutable;

/**
 * How the time before each output line is written.
 */
enum TimestampFormat: string
{
    /**
     * The moment in ISO 8601, to the millisecond: `2026-03-14T09:26:53.589+00:00`.
     */
    case Iso = 'iso';

    /**
     * The seconds since the command started, to the millisecond: `+12.345s`.
     */
    case Relative = 'relative';

    public function render(DateTimeImmutable $now, DateTimeImmutable $start): string
    {
        if (self::Iso === $this) {
            return $now->format('Y-m-d\TH:i:s.vP');
        }

        return \sprintf('+%.3fs', Elapsed::seconds($start, $now));
    }
}
