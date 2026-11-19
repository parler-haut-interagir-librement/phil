<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Courses;

use Override;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Domain\Exception\ClassroomException;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Phil\VirtualClassroomBundle\Infrastructure\Http\JoinController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A live class as an activity of a course, of type `virtual_classroom`: its room is scheduled at its first launch,
 * owned by the activity, and every learner who launches it is sent to the join link of the bundle, in a window of its
 * own. The learner does not mark it done: the attendance does, once the room has ended ({@see CoursesCompletion}).
 *
 * Its settings, all optional: `provider`, a server of the configuration; `record`; `duration`, in minutes;
 * `threshold`, the share of the session attended that completes it, in percents.
 */
final readonly class VirtualClassroomActivity implements ActivityType
{
    public const string NAME = 'virtual_classroom';

    public const string OWNER_PREFIX = 'phil_courses:';

    public function __construct(
        private Classrooms $classrooms,
        private Providers $providers,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public static function owner(string $activity): string
    {
        return self::OWNER_PREFIX.$activity;
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function settings(array $settings): array
    {
        $checked = [];
        $provider = $settings['provider'] ?? null;

        if (null !== $provider) {
            if (!\is_string($provider) || !$this->providers->has($provider)) {
                throw InvalidCourse::because('The "provider" of a live class is a server of the configuration.');
            }

            $checked['provider'] = $provider;
        }

        $record = $settings['record'] ?? false;

        if (!\is_bool($record)) {
            throw InvalidCourse::because('Whether a live class is recorded, its "record", is true or false.');
        }

        $checked['record'] = $record;
        $duration = $settings['duration'] ?? null;

        if (null !== $duration) {
            if (!\is_int($duration) || $duration < 1 || $duration > 1440) {
                throw InvalidCourse::because('The "duration" of a live class is 1 to 1440 minutes.');
            }

            $checked['duration'] = $duration;
        }

        $threshold = $settings['threshold'] ?? null;

        if (null !== $threshold) {
            if (!\is_int($threshold) || $threshold < 1 || $threshold > 100) {
                throw InvalidCourse::because('The "threshold" of a live class is 1 to 100 percent of the session.');
            }

            $checked['threshold'] = $threshold;
        }

        return $checked;
    }

    #[Override]
    public function completesManually(): bool
    {
        return false;
    }

    #[Override]
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        $owner = self::owner($activity->id->value);

        try {
            $room = $this->classrooms->ofOwner($owner) ?? $this->classrooms->schedule(
                $this->title($activity->title),
                $activity->stringSetting('provider'),
                true === $activity->setting('record'),
                \is_int($duration = $activity->setting('duration')) ? $duration : null,
                $owner,
            );
        } catch (ClassroomException $classroomException) {
            throw ActivityUnavailable::because(\sprintf('The live class "%s" cannot be scheduled: %s', $activity->id->value, $classroomException->getMessage()));
        }

        if ($room->hasEnded()) {
            throw ActivityUnavailable::because(\sprintf('The live class "%s" has ended.', $activity->id->value));
        }

        return Launch::newWindow($this->urls->generate(JoinController::ROUTE, ['room' => $room->id->value], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * The title of the activity, which is trimmed already, cut to the 64 characters of a room, or a title of its own
     * when too short.
     */
    private function title(string $title): string
    {
        $title = mb_substr($title, 0, 64);

        return mb_strlen($title) < 2 ? 'Live class' : $title;
    }

    /**
     * Whether a room belongs to an activity of a course.
     */
    public static function owns(Room $room): bool
    {
        return null !== $room->owner && str_starts_with($room->owner, self::OWNER_PREFIX);
    }
}
