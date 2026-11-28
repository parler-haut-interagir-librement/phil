<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;

/**
 * The update of one site within a plan: its batch, its outcome and the agent's message.
 */
final readonly class UpdateStep
{
    public function __construct(
        public string $siteId,
        public int $batch,
        public StepOutcome $outcome,
        public string $message = '',
        public ?DateTimeImmutable $finishedAt = null,
    ) {
    }
}
