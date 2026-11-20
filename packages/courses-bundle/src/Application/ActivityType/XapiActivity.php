<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\ActivityType;

use Override;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\Xapi\XapiLauncher;
use Phil\CoursesBundle\Application\Xapi\XapiRules;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\ElearningPackages\Cmi5\LaunchMethod;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackagePath;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * An activity of an xAPI package, launched with the launch parameters xAPI packages read and satisfied by its own
 * `completed`, `passed` and `failed` statements only: the learner cannot mark it done. Its settings: `activity_id`,
 * the IRI the package names its activity by; `url`, an absolute address, or `path` and `query`, its launch file on
 * the content origin; `launch_method`, `AnyWindow` or `OwnWindow`; `mastery_score`, from 0 to 1, the scaled score that
 * passes it.
 */
final readonly class XapiActivity implements ActivityType
{
    public const string NAME = 'xapi';

    public function __construct(
        private XapiLauncher $launcher,
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
        $id = $settings['activity_id'] ?? null;

        if (!XapiRules::isIri($id)) {
            throw InvalidCourse::because('An xAPI activity names the "activity_id" of its package, an IRI of 2048 bytes at most.');
        }

        if (null === LaunchMethod::tryFrom((string) ($settings['launch_method'] ?? LaunchMethod::AnyWindow->value))) {
            throw InvalidCourse::because('The "launch_method" of an xAPI activity is AnyWindow or OwnWindow.');
        }

        $mastery = $settings['mastery_score'] ?? null;

        if (null !== $mastery && (!\is_float($mastery) && !\is_int($mastery) || $mastery < 0 || $mastery > 1)) {
            throw InvalidCourse::because('The "mastery_score" of an xAPI activity is a number from 0 to 1.');
        }

        return [...$settings, ...$this->location($settings)];
    }

    #[Override]
    public function completesManually(): bool
    {
        return false;
    }

    #[Override]
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        return $this->launcher->launch($activity, $enrollment);
    }

    /**
     * @param array<string, bool|float|int|string|null> $settings
     *
     * @return array{url: string}|array{path: string}
     */
    private function location(array $settings): array
    {
        $url = $settings['url'] ?? null;
        $path = $settings['path'] ?? null;

        try {
            if (\is_string($url)) {
                return ['url' => Url::fromString($url)->toString()];
            }

            if (\is_string($path)) {
                return ['path' => PackagePath::check($path)];
            }
        } catch (InvalidUrl|PackageRefused $exception) {
            throw InvalidCourse::because(\sprintf('The location of an xAPI activity is wrong: %s', $exception->getMessage()));
        }

        throw InvalidCourse::because('An xAPI activity names its "url", or the "path" of its launch file.');
    }
}
