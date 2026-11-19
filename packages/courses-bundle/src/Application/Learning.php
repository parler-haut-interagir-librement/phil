<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;

/**
 * What a learner does on the pages: launch an activity open to them, mark done one whose type lets them, and review
 * an ended SCORM attempt of an activity they reached.
 */
final readonly class Learning
{
    public function __construct(
        private Enrollments $enrollments,
        private Availabilities $availabilities,
        private ActivityTypes $types,
    ) {
    }

    /**
     * @throws UnknownRecord          when the activity is not one of the course
     * @throws ActivityUnavailable    when the activity is not open to the learner, or cannot be launched
     * @throws RecordStoreUnavailable when the learning record store does not keep the statement of the launch
     */
    public function launch(Enrollment $enrollment, ActivityId $id): Launch
    {
        $status = $this->open($enrollment, $id);
        $launch = $this->types->get($status->activity->type)->launch($status->activity, $enrollment);
        $this->enrollments->start($enrollment, $status->activity);

        return $launch;
    }

    /**
     * Opens the learner's last ended attempt at a SCORM activity for review: the activity is released and reached —
     * open, satisfied, or out of tries —, and nothing the content writes is kept.
     *
     * @throws UnknownRecord       when the activity is not one of the course
     * @throws ActivityUnavailable when the activity is not reached, is not a SCORM activity, or has no ended attempt
     */
    public function review(Enrollment $enrollment, ActivityId $id): Launch
    {
        $status = $this->availabilities->of($enrollment, $id);

        if (\in_array($status->availability->status, [AvailabilityStatus::Scheduled, AvailabilityStatus::Locked], true)) {
            throw ActivityUnavailable::because(\sprintf('This activity is not open: it is %s.', $status->availability->status->value));
        }

        $type = $this->types->get($status->activity->type);

        if (!$type instanceof ScormActivity) {
            throw ActivityUnavailable::because('Only an attempt of a SCORM activity opens for review.');
        }

        return $type->review($status->activity, $enrollment);
    }

    /**
     * The activities of the enrollment with an ended SCORM attempt to review, by identifier.
     *
     * @return list<string>
     */
    public function reviewable(Enrollment $enrollment): array
    {
        $type = $this->types->has(ScormActivity::NAME) ? $this->types->get(ScormActivity::NAME) : null;

        return $type instanceof ScormActivity ? $type->reviewable($enrollment) : [];
    }

    /**
     * @throws UnknownRecord       when the activity is not one of the course
     * @throws ActivityUnavailable when the activity is not open, or only its content reports its completion
     */
    public function complete(Enrollment $enrollment, ActivityId $id): Progress
    {
        $status = $this->open($enrollment, $id);

        if (!$this->types->get($status->activity->type)->completesManually()) {
            throw ActivityUnavailable::because('This activity reports its own completion.');
        }

        return $this->enrollments->record($enrollment, $status->activity, Outcome::completed());
    }

    private function open(Enrollment $enrollment, ActivityId $id): ActivityStatus
    {
        $status = $this->availabilities->of($enrollment, $id);

        if (!$status->availability->canStart()) {
            throw ActivityUnavailable::because(\sprintf('This activity is not open: it is %s.', str_replace('_', ' ', $status->availability->status->value)));
        }

        return $status;
    }
}
