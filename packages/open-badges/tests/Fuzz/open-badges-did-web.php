<?php

declare(strict_types=1);

/*
 * The resolution of a did:web from the DID document its site serves, on documents nobody wrote: whatever it reads, it
 * gives a key or refuses with an exception of the library, and never anything else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\OpenBadges\Contract\DocumentFetcher;
use Phil\OpenBadges\Did\DidWebMethods;
use Phil\OpenBadges\Exception\OpenBadgesException;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$served = new class implements DocumentFetcher {
    public string $document = '';

    public function fetch(Url $url): string
    {
        return $this->document;
    }
};
$methods = new DidWebMethods($served);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($served, $methods): void {
    $served->document = $input;

    try {
        $methods->key('did:web:issuer.example.org#key-1');
    } catch (OpenBadgesException) {
    }
});
$config->setMaxLen(4096);
