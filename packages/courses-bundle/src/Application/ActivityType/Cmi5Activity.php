<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\ActivityType;

use Override;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Launcher;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\ElearningPackages\Cmi5\LaunchMethod;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackagePath;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * A cmi5 assignable unit, launched with the parameters of cmi5 and satisfied by its own statements only: the learner
 * cannot mark it done. Its settings: `au_id`, its IRI in the course structure; `url`, an absolute address, or `path`
 * and `query`, its launch file on the content origin; `launch_method`; `mastery_score`, from 0 to 1;
 * `launch_parameters` and `entitlement_key`, handed to the unit.
 */
final readonly class Cmi5Activity implements ActivityType
{
    public const string NAME = 'cmi5';

    public function __construct(
        private Cmi5Launcher $launcher,
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
        if (!\is_string($settings['au_id'] ?? null) || '' === $settings['au_id']) {
            throw InvalidCourse::because('A cmi5 activity names the "au_id" of its unit.');
        }

        if (null === LaunchMethod::tryFrom((string) ($settings['launch_method'] ?? LaunchMethod::AnyWindow->value))) {
            throw InvalidCourse::because('The "launch_method" of a cmi5 activity is AnyWindow or OwnWindow.');
        }

        $mastery = $settings['mastery_score'] ?? null;

        if (null !== $mastery && (!\is_float($mastery) && !\is_int($mastery) || $mastery < 0 || $mastery > 1)) {
            throw InvalidCourse::because('The "mastery_score" of a cmi5 activity is a number from 0 to 1.');
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
            throw InvalidCourse::because(\sprintf('The location of a cmi5 activity is wrong: %s', $exception->getMessage()));
        }

        throw InvalidCourse::because('A cmi5 activity names its "url", or the "path" of its launch file.');
    }
}
