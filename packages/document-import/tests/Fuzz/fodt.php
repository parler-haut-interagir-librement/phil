<?php

declare(strict_types=1);

/*
 * The flat ODT reader on bytes nobody wrote: it gives a document, or refuses the file with an exception of the library.
 * Nothing else is thrown, and nothing warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\DocumentImport\DocumentImporter;
use Phil\DocumentImport\Exception\DocumentImportException;
use Phil\DocumentImport\Format;
use Phil\DocumentImport\Limits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$importer = new DocumentImporter(limits: new Limits(entryBytes: 1024 * 1024, totalBytes: 4 * 1024 * 1024, depth: 64, objects: 10_000));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($importer): void {
    try {
        $importer->import($input, Format::Fodt);
    } catch (DocumentImportException) {
        // A file the reader refuses, with the exception of the library: the expected answer.
        return;
    }
});
$config->setMaxLen(65_536);
