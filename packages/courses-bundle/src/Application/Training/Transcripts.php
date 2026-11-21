<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\TranscriptRepository;
use Phil\CoursesBundle\Domain\Event\CreditsGranted;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\TranscriptEntryId;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The transcripts and the credits: each completion of a course enters the learner's transcript with the credits the
 * course grants at that moment and the assignment it fulfilled; a renewed course completed again enters it again.
 * With the groups of equivalent courses, a course that grants no credits of its own grants those of the first course
 * of its group that does, and a completion that fulfils no obligation while the transcript already holds an
 * equivalent course enters it without credits: a group is credited once a cycle.
 */
final readonly class Transcripts
{
    public function __construct(
        private TranscriptRepository $transcripts,
        private CourseRepository $courses,
        private ?EventDispatcherInterface $events = null,
        private ?Equivalences $equivalences = null,
    ) {
    }

    /**
     * Enters the completion of the enrollment, at that moment, in the learner's transcript.
     *
     * @param list<Obligation> $fulfilled the obligations the completion fulfilled
     */
    public function record(Enrollment $enrollment, DateTimeImmutable $at, array $fulfilled = []): TranscriptEntry
    {
        $obligation = $fulfilled[0] ?? null;
        $equivalents = $this->equivalences?->equivalents($enrollment->course) ?? [];
        $credited = null !== $obligation || !$this->holdsAny($enrollment->learner, $equivalents);
        $entry = new TranscriptEntry(
            TranscriptEntryId::generate(),
            $enrollment->learner,
            $enrollment->course,
            $this->courses->find($enrollment->course)->title ?? $enrollment->course->value,
            $at,
            $credited ? $this->creditsOf($enrollment->course, $equivalents) : null,
            $obligation?->assignment,
            $obligation?->cycle,
        );
        $this->transcripts->save($entry);
        $this->events?->dispatch(new CreditsGranted($entry->id->value, $enrollment->learner->value, $enrollment->course->value));

        return $entry;
    }

    /**
     * The credits of the course, else those of the first of its equivalent courses that grants some.
     *
     * @param list<CourseId> $equivalents
     */
    private function creditsOf(CourseId $course, array $equivalents): ?Credits
    {
        foreach ([$course, ...$equivalents] as $candidate) {
            $credits = $this->transcripts->credits($candidate);

            if ($credits instanceof Credits) {
                return $credits;
            }
        }

        return null;
    }

    /**
     * Whether the learner's transcript holds one of these courses.
     *
     * @param list<CourseId> $courses
     */
    private function holdsAny(LearnerId $learner, array $courses): bool
    {
        if ([] === $courses) {
            return false;
        }

        $held = array_map(static fn (CourseId $course): string => $course->value, $courses);

        return array_any($this->transcripts->of($learner), static fn (TranscriptEntry $entry): bool => \in_array($entry->course->value, $held, true));
    }

    public function of(LearnerId $learner): Transcript
    {
        return new Transcript($learner, $this->transcripts->of($learner));
    }

    /**
     * Sets the credits the course grants from now on, or none; the completions entered before keep theirs.
     *
     * @throws UnknownRecord when the course is not known
     */
    public function credit(CourseId $course, ?Credits $credits): void
    {
        $this->courses->find($course) ?? throw UnknownRecord::of('course', $course->value);
        $this->transcripts->setCredits($course, $credits);
    }

    public function credits(CourseId $course): ?Credits
    {
        return $this->transcripts->credits($course);
    }
}
