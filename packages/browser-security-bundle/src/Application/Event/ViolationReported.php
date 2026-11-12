<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Event;

use Phil\BrowserSecurityBundle\Domain\ValueObject\ViolationReport;

/**
 * A browser reported a violation not announced within the window of deduplication.
 */
final readonly class ViolationReported
{
    /**
     * @param string|null $client the anonymized address of the client, when the application keeps one
     */
    public function __construct(
        public ViolationReport $report,
        public ?string $client = null,
    ) {
    }
}
