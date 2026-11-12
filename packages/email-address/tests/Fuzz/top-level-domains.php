<?php

declare(strict_types=1);

/*
 * The importer of the IANA list on values nobody wrote: whatever the file holds, the importer refuses it with
 * InvalidDomainList, or writes a list whose every domain TopLevelDomains::of() accepts again; never another error nor
 * a warning.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\EmailAddress\Data\TopLevelDomainsImporter;
use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\TopLevelDomains;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$directory = sys_get_temp_dir().'/phil-email-address-fuzz-'.getmypid();

if (!is_dir($directory) && !mkdir($directory) && !is_dir($directory)) {
    throw new RuntimeException('The fuzz directory cannot be created.');
}

$importer = new TopLevelDomainsImporter();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($importer, $directory): void {
    try {
        $count = $importer->import($input, $directory);
    } catch (InvalidDomainList) {
        return;
    }

    /** @var list<string> $labels */
    $labels = require $directory.'/'.TopLevelDomainsImporter::DATA_FILE;

    if (count($labels) !== $count || TopLevelDomains::of($labels)->count() !== $count) {
        throw new LogicException('An imported list does not read back as the domains written.');
    }
});
