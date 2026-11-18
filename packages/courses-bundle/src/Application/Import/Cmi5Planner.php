<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Application\ActivityType\Cmi5Activity;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\ElearningPackages\Cmi5\Cmi5Au;
use Phil\ElearningPackages\Cmi5\Cmi5Block;
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Cmi5\MoveOn;
use Phil\ElearningPackages\Exception\PackageRefused as UnreadablePackage;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Warnings;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Turns a cmi5 course structure into a course. Its blocks are kept as a tree — nested blocks, their order among the
 * children of their parent and their identifiers — and each assignable unit names the innermost block that holds it.
 * For the outline, each block of the first level becomes a module, whose units become its activities, the titles of
 * the nested blocks prefixed; the units of the first level gather in a module named after the course. Each assignable
 * unit becomes a cmi5 activity that keeps its IRI, its `moveOn` as its requirement, its mastery score and launch
 * method; a unit whose `moveOn` is `NotApplicable` is not required, as cmi5 counts it satisfied. Its URL is a file of
 * the package, served from the content origin, or an absolute address. The notes of the reading of the package are
 * said first.
 */
final readonly class Cmi5Planner
{
    /**
     * @param bool         $packaged whether the structure comes with its package, or alone, its units on absolute URLs
     * @param list<string> $notes    the notes of the reading of the package, said first
     *
     * @throws PackageRefused when a unit's URL is neither a file of the package nor an absolute address
     */
    public function plan(Cmi5Course $course, bool $packaged = true, array $notes = []): ImportPlan
    {
        $warnings = new Warnings();

        foreach ($notes as $note) {
            $warnings->add($note);
        }

        $modules = [];
        $blocks = [];
        $loose = null;

        foreach ($course->children as $position => $child) {
            if ($child instanceof Cmi5Block) {
                $block = $this->block($child, $position, null, $blocks);
                $activities = $this->units($child->children, [], $block, $packaged, $warnings, $blocks);

                if ([] !== $activities) {
                    $modules[] = new PlannedModule(ImportPlan::title($child->title, $child->id), $activities);
                }

                continue;
            }

            if (null === $loose) {
                $loose = \count($modules);
                $modules[] = new PlannedModule(ImportPlan::title($course->title, $course->id), []);
            }

            $modules[$loose] = new PlannedModule($modules[$loose]->title, [...$modules[$loose]->activities, $this->activity($child, [], null, $packaged, $warnings)]);
        }

        if ($course->hasObjectives) {
            $warnings->add('The objectives of the course structure are left out.');
        }

        return new ImportPlan(ImportPlan::title($course->title, $course->id), $course->description, PackageFormat::Cmi5, $course->id, array_values($modules), $warnings->all(), $blocks);
    }

    /**
     * Adds the block to the plan and gives back its index.
     *
     * @param int<0, max>        $position
     * @param int<0, max>|null   $parent
     * @param list<PlannedBlock> $blocks
     *
     * @return int<0, max>
     */
    private function block(Cmi5Block $block, int $position, ?int $parent, array &$blocks): int
    {
        $blocks[] = new PlannedBlock(ImportPlan::title($block->title, $block->id), $block->id, $position, $parent);

        return \count($blocks) - 1;
    }

    /**
     * @param list<Cmi5Block|Cmi5Au> $children
     * @param list<string>           $path
     * @param int<0, max>            $parent
     * @param list<PlannedBlock>     $blocks
     *
     * @return list<PlannedActivity>
     */
    private function units(array $children, array $path, int $parent, bool $packaged, Warnings $warnings, array &$blocks): array
    {
        $activities = [];

        foreach ($children as $position => $child) {
            $activities = $child instanceof Cmi5Block
                ? [...$activities, ...$this->units($child->children, [...$path, $child->title], $this->block($child, $position, $parent, $blocks), $packaged, $warnings, $blocks)]
                : [...$activities, $this->activity($child, $path, $parent, $packaged, $warnings)];
        }

        return $activities;
    }

    /**
     * @param list<string>     $path
     * @param int<0, max>|null $block
     */
    private function activity(Cmi5Au $au, array $path, ?int $block, bool $packaged, Warnings $warnings): PlannedActivity
    {
        if (null !== $au->entitlementKey) {
            $warnings->add('The entitlement keys of the assignable units are kept and handed to them, not checked.');
        }

        return new PlannedActivity(
            ImportPlan::title(implode(' / ', [...$path, $au->title]), $au->id),
            Cmi5Activity::NAME,
            array_filter([
                ...$this->location($au, $packaged),
                'au_id' => $au->id,
                'launch_method' => $au->launchMethod->value,
                'mastery_score' => $au->masteryScore,
                'launch_parameters' => $au->launchParameters,
                'entitlement_key' => $au->entitlementKey,
                'activity_type' => $au->activityType,
            ], static fn (float|string|null $value): bool => null !== $value),
            CompletionRequirement::from($au->moveOn->value),
            $au->id,
            MoveOn::NotApplicable !== $au->moveOn,
            $block,
        );
    }

    /**
     * @return array{url?: string, path?: string, query?: ?string}
     */
    private function location(Cmi5Au $au, bool $packaged): array
    {
        if (PackagePath::isWebAddress($au->url)) {
            try {
                return ['url' => Url::fromString($au->url)->toString()];
            } catch (InvalidUrl $invalidUrl) {
                throw PackageRefused::because(\sprintf('The URL of the assignable unit "%s" is not an address: %s', $au->id, $invalidUrl->getMessage()));
            }
        }

        if (!$packaged) {
            throw PackageRefused::because(\sprintf('The assignable unit "%s" names a file, "%s", of a package that is not given: a course structure alone names absolute URLs.', $au->id, mb_substr($au->url, 0, 120)));
        }

        try {
            [$path, $query] = PackagePath::resolve([], $au->url);
        } catch (UnreadablePackage $unreadablePackage) {
            throw PackageRefused::from($unreadablePackage);
        }

        return ['path' => $path, 'query' => $query];
    }
}
