<?php

declare(strict_types=1);

/*
 * The reader of `tincan.xml` on documents nobody wrote: each is read into a course whose activities have unique IRIs
 * and whose course activity is launchable, its launch inside the package or an absolute address, or refused as a
 * package; nothing else is thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
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

    foreach ($course->launchable() as $activity) {
        $launch = (string) $activity->launch;

        if (!PackagePath::isWebAddress($launch)) {
            PackagePath::check(substr($launch, 0, strcspn($launch, '?#')));
        }
    }
});
$config->setMaxLen(4096);
