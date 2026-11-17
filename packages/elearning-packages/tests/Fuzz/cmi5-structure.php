<?php

declare(strict_types=1);

/*
 * The reader of cmi5 course structures on documents nobody wrote: each is read into a course holding units of unique
 * identifiers and mastery scores from 0 to 1, or refused as a package; nothing else is thrown, and nothing warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Cmi5\Cmi5Au;
use Phil\ElearningPackages\Cmi5\Cmi5Block;
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new Cmi5CourseReader(new PackageLimits(manifestBytes: 65_536, depth: 8, items: 200));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $course = $reader->read($input);
    } catch (PackageRefused) {
        return;
    }

    $ids = [$course->id];
    $units = 0;
    $walk = static function (array $children) use (&$walk, &$ids, &$units): void {
        foreach ($children as $child) {
            $ids[] = $child->id;

            if ($child instanceof Cmi5Block) {
                $walk($child->children);
            } elseif ($child instanceof Cmi5Au) {
                ++$units;

                if (null !== $child->masteryScore && ($child->masteryScore < 0 || $child->masteryScore > 1)) {
                    throw new LogicException('A mastery score outside 0 and 1 is read.');
                }
            }
        }
    };
    $walk($course->children);

    if (0 === $units || count($ids) !== count(array_unique($ids))) {
        throw new LogicException('A course structure is read without units, or with an identifier used twice.');
    }
});
$config->setMaxLen(4096);
