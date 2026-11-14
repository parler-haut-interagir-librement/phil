<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Domain\Event;

/**
 * An activity of continuing education recorded and stored. It carries identifiers only — the activity's and the
 * member's —: a listener that needs its title or its credits reads it from the repository.
 */
final readonly class LearningActivityRecorded
{
    public function __construct(
        public string $activityId,
        public string $member,
    ) {
    }
}
