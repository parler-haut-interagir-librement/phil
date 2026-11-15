<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application\Event;

use Phil\ModerationBundle\Domain\ValueObject\Report;

/**
 * A report was filed: by a member, or by the bundle for a held, flagged or hidden content.
 */
final readonly class ReportFiled
{
    public function __construct(
        public Report $report,
    ) {
    }
}
