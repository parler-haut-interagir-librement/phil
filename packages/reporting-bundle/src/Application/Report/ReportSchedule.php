<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use Phil\ReportingBundle\Application\PeriodPreset;

/**
 * A report exported again and again: every `$every` — `1 day`, `1 week`, `1 month` — from the moment `$from` gives,
 * over the preset's period at that moment, and delivered to its recipients.
 */
final readonly class ReportSchedule
{
    /**
     * @param list<string>                         $recipients
     * @param array<string, bool|float|int|string> $parameters
     */
    public function __construct(
        public string $name,
        public string $report,
        public string $format,
        public string $every,
        public ?string $from,
        public PeriodPreset $period,
        public ?string $delivery,
        public array $recipients = [],
        public array $parameters = [],
    ) {
    }
}
