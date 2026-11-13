<?php

declare(strict_types=1);

/*
 * The definition reader on JSON nobody wrote: whatever it reads, it gives a definition or refuses with
 * InvalidDefinition, never throws anything else nor warns; a definition it gives writes JSON that reads back to the
 * same JSON.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Automations\Definition\DefinitionReader;
use Phil\Automations\Definition\DefinitionWriter;
use Phil\Automations\Exception\InvalidDefinition;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new DefinitionReader();
$writer = new DefinitionWriter();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $writer): void {
    try {
        $definition = $reader->read($input);
    } catch (InvalidDefinition) {
        return;
    }

    $json = $writer->write($definition);

    if ($writer->write($reader->read($json)) !== $json) {
        throw new LogicException('A written definition does not read back to itself.');
    }

    $definition->allSteps();
});
$config->setMaxLen(8192);
