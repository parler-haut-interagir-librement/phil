<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\ScormAttemptRepository;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The SCORM attempts in an access or erasure request: the person is the learner, named by the stable identifier the
 * courses know them by. The export gives each attempt with what the platform read of it and its tracking data — what
 * the content wrote, the learner's answers to the interactions among it —; the erasure deletes the attempts and their
 * data, and runs again without harm.
 */
final readonly class ScormPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'courses_scorm';

    public function __construct(
        private EnrollmentRepository $enrollments,
        private ScormAttemptRepository $attempts,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $attempts = $this->attempts->ofEnrollments(...array_map(static fn (Enrollment $enrollment): EnrollmentId => $enrollment->id, $this->enrollmentsOf($subject)));

        return [] === $attempts ? [] : ['attempts' => array_map($this->exported(...), $attempts)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->attempts->removeOfEnrollments(...array_map(static fn (Enrollment $enrollment): EnrollmentId => $enrollment->id, $this->enrollmentsOf($subject)));

        return ErasureReport::of(self::MODULE, [
            'phil_course_scorm_attempt' => ErasureEffect::Deleted,
            'phil_course_scorm_data' => ErasureEffect::Deleted,
        ]);
    }

    /**
     * @return list<Enrollment>
     */
    private function enrollmentsOf(DataSubject $subject): array
    {
        try {
            return $this->enrollments->ofLearner(LearnerId::of($subject->value));
        } catch (CoursesException) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exported(ScormAttempt $attempt): array
    {
        return [
            'id' => $attempt->id->value,
            'enrollment' => $attempt->enrollment->value,
            'activity' => $attempt->activity->value,
            'number' => $attempt->number,
            'scorm_version' => $attempt->version->value,
            'state' => $attempt->state->value,
            'completion' => $attempt->status->completion,
            'success' => $attempt->status->success,
            'score' => $attempt->status->score,
            'total_time' => $attempt->totalTime->interval(),
            'started_at' => $attempt->startedAt->format(DateTimeInterface::ATOM),
            'updated_at' => $attempt->updatedAt->format(DateTimeInterface::ATOM),
            'ended_at' => $attempt->endedAt?->format(DateTimeInterface::ATOM),
            'tracking_data' => $attempt->data,
        ];
    }
}
