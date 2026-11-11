<?php

declare(strict_types=1);

/*
 * The reader of the JSON-LD of a page on inputs nobody wrote: whatever it reads, as a page or as one JSON-LD
 * document, it returns a graph that writes as JSON, or refuses the input with its own exception, and never throws
 * anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\JsonLd;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Validation\Validator;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$validator = new Validator();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($validator): void {
    $extraction = JsonLdReader::fromHtml($input);
    $validator->validate($extraction->graph);
    JsonLd::script($extraction->graph);

    try {
        $graph = JsonLdReader::fromJson($input);
        $validator->validate($graph);
        JsonLd::encode($graph);
    } catch (InvalidStructuredData) {
        // A document refused whole is an answer.
    }
});
$config->setMaxLen(4096);
