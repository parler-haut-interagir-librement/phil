<?php

declare(strict_types=1);

/*
 * The ISO 8601 durations nobody wrote: read into words, or refused with the library's own exception, never anything
 * else thrown nor a warning.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\LegalNotices\DurationWords;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        DurationWords::duration($input, 'fr');
        DurationWords::duration($input, 'en');
    } catch (InvalidLegalNotice) {
        // A duration refused is an answer.
    }
});
$config->setMaxLen(64);
