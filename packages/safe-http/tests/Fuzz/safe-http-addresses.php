<?php

declare(strict_types=1);

/*
 * The guard of the addresses the client reads: on any first line, an address or RefusedAddress; on any second line, a
 * redirect from a known address resolved to a public address or RefusedAddress; never another exception, and what it
 * returns always reads back as itself.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SafeHttp\AddressGuard;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$from = Url::fromString('https://example.com/a/b?c#d');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($from): void {
    [$address, $location] = explode("\n", $input, 2) + ['', ''];

    foreach ([static fn (): Url => AddressGuard::address($address), static fn (): Url => AddressGuard::redirect($from, $location)] as $check) {
        try {
            $url = $check();
        } catch (RefusedAddress) {
            continue;
        }

        if (!Url::fromString($url->toString())->equals($url) || !in_array($url->scheme, ['http', 'https'], true)) {
            throw new LogicException(sprintf('"%s" does not read back as itself.', $url->toString()));
        }
    }
});
$config->setMaxLen(4096);
