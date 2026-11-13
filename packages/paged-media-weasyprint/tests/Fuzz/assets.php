<?php

declare(strict_types=1);

use Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed;
use Phil\PagedMediaWeasyprint\LocalAssets;

/*
 * The references of a document nobody wrote, against a directory holding one image: the document always comes back as
 * text, and the only file it can ever hold is that image.
 */
$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$directory = sys_get_temp_dir().'/phil-weasyprint-fuzz-'.getmypid();

if (!is_dir($directory.'/assets')) {
    mkdir($directory.'/assets', 0o700, true);
    file_put_contents($directory.'/assets/in.png', 'INSIDE');
    file_put_contents($directory.'/out.png', 'OUTSIDE');
}

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($directory): void {
    try {
        $inlined = new LocalAssets($directory.'/assets')->inline($input);
    } catch (WeasyPrintFailed) {
        return;
    }

    if (str_contains($inlined, sodium_bin2base64('OUTSIDE', \SODIUM_BASE64_VARIANT_ORIGINAL))) {
        throw new LogicException('A file outside the directory was written into the document.');
    }
});
$config->setMaxLen(2048);
