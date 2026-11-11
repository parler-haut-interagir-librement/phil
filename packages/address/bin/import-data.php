<?php

declare(strict_types=1);

/*
 * Imports commerceguys/addressing's address formats and subdivisions into data/:
 *
 *     php bin/import-data.php path/to/addressing
 *
 * The path is a checkout of https://github.com/commerceguys/addressing at a release tag, which goes in data/VERSION.
 * Its formats are read from its own repository class, which this script loads from that checkout only. data/formats/
 * and data/subdivisions/ are replaced once every file is written; data refused halfway leave them as they were.
 */

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use Phil\Address\Data\DataImporter;

// The library's own vendor/, or that of the repository that holds it.
require is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';

$source = rtrim($argv[1] ?? '', '/');

if ('' === $source || !is_dir($source.'/resources/subdivision')) {
    fwrite(\STDERR, "Usage: php bin/import-data.php path/to/addressing\n");

    exit(1);
}

spl_autoload_register(static function (string $class) use ($source): void {
    $prefix = 'CommerceGuys\\Addressing\\';

    if (str_starts_with($class, $prefix)) {
        require $source.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
}, prepend: true);

$repository = new AddressFormatRepository();
$read = static function (string $method) use ($repository): array {
    /** @var array<string, mixed> $definitions commerceguys/addressing's, by its own documentation */
    $definitions = new ReflectionMethod($repository, $method)->invoke($repository);

    return $definitions;
};
$subdivisions = static function () use ($source): Generator {
    $files = glob($source.'/resources/subdivision/*.json');

    foreach (false === $files ? [] : $files as $file) {
        yield basename($file, '.json') => (string) file_get_contents($file);
    }
};

$directory = __DIR__.'/../data';

/** @var array<string, array<string, mixed>> $definitions */
$definitions = $read('getDefinitions');
printf("%d files imported.\n", new DataImporter()->import($definitions, $read('getGenericDefinition'), $subdivisions(), $directory));
