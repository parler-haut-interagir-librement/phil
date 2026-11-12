<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Scheduling;

use DateTimeImmutable;
use Override;
use Phil\ContentBundle\Application\Contract\PublicationSchedule;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\ScheduledPublication;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\Domain\Model\ScheduledTransition;

/**
 * The schedule of publications on `phil/scheduled-transitions-bundle`'s `Scheduler`, each publication named by
 * `PublicationReferences`; `PublicationSubjects` hands it back to the bundle's workflow handler when it is due.
 */
final readonly class ScheduledTransitionsPublicationSchedule implements PublicationSchedule
{
    public function __construct(
        private Scheduler $scheduler,
        private PublicationReferences $references,
    ) {
    }

    #[Override]
    public function schedule(Publication $publication, string $transition, DateTimeImmutable $at): void
    {
        if (ScheduledPublication::PUBLISH !== $transition && ScheduledPublication::UNPUBLISH !== $transition) {
            throw InvalidContent::because(\sprintf('A publication is scheduled to "publish" or to "unpublish", not to "%s".', mb_substr($transition, 0, 64)));
        }

        $this->scheduler->schedule($this->references->referenceOf($publication), $transition, $at);
    }

    #[Override]
    public function cancel(Publication $publication, ?string $transition = null): int
    {
        return $this->scheduler->cancel($this->references->referenceOf($publication), $transition);
    }

    #[Override]
    public function pending(Publication $publication): array
    {
        return array_map(
            static fn (ScheduledTransition $pending): ScheduledPublication => new ScheduledPublication($pending->transition(), $pending->dueAt()),
            $this->scheduler->pendingFor($this->references->referenceOf($publication)),
        );
    }
}
