<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;

/**
 * A type of activity: the settings it reads, whether the learner reports its completion by hand, and where the
 * learner goes to take it. The bundle collects every service tagged `phil_courses.activity_type`; the name is what an
 * activity keeps.
 *
 * @phpstan-import-type Settings from Activity
 */
interface ActivityType
{
    public const string TAG = 'phil_courses.activity_type';

    /**
     * The name an activity of this type keeps: letters, digits, `.`, `_`, `:` or `-`.
     */
    public function name(): string;

    /**
     * The settings as the type keeps them, checked.
     *
     * @param Settings $settings
     *
     * @return Settings
     *
     * @throws InvalidCourse when a setting is missing or wrong
     */
    public function settings(array $settings): array;

    /**
     * Whether the learner marks the activity done themselves; false when only its content reports it, as a cmi5
     * assignable unit does through its statements.
     */
    public function completesManually(): bool;

    /**
     * Where the learner goes to take the activity.
     *
     * @throws ActivityUnavailable when the activity cannot be launched, its content not served for instance
     */
    public function launch(Activity $activity, Enrollment $enrollment): Launch;
}
