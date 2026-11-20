<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\CoursesBundle\Application\Training\Transcript;
use Phil\CoursesBundle\Domain\Contract\AssignmentRepository;
use Phil\CoursesBundle\Domain\Contract\TranscriptRepository;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The mandatory training and the transcript of a learner in an access or erasure request, under the module
 * `courses_training`: the person is named by the stable identifier the courses know them by. The export gives their
 * obligations — the assignment, the course, the cycle, the due date, the completion, the renewal and the reminders
 * sent — and their transcript, with its totals. The erasure deletes the obligations, takes their name off the
 * assignments they made, which stay unsigned, and deletes the transcript, unless the application keeps transcripts
 * under a legal obligation, as continuing education often asks: then the transcript survives, and the report says so.
 */
final readonly class TrainingPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'courses_training';

    /**
     * @param bool $keepTranscripts whether the transcripts survive an erasure, under a legal obligation
     */
    public function __construct(
        private AssignmentRepository $assignments,
        private TranscriptRepository $transcripts,
        private bool $keepTranscripts = false,
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

        if (!$learner instanceof LearnerId) {
            return [];
        }

        $obligations = $this->assignments->obligationsOf($learner);
        $entries = $this->transcripts->of($learner);

        $transcript = new Transcript($learner, $entries)->toArray();

        return array_filter([
            'obligations' => array_map(static fn (Obligation $obligation): array => [
                'assignment' => $obligation->assignment->value,
                'course' => $obligation->course->value,
                'cycle' => $obligation->cycle,
                'assigned_at' => $obligation->assignedAt->format(DateTimeInterface::ATOM),
                'due_at' => $obligation->dueAt->format(DateTimeInterface::ATOM),
                'completed_at' => $obligation->completedAt?->format(DateTimeInterface::ATOM),
                'renews_at' => $obligation->renewsAt?->format(DateTimeInterface::ATOM),
                'reminders' => array_map(static fn (ReminderStage $stage): string => $stage->value, $obligation->reminders),
            ], $obligations),
            'transcript' => $transcript['entries'],
            'credit_totals' => $transcript['totals'],
        ], static fn (array $records): bool => [] !== $records);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $learner = $this->learner($subject);

        if ($learner instanceof LearnerId) {
            $this->assignments->removeOf($learner);

            if (!$this->keepTranscripts) {
                $this->transcripts->removeOf($learner);
            }
        }

        return ErasureReport::of(self::MODULE, [
            'phil_course_obligation' => ErasureEffect::Deleted,
            'phil_course_assignment' => ErasureEffect::Redacted,
            'phil_course_transcript' => $this->keepTranscripts ? ErasureEffect::Survives : ErasureEffect::Deleted,
        ]);
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
