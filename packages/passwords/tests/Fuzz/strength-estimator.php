<?php

declare(strict_types=1);

/*
 * The strength estimator on inputs nobody wrote, valid UTF-8 or not: whatever it reads, it returns a strength and
 * never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Passwords\Strength\StrengthEstimator;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$estimator = new StrengthEstimator(referenceYear: 2026);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($estimator): void {
    $estimator->estimate($input, [$input]);
});
$config->setMaxLen(256);
