<?php

declare(strict_types=1);

/*
 * Imports libphonenumber's metadata into data/:
 *
 *     php bin/import-metadata.php path/to/PhoneNumberMetadata.xml
 *
 * The file is resources/PhoneNumberMetadata.xml of https://github.com/google/libphonenumber, at a release tag;
 * the tag goes in data/VERSION.
 */

use Phil\Phone\Metadata\MetadataImporter;

// The library's own vendor/, or the monorepo's while it is incubated there.
require is_file(__DIR__.'/../vendor/autoload.php') ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';

$source = $argv[1] ?? null;

if (null === $source || !is_file($source)) {
    fwrite(\STDERR, "Usage: php bin/import-metadata.php path/to/PhoneNumberMetadata.xml\n");

    exit(1);
}

// The importer reads every territory before it writes, and removes the plans dropped only once the new ones are
// in place: a failed import leaves data/ as it was.
printf("%d territories imported.\n", new MetadataImporter()->import((string) file_get_contents($source), __DIR__.'/../data'));
