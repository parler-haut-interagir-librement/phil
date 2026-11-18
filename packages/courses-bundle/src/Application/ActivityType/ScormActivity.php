<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\ActivityType;

use Override;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
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
 * threshold, the launch data, the time allowed and its action are handed to the content; the SCORM type of the
 * resource, the prerequisites and the sequencing are kept for information.
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
        $path = $activity->stringSetting('path');

        if (null === $path) {
            throw ActivityUnavailable::because(\sprintf('The SCORM activity "%s" names no launch file.', $activity->id->value));
        }

        $url = $this->urls->url($activity->course, $path, $activity->stringSetting('query'));

        return $this->runtime instanceof ScormRuntime ? Launch::scorm($url, $this->runtime->launch($activity, $enrollment)) : Launch::embedded($url);
    }
}
