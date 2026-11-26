<?php

declare(strict_types=1);

/*
 * The reader of `tincan.xml` on documents nobody wrote: each is read into a course whose activities have unique IRIs
 * and whose course activity is launchable, its launch inside the package or an absolute address, each question
 * holding only the lists of components its interaction type uses, each identifier once and within its bounds, or
 * refused as a package; nothing else is thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Xapi\ComponentList;
use Phil\ElearningPackages\Xapi\InteractionComponent;
use Phil\ElearningPackages\Xapi\XapiActivity;
use Phil\ElearningPackages\Xapi\XapiCourseReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new XapiCourseReader(new PackageLimits(manifestBytes: 65_536, items: 200), 'fr');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $course = $reader->read($input);
    } catch (PackageRefused) {
        return;
    }

    $ids = array_map(static fn (XapiActivity $activity): string => $activity->id, $course->activities);

    if (!$course->course->isLaunchable() || count($ids) !== count(array_unique($ids))) {
        throw new LogicException('A course is read without a launch, or with an IRI used twice.');
    }

    foreach ($course->activities as $activity) {
        $used = array_map(static fn (ComponentList $list): string => $list->value, ComponentList::of($activity->interactionType));

        if ([] !== array_diff(array_keys($activity->components), $used) || count($activity->correctResponses) > XapiCourseReader::MAX_RESPONSES || (null === $activity->interactionType && [] !== $activity->correctResponses)) {
            throw new LogicException('A question keeps a list of components or correct responses it should not.');
        }

        foreach ($activity->components as $components) {
            $componentIds = array_map(static fn (InteractionComponent $component): string => $component->id, $components);

            if (count($componentIds) > XapiCourseReader::MAX_COMPONENTS || count($componentIds) !== count(array_unique($componentIds)) || in_array('', $componentIds, true)) {
                throw new LogicException('A list of components is read past its bound, empty identifiers or an identifier twice.');
            }
        }
    }

    foreach ($course->launchable() as $activity) {
        $launch = (string) $activity->launch;

        if (!PackagePath::isWebAddress($launch)) {
            PackagePath::check(substr($launch, 0, strcspn($launch, '?#')));
        }
    }
});
$config->setMaxLen(4096);
