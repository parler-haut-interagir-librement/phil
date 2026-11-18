<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;

/**
 * What a learner does on the pages: launch an activity open to them, and mark done one whose type lets them.
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
