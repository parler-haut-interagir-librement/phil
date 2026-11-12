<?php

declare(strict_types=1);

/*
 * The reader of stored disclosures, on JSON nobody wrote: whatever it reads, it returns a disclosure whose array reads
 * back to the same disclosure, or throws InvalidDisclosure, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CreationDisclosure\CreationDisclosure;
use Phil\CreationDisclosure\Exception\InvalidDisclosure;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $stored = json_decode($input, true, 4, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($stored)) {
        return;
    }

    try {
        $disclosure = CreationDisclosure::fromArray($stored);
    } catch (InvalidDisclosure) {
        return;
    }

    if (CreationDisclosure::fromArray($disclosure->toArray())->toArray() !== $disclosure->toArray()) {
        throw new LogicException('A disclosure read back is not the disclosure written.');
    }

    $disclosure->generativeAi();
});
$config->setMaxLen(512);
