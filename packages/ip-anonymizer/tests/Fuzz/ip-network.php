<?php

declare(strict_types=1);

/*
 * The reader of networks in CIDR notation, on text nobody wrote: whatever it reads, it returns a network whose own
 * text it reads back to the same network, or refuses it with InvalidNetwork, and throws nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\IpNetwork;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $network = IpNetwork::parse($input);
    } catch (InvalidNetwork) {
        return;
    }

    if (!IpNetwork::parse($network->toString())->equals($network)) {
        throw new LogicException('The text of a network does not read back to the same network.');
    }
});
$config->setMaxLen(64);
