<?php

declare(strict_types=1);

/*
 * The reader of clamd's answers on bytes nobody wrote: whatever it reads, it answers a scan result whose signature or
 * reason is printable ASCII, and a version that is one or null; it never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\UploadScan\Clamd\ClamdResponse;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $result = ClamdResponse::scan($input);
    $text = $result->signature ?? $result->reason ?? '';

    if (!$result->isClean() && 1 !== preg_match('/^[\x21-\x7E](?:[\x20-\x7E]*[\x21-\x7E])?$/D', $text)) {
        throw new LogicException('A result carries a text that is not printable ASCII.');
    }

    $version = ClamdResponse::version($input);

    if (null !== $version && !str_starts_with($version, 'ClamAV ')) {
        throw new LogicException('A version that does not name ClamAV.');
    }

    ClamdResponse::isPong($input);
});
$config->setMaxLen(4096);
