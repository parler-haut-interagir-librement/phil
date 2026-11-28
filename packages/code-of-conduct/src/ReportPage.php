<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

/**
 * A page of the reports a responsible person may handle. The reports they may not handle are left out, so a page may
 * hold fewer than asked; the next page starts after `$next`, `null` on the last page.
 */
final readonly class ReportPage
{
    /**
     * @param list<Report> $reports
     */
    public function __construct(
        public array $reports,
        public ?string $next,
    ) {
    }
}
