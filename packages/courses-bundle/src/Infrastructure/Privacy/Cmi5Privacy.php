<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Privacy;

use DateTimeInterface;
use JsonException;
use Override;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningRecordStore;
use Phil\CoursesBundle\Infrastructure\Xapi\HttpLearningRecordStore;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The cmi5 and xAPI records of a learner in an access or erasure request: the person is the learner, named by the
 * stable identifier the courses know them by, and their records are those of the registrations of their enrollments —
 * the launch sessions, the statements and the state documents the store inside the bundle keeps, of the cmi5 units and
 * of the xAPI activities alike, and the blocks and courses whose `satisfied` statement was issued.
 *
 * The export gives each of them, a statement and a document as the JSON they were sent as. The erasure deletes them,
 * and runs again without harm.
 *
 * A learning record store outside the bundle keeps what it was sent: a remote store keeps the statements — xAPI gives
 * no way to erase one —, a store of the application's own keeps the statements and the state documents. The export
 * says so, with the store and the registrations to ask its operator about; the erasure reports that store as
 * surviving, and keeps the notes of the `satisfied` statements the store holds, so that none is issued twice.
 */
final readonly class Cmi5Privacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'courses_cmi5';

    /** The store outside the bundle, in an erasure report. */
    public const string ELSEWHERE = 'learning_record_store';

    public function __construct(
        private EnrollmentRepository $enrollments,
        private LaunchSessionRepository $sessions,
        private DbalLearningRecordStore $local,
        private LearningRecordStore $store,
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
        $enrollments = $this->enrollmentsOf($subject);
        $registrations = array_map(static fn (Enrollment $enrollment): string => $enrollment->registration, $enrollments);
        $sessions = $this->sessions->ofEnrollments(...$this->ids($enrollments));
        $statements = $this->local->statementsOf($registrations);
        $states = $this->local->statesOf($registrations);
        $satisfactions = $this->satisfactions($enrollments);

        if ([] === $sessions && [] === $statements && [] === $states && [] === $satisfactions) {
            return [];
        }

        return array_filter([
            'registrations' => array_map(static fn (Enrollment $enrollment): array => [
                'enrollment' => $enrollment->id->value,
                'course' => $enrollment->course->value,
                'registration' => $enrollment->registration,
            ], $enrollments),
            'launch_sessions' => array_map(static fn (LaunchSession $session): array => [
                'id' => $session->id->value,
                'enrollment' => $session->enrollment->value,
                'activity' => $session->activity->value,
                'state' => $session->state->value,
                'launched_at' => $session->launchedAt->format(DateTimeInterface::ATOM),
                'ended_at' => $session->endedAt?->format(DateTimeInterface::ATOM),
            ], $sessions),
            'statements' => array_map(static fn (array $statement): array => [
                'id' => $statement['id'],
                'registration' => $statement['registration'],
                'stored_at' => $statement['stored_at']->format(DateTimeInterface::ATOM),
                'statement' => self::json($statement['statement']),
            ], $statements),
            'state_documents' => array_map(static fn (array $state): array => [
                'registration' => $state['registration'],
                'activity_sha256' => $state['activity_sha256'],
                'state_id' => $state['state_id'],
                'updated_at' => $state['updated_at']->format(DateTimeInterface::ATOM),
                'document' => self::json($state['document']),
            ], $states),
            'satisfactions' => $satisfactions,
            'kept_elsewhere' => $this->elsewhere($registrations),
        ], static fn (array $records): bool => [] !== $records);
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $enrollments = $this->enrollmentsOf($subject);
        $this->sessions->removeOfEnrollments(...$this->ids($enrollments));
        $this->local->erase(array_map(static fn (Enrollment $enrollment): string => $enrollment->registration, $enrollments));
        $statementsHere = $this->store instanceof DbalLearningRecordStore;

        if ($statementsHere) {
            foreach ($enrollments as $enrollment) {
                foreach ($this->enrollments->satisfactions($enrollment->id) as $subjectId) {
                    $this->enrollments->releaseSatisfaction($enrollment->id, $subjectId);
                }
            }
        }

        return ErasureReport::of(self::MODULE, [
            'phil_course_cmi5_session' => ErasureEffect::Deleted,
            'phil_course_statement' => ErasureEffect::Deleted,
            'phil_course_state' => ErasureEffect::Deleted,
            'phil_course_satisfaction' => $statementsHere ? ErasureEffect::Deleted : ErasureEffect::Survives,
        ] + ($statementsHere ? [] : [self::ELSEWHERE => ErasureEffect::Survives]));
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
     * @param list<Enrollment> $enrollments
     *
     * @return list<array{enrollment: string, subject: string}>
     */
    private function satisfactions(array $enrollments): array
    {
        $satisfactions = [];

        foreach ($enrollments as $enrollment) {
            foreach ($this->enrollments->satisfactions($enrollment->id) as $subjectId) {
                $satisfactions[] = ['enrollment' => $enrollment->id->value, 'subject' => $subjectId];
            }
        }

        return $satisfactions;
    }

    /**
     * What a store outside the bundle keeps of the registrations, and where.
     *
     * @param list<string> $registrations
     *
     * @return array<string, mixed>
     */
    private function elsewhere(array $registrations): array
    {
        if ($this->store instanceof DbalLearningRecordStore) {
            return [];
        }

        $remote = $this->store instanceof HttpLearningRecordStore;

        return [
            'records' => $remote ? ['statements'] : ['statements', 'state_documents'],
            'store' => $remote ? $this->store->endpoint : $this->store::class,
            'registrations' => $registrations,
            'note' => $remote
                ? 'The statements of these registrations were sent to this remote learning record store, which keeps them: the platform can neither read nor erase them there. Ask its operator, with the registrations.'
                : 'The statements and state documents of these registrations are kept by this learning record store of the application, which the courses neither read nor erase.',
        ];
    }

    /**
     * @param list<Enrollment> $enrollments
     *
     * @return list<EnrollmentId>
     */
    private function ids(array $enrollments): array
    {
        return array_map(static fn (Enrollment $enrollment): EnrollmentId => $enrollment->id, $enrollments);
    }

    private static function json(string $text): mixed
    {
        try {
            return json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $text;
        }
    }
}
