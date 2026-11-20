<?php

declare(strict_types=1);

/*
 * The parser of the prerequisites of SCORM 1.2 on expressions nobody wrote: each is read into prerequisites that
 * answer for any statuses and name only identifiers of the expression, or refused as a package; nothing else is thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $prerequisites = Prerequisites::parse($input);
    } catch (PackageRefused) {
        return;
    }

    foreach ($prerequisites->identifiers() as $identifier) {
        if (!str_contains($input, $identifier)) {
            throw new LogicException('An identifier the expression does not hold is named.');
        }
    }

    foreach (Prerequisites::STATUSES as $status) {
        $prerequisites->isMet(static fn (string $identifier): string => $status);
    }
});
$config->setMaxLen(512);
