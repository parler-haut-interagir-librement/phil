<?php

declare(strict_types=1);

/*
 * A template read back from JSON nobody wrote: whatever the array holds, `TemplateArray::fromArray()` returns a
 * template or refuses it with `InvalidDefinition`, never another error.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\TemplateArray;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 32, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        TemplateArray::fromArray($data);
    } catch (InvalidDefinition) {
        // The refusal the reader promises for an array no code of the library wrote.
    }
});
$config->setMaxLen(4096);
