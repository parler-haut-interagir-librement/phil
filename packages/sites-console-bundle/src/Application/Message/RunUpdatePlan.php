<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Message;

/**
 * Run an update plan: every batch left, or that many.
 */
final readonly class RunUpdatePlan
{
    /**
     * @param ?int    $batches the batches to run, null for all of them
     * @param ?string $actor   the user identifier of the operator who asked
     */
    public function __construct(
        public string $planId,
        public ?int $batches = null,
        public ?string $actor = null,
    ) {
    }
}
