<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\CoursesBundle\Domain\Contract\LearningPathRepository;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * A learner's enrollments in learning paths in an access or erasure request, under the module `courses_paths`: the
 * person is named by the stable identifier the courses know them by. The export gives each enrollment — the path, when
 * it began and when it was completed —; the erasure deletes them.
 */
final readonly class PathsPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'courses_paths';

    public function __construct(
        private LearningPathRepository $paths,
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
        $learner = $this->learner($subject);
        $enrollments = $learner instanceof LearnerId ? $this->paths->enrollmentsOf($learner) : [];

        return [] === $enrollments ? [] : ['path_enrollments' => array_map(static fn (PathEnrollment $enrollment): array => [
            'enrollment' => $enrollment->id->value,
            'path' => $enrollment->path->value,
            'enrolled_at' => $enrollment->enrolledAt->format(DateTimeInterface::ATOM),
            'completed_at' => $enrollment->completedAt?->format(DateTimeInterface::ATOM),
        ], $enrollments)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $learner = $this->learner($subject);

        if ($learner instanceof LearnerId) {
            $this->paths->removeEnrollmentsOf($learner);
        }

        return ErasureReport::of(self::MODULE, ['phil_course_path_enrollment' => ErasureEffect::Deleted]);
    }

    private function learner(DataSubject $subject): ?LearnerId
    {
        try {
            return LearnerId::of($subject->value);
        } catch (CoursesException) {
            return null;
        }
    }
}
