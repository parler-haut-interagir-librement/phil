<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Contract\RetryRequestRepository;
use Phil\CoursesBundle\Domain\Event\RetryGranted;
use Phil\CoursesBundle\Domain\Event\RetryRefused;
use Phil\CoursesBundle\Domain\Event\RetryRequested;
use Phil\CoursesBundle\Domain\Event\RetryWaived;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\Progress\RetryDecision;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Second chances: a learner who failed every try of an activity asks for one; a teacher grants it, which gives one
 * more try, refuses it, or waives the activity instead, which the course then counts satisfied — each decision with
 * the teacher's note. Each step is told to the event dispatcher, which hands it to phil/audit-bundle when it is
 * installed.
 */
final readonly class SecondChances
{
    public function __construct(
        private RetryRequestRepository $requests,
        private Courses $courses,
        private Enrollments $enrollments,
        private Availabilities $availabilities,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @throws ActivityUnavailable when the learner is not out of tries, or asked already
     * @throws InvalidCourse       when the reason is too long
     */
    public function request(Enrollment $enrollment, ActivityId $activity, string $reason): RetryRequest
    {
        $status = $this->availabilities->of($enrollment, $activity)->availability->status;

        if (AvailabilityStatus::RetryNeeded !== $status) {
            throw ActivityUnavailable::because(AvailabilityStatus::RetryRequested === $status ? 'A second chance is asked for already.' : 'A second chance is asked for once every try is failed.');
        }

        $request = new RetryRequest(RetryRequestId::generate(), $enrollment->id, $activity, $reason, $this->clock->now());
        $this->requests->save($request);
        $this->events?->dispatch(new RetryRequested($request->id->value, $enrollment->id->value, $activity->value));

        return $request;
    }

    /**
     * @param string $by   the stable identifier of the teacher who grants it
     * @param string $note why, as the teacher writes it
     *
     * @throws UnknownRecord when the request is not known
     * @throws InvalidCourse when the request is decided already, the teacher is not an identifier or the note is too long
     */
    public function grant(RetryRequestId $id, string $by, string $note = ''): RetryRequest
    {
        $request = $this->decide($id, RetryDecision::Granted, $by, $note);
        $this->enrollments->grantTry($this->enrollments->get($request->enrollment), $this->courses->activity($request->activity));
        $this->events?->dispatch(new RetryGranted($request->id->value, $request->enrollment->value, $request->activity->value, $by));

        return $request;
    }

    /**
     * @param string $by   the stable identifier of the teacher who refuses it
     * @param string $note why, as the teacher writes it
     *
     * @throws UnknownRecord when the request is not known
     * @throws InvalidCourse when the request is decided already, the teacher is not an identifier or the note is too long
     */
    public function refuse(RetryRequestId $id, string $by, string $note = ''): RetryRequest
    {
        $request = $this->decide($id, RetryDecision::Refused, $by, $note);
        $this->events?->dispatch(new RetryRefused($request->id->value, $request->enrollment->value, $request->activity->value, $by));

        return $request;
    }

    /**
     * Waives the activity instead of granting a try: the course counts it satisfied, and a cmi5 unit has its `waived`
     * statement.
     *
     * @param string $by   the stable identifier of the teacher who waives it
     * @param string $note why, as the teacher writes it
     *
     * @throws UnknownRecord when the request is not known
     * @throws InvalidCourse when the request is decided already, the teacher is not an identifier or the note is too long
     */
    public function waive(RetryRequestId $id, string $by, string $note = ''): RetryRequest
    {
        $request = $this->decide($id, RetryDecision::Waived, $by, $note);
        $this->enrollments->waive($this->enrollments->get($request->enrollment), $this->courses->activity($request->activity));
        $this->events?->dispatch(new RetryWaived($request->id->value, $request->enrollment->value, $request->activity->value, $by));

        return $request;
    }

    /**
     * @return list<RetryRequest> the oldest first
     */
    public function waiting(int $limit = 50): array
    {
        return $this->requests->waiting(max(1, min($limit, 200)));
    }

    /**
     * The requests waiting for a decision, the oldest first, each with its enrollment, course, activity and the
     * learner's progress; a request whose enrollment or activity is gone is left out.
     *
     * @return list<RetryReview>
     */
    public function review(int $limit = 50): array
    {
        $reviews = [];

        foreach ($this->waiting($limit) as $request) {
            try {
                $enrollment = $this->enrollments->get($request->enrollment);
                $activity = $this->courses->activity($request->activity);
                $course = $this->courses->get($activity->course);
            } catch (UnknownRecord) {
                continue;
            }

            $reviews[] = new RetryReview($request, $enrollment, $course, $activity, $this->enrollments->progresses($enrollment)[$activity->id->value] ?? null);
        }

        return $reviews;
    }

    private function decide(RetryRequestId $id, RetryDecision $decision, string $by, string $note): RetryRequest
    {
        Identifiers::check('A teacher', $by, 190);
        $request = $this->requests->find($id) ?? throw UnknownRecord::of('request', $id->value);

        if (!$request->isPending()) {
            throw InvalidCourse::because(\sprintf('The request "%s" is decided already.', $id->value));
        }

        $request->decide($decision, $by, $this->clock->now(), $note);
        $this->requests->save($request);

        return $request;
    }
}
