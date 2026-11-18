<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Application\ActivityType\LinkActivity;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Scorm\ScormItem;
use Phil\ElearningPackages\Scorm\ScormManifest;
use Phil\ElearningPackages\Scorm\ScormOrganization;
use Phil\ElearningPackages\Warnings;

/**
 * Turns a SCORM manifest into a course: the default organization becomes the course; each item of its first level
 * that has children becomes a module, whose leaves become its activities, the titles of the items between them
 * prefixed; the leaves of the first level gather in a module named after the organization. A leaf whose resource is in
 * the package becomes a SCORM activity, one whose resource is an absolute URL a link. Hidden items and items that
 * launch nothing are left out, and the report says so, after the notes of the reading of the package. A SCORM
 * activity is satisfied once passed when its item has a mastery score (SCORM 1.2) or a scaled passing score (SCORM
 * 2004), once completed or passed otherwise.
 */
final readonly class ScormPlanner
{
    private const int SEQUENCING_BYTES = 16_384;

    /**
     * @param list<string> $notes the notes of the reading of the package, said first
     */
    public function plan(ScormManifest $manifest, array $notes = []): ImportPlan
    {
        $organization = $manifest->organization();
        $warnings = new Warnings();

        foreach ($notes as $note) {
            $warnings->add($note);
        }

        if (\count($manifest->organizations) > 1) {
            $warnings->add(\sprintf('The manifest has %d organizations: the import reads "%s" and leaves the others out.', \count($manifest->organizations), $organization->identifier));
        }

        $modules = $this->modules($manifest, $organization, $warnings);

        if (null !== $organization->sequencing || null !== $manifest->sequencingCollection) {
            $warnings->add('The sequencing and navigation rules of SCORM 2004 are kept for information: the course does not enforce them.');
        }

        $warnings->add('A SCORM package carries no learner results: results exported from the previous platform are imported with "phil:courses:import-results".');

        return new ImportPlan(
            ImportPlan::title($organization->title, $manifest->identifier),
            '',
            $manifest->format,
            $manifest->identifier,
            $modules,
            $warnings->all(),
        );
    }

    /**
     * @return list<PlannedModule>
     */
    private function modules(ScormManifest $manifest, ScormOrganization $organization, Warnings $warnings): array
    {
        $modules = [];
        $loose = null;

        foreach ($organization->items as $item) {
            if (!$item->visible) {
                $warnings->add(\sprintf('The hidden item "%s" is left out.', $item->identifier));

                continue;
            }

            if (!$item->isLeaf()) {
                $activities = $this->leaves($manifest, $item->children, [], $warnings);

                if ([] !== $activities) {
                    $modules[] = new PlannedModule(ImportPlan::title($item->title, $item->identifier), $activities);
                }

                continue;
            }

            $activity = $this->activity($manifest, $item, [], $warnings);

            if (!$activity instanceof PlannedActivity) {
                continue;
            }

            if (null === $loose) {
                $loose = \count($modules);
                $modules[] = new PlannedModule(ImportPlan::title($organization->title, $organization->identifier), []);
            }

            $modules[$loose] = new PlannedModule($modules[$loose]->title, [...$modules[$loose]->activities, $activity]);
        }

        return array_values($modules);
    }

    /**
     * @param list<ScormItem> $items
     * @param list<string>    $path  the titles of the items between the module and these
     *
     * @return list<PlannedActivity>
     */
    private function leaves(ScormManifest $manifest, array $items, array $path, Warnings $warnings): array
    {
        $activities = [];

        foreach ($items as $item) {
            if (!$item->visible) {
                $warnings->add(\sprintf('The hidden item "%s" is left out.', $item->identifier));

                continue;
            }

            if (!$item->isLeaf()) {
                $activities = [...$activities, ...$this->leaves($manifest, $item->children, [...$path, $item->title], $warnings)];

                continue;
            }

            $activity = $this->activity($manifest, $item, $path, $warnings);

            if ($activity instanceof PlannedActivity) {
                $activities[] = $activity;
            }
        }

        return $activities;
    }

    /**
     * @param list<string> $path
     */
    private function activity(ScormManifest $manifest, ScormItem $item, array $path, Warnings $warnings): ?PlannedActivity
    {
        $resource = null === $item->resource ? null : ($manifest->resources[$item->resource] ?? null);

        if (null === $resource?->href) {
            $warnings->add(\sprintf('The item "%s" launches no resource with a file: it is left out.', $item->identifier));

            return null;
        }

        $title = ImportPlan::title(implode(' / ', [...$path, $item->title]), $item->identifier);

        if (PackagePath::isWebAddress($resource->href)) {
            return new PlannedActivity($title, LinkActivity::NAME, ['url' => $resource->href], CompletionRequirement::Completed, $item->identifier);
        }

        [$file, $query] = $this->split($resource->href, $item->parameters);

        if (null !== $item->prerequisites) {
            $warnings->add('The prerequisites of SCORM 1.2 are kept for information: impose the order of the course to enforce one.');
        }

        if (null !== $item->sequencing) {
            $warnings->add('The sequencing and navigation rules of SCORM 2004 are kept for information: the course does not enforce them.');
        }

        $passing = PackageFormat::Scorm12 === $manifest->format ? $item->masteryScore : $item->scaledPassingScore;

        return new PlannedActivity($title, ScormActivity::NAME, array_filter([
            'path' => $file,
            'query' => $query,
            'scorm_type' => $resource->scormType,
            'scorm_version' => $manifest->format->value,
            'mastery_score' => $item->masteryScore,
            'scaled_passing_score' => $item->scaledPassingScore,
            'completion_threshold' => $item->completionThreshold,
            'launch_data' => $item->dataFromLms,
            'max_time_allowed' => $item->maxTimeAllowed,
            'time_limit_action' => $item->timeLimitAction,
            'prerequisites' => null === $item->prerequisites ? null : mb_substr($item->prerequisites, 0, 1000),
            'sequencing' => null !== $item->sequencing && \strlen($item->sequencing) <= self::SEQUENCING_BYTES ? $item->sequencing : null,
        ], static fn (float|string|null $value): bool => null !== $value), null === $passing ? CompletionRequirement::CompletedOrPassed : CompletionRequirement::Passed, $item->identifier);
    }

    /**
     * The launch file, and what follows it: the query of the resource's reference, then the item's parameters, which
     * IMS Content Packaging appends to it.
     *
     * @return array{0: string, 1: ?string}
     */
    private function split(string $href, ?string $parameters): array
    {
        $position = strcspn($href, '?#');
        $file = substr($href, 0, $position);
        $query = substr($href, $position);
        $parameters = (string) $parameters;

        if ('' !== $parameters) {
            $query = str_contains($query, '?') && str_starts_with($parameters, '?') ? $query.'&'.substr($parameters, 1) : $query.$parameters;
        }

        return [$file, '' === $query ? null : $query];
    }
}
