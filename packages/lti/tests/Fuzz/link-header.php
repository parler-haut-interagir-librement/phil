<?php

declare(strict_types=1);

/*
 * The reader of `Link` headers on text nobody wrote: it finds an address, an absolute HTTPS or HTTP one, or none, and
 * never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Lti\Http\LinkHeader;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$base = Url::fromString('https://lms.example.org/c/1/members');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($base): void {
    foreach (['next', 'differences'] as $relation) {
        $found = LinkHeader::find([$input], $relation, $base);

        if ($found instanceof Url && !in_array($found->scheme, ['http', 'https'], true)) {
            throw new LogicException('A link was read to an address that is not on the web.');
        }
    }
});
$config->setMaxLen(4096);
