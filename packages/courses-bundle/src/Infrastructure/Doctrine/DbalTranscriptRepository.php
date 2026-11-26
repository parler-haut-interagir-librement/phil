<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Domain\Contract\TranscriptRepository;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\TranscriptEntryId;

/**
 * The transcripts and the credits of the courses through DBAL.
 */
final readonly class DbalTranscriptRepository implements TranscriptRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(TranscriptEntry $entry): void
    {
        $this->rows->put(CourseTables::TRANSCRIPT, ['id' => $entry->id->value], [
            'learner' => $entry->learner->value,
            'course_id' => $entry->course->value,
            'course_title' => mb_substr($entry->courseTitle, 0, 255),
            'completed_at' => $entry->completedAt,
            'credit_hundredths' => $entry->credits?->hundredths,
            'credit_unit' => $entry->credits?->unit->value,
            'assignment_id' => $entry->assignment?->value,
            'cycle' => $entry->cycle,
        ], ['completed_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function of(LearnerId $learner): array
    {
        return array_map($this->entry(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::TRANSCRIPT.' WHERE learner = ? ORDER BY completed_at DESC, id DESC',
            [$learner->value],
        ));
    }

    #[Override]
    public function completedBetween(DateTimeImmutable $from, DateTimeImmutable $until): iterable
    {
        $rows = $this->connection->iterateAssociative(
            'SELECT * FROM '.CourseTables::TRANSCRIPT.' WHERE completed_at >= ? AND completed_at < ? ORDER BY completed_at, id',
            [$from, $until],
            [Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE],
        );

        foreach ($rows as $row) {
            yield $this->entry($row);
        }
    }

    #[Override]
    public function removeOf(LearnerId $learner): void
    {
        $this->connection->delete(CourseTables::TRANSCRIPT, ['learner' => $learner->value]);
    }

    #[Override]
    public function setCredits(CourseId $course, ?Credits $credits): void
    {
        if (!$credits instanceof Credits) {
            $this->connection->delete(CourseTables::CREDITS, ['course_id' => $course->value]);

            return;
        }

        $this->rows->put(CourseTables::CREDITS, ['course_id' => $course->value], ['hundredths' => $credits->hundredths, 'unit' => $credits->unit->value]);
    }

    #[Override]
    public function credits(CourseId $course): ?Credits
    {
        $row = $this->connection->fetchAssociative('SELECT hundredths, unit FROM '.CourseTables::CREDITS.' WHERE course_id = ?', [$course->value]);

        return false === $row ? null : $this->credit($row['hundredths'], $row['unit']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function entry(array $row): TranscriptEntry
    {
        $assignment = Rows::nullableText($row['assignment_id']);
        $cycle = Rows::nullableInt($row['cycle']);

        return new TranscriptEntry(
            TranscriptEntryId::of(Rows::text($row['id'])),
            LearnerId::of(Rows::text($row['learner'])),
            CourseId::of(Rows::text($row['course_id'])),
            Rows::text($row['course_title']),
            $this->rows->requiredMoment($row['completed_at']),
            $this->credit($row['credit_hundredths'], $row['credit_unit']),
            null === $assignment ? null : AssignmentId::of($assignment),
            null === $cycle ? null : max(1, $cycle),
        );
    }

    private function credit(mixed $hundredths, mixed $unit): ?Credits
    {
        $kind = CreditUnit::tryFrom(Rows::text($unit));

        return null === $hundredths || null === $kind ? null : Credits::hundredths(Rows::int($hundredths), $kind);
    }
}
