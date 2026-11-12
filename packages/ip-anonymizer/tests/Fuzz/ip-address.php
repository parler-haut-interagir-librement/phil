<?php

declare(strict_types=1);

/*
 * The reader of addresses, on text nobody wrote: whatever it reads, it returns an address whose own text it reads back
 * to the same bytes, or `null`, and throws nothing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IpAnonymizer\IpAddress;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $address = IpAddress::tryParse($input);

    if ($address instanceof IpAddress && !IpAddress::parse($address->toString())->equals($address)) {
        throw new LogicException('The text of an address does not read back to the same address.');
    }
});
$config->setMaxLen(64);
