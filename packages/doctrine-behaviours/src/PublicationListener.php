<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\Publishable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Stamps the dates of a `Publishable` record as it enters a place of the publication workflow, in UTC. To listen to
 * `workflow.<name>.entered`; the record's flush stays the application's, as with every workflow.
 */
final readonly class PublicationListener
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EnteredEvent $event): void
    {
        $subject = $event->getSubject();

        if (!$subject instanceof Publishable) {
            return;
        }

        if ($event->getMarking()->has(PublicationWorkflow::PUBLISHED)) {
            $subject->markPublished(Utc::now($this->clock));
        } elseif ($event->getMarking()->has(PublicationWorkflow::UNPUBLISHED)) {
            $subject->markUnpublished(Utc::now($this->clock));
        }
    }
}
