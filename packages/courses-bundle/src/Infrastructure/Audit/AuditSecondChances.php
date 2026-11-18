<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Audit;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\CoursesBundle\Domain\Event\RetryGranted;
use Phil\CoursesBundle\Domain\Event\RetryRefused;
use Phil\CoursesBundle\Domain\Event\RetryRequested;
use Phil\CoursesBundle\Domain\Event\RetryWaived;

/**
 * Writes the second chances to the trail of phil/audit-bundle: who asked, who granted, refused or waived, for which
 * enrollment and activity — identifiers only, never a reason or a note, which may hold personal words.
 */
final readonly class AuditSecondChances
{
    public const string SUBJECT = 'course_retry';

    public function __construct(
        private AuditTrail $trail,
        private string $segment = 'courses',
    ) {
    }

    public function requested(RetryRequested $event): void
    {
        $this->trail->record($this->segment, ['enrollment' => $event->enrollment, 'activity' => $event->activity], Facts::of('courses.retry.requested', null, Subject::of(self::SUBJECT, $event->request)));
    }

    public function granted(RetryGranted $event): void
    {
        $this->trail->record($this->segment, ['enrollment' => $event->enrollment, 'activity' => $event->activity], Facts::of('courses.retry.granted', $event->decidedBy, Subject::of(self::SUBJECT, $event->request)));
    }

    public function refused(RetryRefused $event): void
    {
        $this->trail->record($this->segment, ['enrollment' => $event->enrollment, 'activity' => $event->activity], Facts::of('courses.retry.refused', $event->decidedBy, Subject::of(self::SUBJECT, $event->request)));
    }

    public function waived(RetryWaived $event): void
    {
        $this->trail->record($this->segment, ['enrollment' => $event->enrollment, 'activity' => $event->activity], Facts::of('courses.retry.waived', $event->decidedBy, Subject::of(self::SUBJECT, $event->request)));
    }
}
