<?php

declare(strict_types=1);

/*
 * The purifier of SVG images, sanitizer then minifier, on text nobody wrote: whatever it reads, it returns an SVG
 * element that reads again as well-formed XML and comes back the same through the purifier, or throws InvalidMedia,
 * and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Svg\SanitizingSvgPurifier;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$purifier = new SanitizingSvgPurifier();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($purifier): void {
    try {
        $purified = $purifier->purify($input);
    } catch (InvalidMedia) {
        return;
    }

    if (!str_starts_with($purified, '<svg')) {
        throw new LogicException('A purified image is not an SVG element.');
    }

    if ($purifier->purify($purified) !== $purified) {
        throw new LogicException('A purified image changes when it is purified again.');
    }
});
$config->setMaxLen(2048);
