<?php

declare(strict_types=1);

/*
 * The HTML checker on inputs nobody wrote: whatever it reads, it returns a report, or refuses the content with one of
 * its own exceptions, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Exception\AccessibilityCheckException;
use Phil\AccessibilityCheck\Policy;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$checker = new AccessibilityChecker(new Policy(maxDepth: 64, maxElements: 2_000)->withColourTokens(['accent' => '#b00020']));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($checker): void {
    try {
        json_encode($checker->checkHtml($input), \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (AccessibilityCheckException) {
        // A content refused whole is an answer.
    }
});
$config->setMaxLen(4096);
