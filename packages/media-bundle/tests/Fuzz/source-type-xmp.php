<?php

declare(strict_types=1);

/*
 * The writer of the digital source type into rendered images, on bytes no encoder wrote: the first byte picks the file
 * type, the rest is the image. Whatever it reads, it returns the image unchanged or with exactly one XMP packet, and
 * never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\MediaBundle\Application\Xmp\SourceTypeXmp;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$uri = 'http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia';
$packet = SourceTypeXmp::packet($uri);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($uri, $packet): void {
    if ('' === $input) {
        return;
    }

    $image = substr($input, 1);
    $written = SourceTypeXmp::write($image, ['jpeg', 'png', 'webp', 'avif'][ord($input[0]) % 4], $uri);

    if ($written !== $image && substr_count($written, $packet) !== substr_count($image, $packet) + 1) {
        throw new LogicException('A written image does not carry exactly one more packet.');
    }
});
$config->setMaxLen(4096);
