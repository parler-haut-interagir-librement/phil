<?php

declare(strict_types=1);

/*
 * Catalogue definitions nobody wrote, decoded from JSON: whatever they hold, the loader gives a catalogue or throws
 * InvalidCatalogue, never another error.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\CatalogueLoader;
use Phil\ContentWarnings\Exception\InvalidCatalogue;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$loader = new CatalogueLoader();
$base = BaseCatalogue::load();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($loader, $base): void {
    $definition = json_decode($input, true, 16);

    if (!is_array($definition)) {
        return;
    }

    try {
        $loader->apply($definition, $base);
    } catch (InvalidCatalogue) {
        // A refusal is the expected answer to a malformed definition.
    }
});
$config->setMaxLen(4096);
