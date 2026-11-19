<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\ActivityType;

use Override;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\TimeLimit;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackagePath;

/**
 * The content of an imported SCORM item, framed in the activity page from the content origin. With the run time, the
 * content finds the run-time API of its version and reports its own progress through a session of an attempt; without
 * it, the learner marks it done. The `path` setting is the launch file inside the course's directory, `query` the
 * parameters of the item, `scorm_version` the version; the mastery score, the scaled passing score, the completion
 * threshold, the launch data, the time allowed and its action are handed to the content; `time_limit` is the
 * activity's own limit, in seconds, which the platform enforces; the SCORM type of the resource, the prerequisites and
 * the sequencing are kept for information. With the run time, an ended attempt opens again for review.
 */
final readonly class ScormActivity implements ActivityType
{
    public const string NAME = 'scorm';

    public function __construct(
        private ContentUrls $urls,
        private ?ScormRuntime $runtime = null,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function settings(array $settings): array
    {
        $path = $settings['path'] ?? null;
        $query = $settings['query'] ?? null;

        if (!\is_string($path) || null !== $query && !\is_string($query)) {
            throw InvalidCourse::because('A SCORM activity names the "path" of its launch file, and its "query" as text.');
        }

        $limit = $settings[TimeLimit::SETTING] ?? null;

        if (null !== $limit && (!\is_int($limit) || $limit < 1 || $limit > TimeLimit::MAX_SECONDS)) {
            throw InvalidCourse::because(\sprintf('The "time_limit" of a SCORM activity is a number of seconds, from 1 to %d.', TimeLimit::MAX_SECONDS));
        }

        try {
            PackagePath::check($path);
        } catch (PackageRefused $packageRefused) {
            throw InvalidCourse::because($packageRefused->getMessage());
        }

        return $settings;
    }

    #[Override]
    public function completesManually(): bool
    {
        return !$this->runtime instanceof ScormRuntime;
    }

    #[Override]
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        $url = $this->url($activity);

        return $this->runtime instanceof ScormRuntime ? Launch::scorm($url, $this->runtime->launch($activity, $enrollment)) : Launch::embedded($url);
    }

    /**
     * The learner's last ended attempt, framed for review: nothing the content writes is kept.
     *
     * @throws ActivityUnavailable when the run time is off, or the learner has no ended attempt
     */
    public function review(Activity $activity, Enrollment $enrollment): Launch
    {
        if (!$this->runtime instanceof ScormRuntime) {
            throw ActivityUnavailable::because('An attempt opens for review with the SCORM run time only.');
        }

        return Launch::scorm($this->url($activity), $this->runtime->review($activity, $enrollment));
    }

    /**
     * The activities of the enrollment with an ended attempt to review, by identifier; none without the run time.
     *
     * @return list<string>
     */
    public function reviewable(Enrollment $enrollment): array
    {
        return $this->runtime?->reviewable($enrollment) ?? [];
    }

    private function url(Activity $activity): string
    {
        $path = $activity->stringSetting('path');

        if (null === $path) {
            throw ActivityUnavailable::because(\sprintf('The SCORM activity "%s" names no launch file.', $activity->id->value));
        }

        return $this->urls->url($activity->course, $path, $activity->stringSetting('query'));
    }
}
