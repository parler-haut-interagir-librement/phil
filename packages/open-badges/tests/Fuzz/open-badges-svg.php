<?php

declare(strict_types=1);

/*
 * The reader of a credential baked into an SVG image, on inputs nobody wrote: whatever it reads, it returns a
 * credential, nothing, or a refusal of the library, and never anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\OpenBadges\Baking\SvgBaker;
use Phil\OpenBadges\Exception\OpenBadgesException;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        SvgBaker::extract($input);
    } catch (OpenBadgesException) {
    }
});
$config->setMaxLen(4096);
