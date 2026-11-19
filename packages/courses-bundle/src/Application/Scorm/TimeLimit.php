<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Scorm;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\ScormVersion;

/**
 * The time a SCORM attempt is allowed: the time of the manifest — `max_time_allowed` and its `time_limit_action` —, the
 * activity's own limit — `time_limit`, in seconds —, or the shorter of both.
 *
 * The content is handed the shorter time and the action that goes with it: the manifest's, or an action that exits
 * when the activity's own limit is the shorter. The platform ends the attempt at the deadline, by its own clock: the
 * activity's own limit always, the time of the manifest when its action exits. A manifest that names no action
 * continues, as SCORM says.
 */
final readonly class TimeLimit
{
    /** The setting of the activity's own limit, in seconds. */
    public const string SETTING = 'time_limit';

    /** The longest limit, in seconds: 9999:59:59, the longest time SCORM 1.2 writes. */
    public const int MAX_SECONDS = 35_999_999;

    /** The action when the manifest names none. */
    public const string DEFAULT_ACTION = 'continue,no message';

    /**
     * @param int<1, max>  $allowed  the seconds handed to the content
     * @param ?int<1, max> $deadline the seconds after which the platform ends the attempt, null when it lets it run
     */
    private function __construct(
        public int $allowed,
        public string $action,
        public ?int $deadline,
    ) {
    }

    /**
     * The limit of the activity, or null when it has none.
     */
    public static function of(Activity $activity, ScormVersion $version): ?self
    {
        $manifest = self::seconds($version, $activity->stringSetting('max_time_allowed'));
        $action = $activity->stringSetting('time_limit_action') ?? self::DEFAULT_ACTION;
        $exits = str_starts_with($action, 'exit,');
        $own = $activity->setting(self::SETTING);
        $own = \is_int($own) && $own > 0 ? min($own, self::MAX_SECONDS) : null;

        if (null !== $own && (null === $manifest || $own <= $manifest)) {
            return new self($own, $exits ? $action : 'exit,message', $own);
        }

        if (null === $manifest) {
            return null;
        }

        return new self($manifest, $action, $exits ? $manifest : $own);
    }

    /**
     * The time allowed as the version writes it.
     */
    public function duration(ScormVersion $version): string
    {
        return Duration::ofCentiseconds($this->allowed * 100)->format($version);
    }

    /**
     * The seconds left before the deadline at that moment, below zero once it passed; null without a deadline.
     */
    public function remaining(ScormAttempt $attempt, DateTimeImmutable $at): ?int
    {
        return null === $this->deadline ? null : $this->deadline - $attempt->spent($at);
    }

    /**
     * @return ?int<1, max>
     */
    private static function seconds(ScormVersion $version, ?string $value): ?int
    {
        $duration = null === $value ? null : Duration::parse($version, $value);
        $seconds = $duration instanceof Duration ? intdiv($duration->centiseconds, 100) : null;

        return null !== $seconds && $seconds > 0 ? $seconds : null;
    }
}
