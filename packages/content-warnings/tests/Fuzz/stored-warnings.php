<?php

declare(strict_types=1);

/*
 * Stored warnings and preferences nobody wrote, decoded from JSON: whatever they hold, reading them back gives a value
 * or throws UnreadableWarnings, never another error.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Exception\UnreadableWarnings;
use Phil\ContentWarnings\WarningPreferences;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $stored = json_decode($input, true, 8);

    if (!is_array($stored)) {
        return;
    }

    foreach ([ContentWarnings::fromArray(...), WarningPreferences::fromArray(...)] as $read) {
        try {
            $read($stored);
        } catch (UnreadableWarnings) {
            // A refusal is the expected answer to malformed data.
        }
    }
});
$config->setMaxLen(4096);
