<?php

declare(strict_types=1);

/*
 * The decoder and the renderers of UJO on inputs nobody wrote: a document either decodes and renders, or fails with an
 * exception of the library; the same at the publication level of validation.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;
use Phil\Ujo\Validation;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$ujo = new Ujo();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($ujo): void {
    try {
        $document = $ujo->decode($input);
        $ujo->toHtml($document);
        $ujo->toText($document);
        $ujo->normalize($document, Validation::Publication);
    } catch (UjoException) {
        // The failure the library promises for a document it cannot read.
    }
});
$config->setMaxLen(8192);
