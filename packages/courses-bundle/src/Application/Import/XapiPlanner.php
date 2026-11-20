<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\ElearningPackages\Exception\PackageRefused as UnreadablePackage;
use Phil\ElearningPackages\PackagePath;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Turns an xAPI package into a course of one module, named after the course, whose activities are the package's
 * launchable activities in their order. Each becomes an xAPI activity that keeps its IRI and its mastery score; one
 * with a mastery score is satisfied once passed, the others once completed. Its launch is a file of the package,
 * served from the content origin, or an absolute address.
 */
final readonly class XapiPlanner
{
    /**
     * @throws PackageRefused when a launch is neither a file of the package nor an absolute address
     */
    public function plan(XapiPackage $package): ImportPlan
    {
        $activities = array_map(fn (XapiLaunchable $activity): PlannedActivity => new PlannedActivity(
            ImportPlan::title($activity->title, $activity->id),
            XapiActivity::NAME,
            array_filter([
                ...$this->location($activity),
                'activity_id' => $activity->id,
                'mastery_score' => $activity->masteryScore,
            ], static fn (float|string|null $value): bool => null !== $value),
            null === $activity->masteryScore ? CompletionRequirement::Completed : CompletionRequirement::Passed,
            $activity->id,
        ), $package->activities);
        $title = ImportPlan::title($package->title, $package->id);

        return new ImportPlan($title, $package->description, CourseOrigin::Xapi, $package->id, [] === $activities ? [] : [new PlannedModule($title, $activities)], $package->warnings);
    }

    /**
     * @return array{url?: string, path?: string, query?: ?string}
     */
    private function location(XapiLaunchable $activity): array
    {
        try {
            if (PackagePath::isWebAddress($activity->launch)) {
                return ['url' => Url::fromString($activity->launch)->toString()];
            }

            [$path, $query] = PackagePath::resolve([], $activity->launch);
        } catch (InvalidUrl|UnreadablePackage $exception) {
            throw PackageRefused::because(\sprintf('The launch of the xAPI activity "%s" is neither a file of the package nor an address: %s', mb_substr($activity->id, 0, 120), $exception->getMessage()));
        }

        return ['path' => $path, 'query' => $query];
    }
}
