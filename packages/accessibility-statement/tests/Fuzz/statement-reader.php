<?php

declare(strict_types=1);

/*
 * The statement reader on inputs nobody wrote: whatever it reads, it returns a statement that writes back to the data
 * it reads again, or refuses the input with an InvalidStatement, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\StatementReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $statement = StatementReader::fromJson($input);
    } catch (InvalidStatement) {
        // A statement refused whole is an answer.
        return;
    }

    if (StatementReader::fromArray($statement->toArray())->toArray() !== $statement->toArray()) {
        throw new LogicException('A statement read does not write back to itself.');
    }
});
$config->setMaxLen(4096);
