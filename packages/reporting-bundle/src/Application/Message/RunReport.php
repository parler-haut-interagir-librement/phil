<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Message;

/**
 * Export and deliver the report job kept under this identifier. The message carries nothing else: the job's request
 * and recipients wait in the job store.
 */
final readonly class RunReport
{
    public function __construct(
        public string $job,
    ) {
    }
}
