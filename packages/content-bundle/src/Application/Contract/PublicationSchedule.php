<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Contract;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\ScheduledPublication;
use Phil\ContentBundle\Domain\Exception\InvalidContent;

/**
 * Publishing or taking offline a content in one locale at a set moment. The bundle implements it with
 * `ScheduledTransitionsPublicationSchedule` when the kernel registers `phil/scheduled-transitions-bundle`, whose
 * runs apply the transition through the `phil_content_publication` workflow, under its guards; an application without
 * that bundle implements it over its own scheduler, or does without scheduling.
 */
interface PublicationSchedule
{
    /**
     * Plans `publish` or `unpublish` at `$at`; a transition already planned for the publication moves to `$at`.
     *
     * @throws InvalidContent when the transition is neither `publish` nor `unpublish`
     */
    public function schedule(Publication $publication, string $transition, DateTimeImmutable $at): void;

    /**
     * Cancels what is planned for the publication — one transition, or both —, and returns how many were cancelled.
     */
    public function cancel(Publication $publication, ?string $transition = null): int;

    /**
     * What is planned for the publication, soonest first.
     *
     * @return list<ScheduledPublication>
     */
    public function pending(Publication $publication): array;
}
