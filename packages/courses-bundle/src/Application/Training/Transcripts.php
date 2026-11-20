<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateTimeImmutable;
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
 */
final readonly class Transcripts
{
    public function __construct(
        private TranscriptRepository $transcripts,
        private CourseRepository $courses,
        private ?EventDispatcherInterface $events = null,
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
        $entry = new TranscriptEntry(
            TranscriptEntryId::generate(),
            $enrollment->learner,
            $enrollment->course,
            $this->courses->find($enrollment->course)->title ?? $enrollment->course->value,
            $at,
            $this->transcripts->credits($enrollment->course),
            $obligation?->assignment,
            $obligation?->cycle,
        );
        $this->transcripts->save($entry);
        $this->events?->dispatch(new CreditsGranted($entry->id->value, $enrollment->learner->value, $enrollment->course->value));

        return $entry;
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
