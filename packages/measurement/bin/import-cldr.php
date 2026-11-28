<?php

declare(strict_types=1);

namespace Phil\Measurement;

/*
 * Imports the CLDR unit preferences and unit patterns into data/cldr/:
 *
 *     php bin/import-cldr.php path/to/cldr-json 49.0.0 en fr fr-CA de ...
 *
 * The path is a checkout of https://github.com/unicode-org/cldr-json at a release tag, given as the second argument
 * and written to data/cldr/VERSION; the locales follow. The preferences are read from
 * cldr-json/cldr-core/supplemental/unitPreferenceData.json, the patterns of each locale from
 * cldr-json/cldr-units-full/main/<locale>/units.json. The licence of the data is copied to data/cldr/LICENSE.
 *
 * The script lives in the library's root namespace, which may call its internal importer.
 */

use Generator;
use Phil\Measurement\Cldr\CldrImporter;

// The library's own vendor/, or that of the repository that holds it.
require is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';

$arguments = $argv ?? [];
$source = rtrim($arguments[1] ?? '', '/');
$version = $arguments[2] ?? '';
$locales = \array_slice($arguments, 3);
$preferences = $source.'/cldr-json/cldr-core/supplemental/unitPreferenceData.json';

if ('' === $version || [] === $locales || !is_file($preferences)) {
    fwrite(\STDERR, "Usage: php bin/import-cldr.php path/to/cldr-json <version> <locale>...\n");

    exit(1);
}

$units = static function () use ($source, $locales): Generator {
    foreach ($locales as $locale) {
        yield $locale => (string) file_get_contents($source.'/cldr-json/cldr-units-full/main/'.$locale.'/units.json');
    }
};

$directory = __DIR__.'/../data/cldr';
$count = new CldrImporter()->import((string) file_get_contents($preferences), $units(), $version, $directory);
copy($source.'/LICENSE', $directory.'/LICENSE');
printf("%d locales imported.\n", $count);
