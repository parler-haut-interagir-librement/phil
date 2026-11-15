<?php

declare(strict_types=1);

/*
 * The readers of addresses: on any first line, a Url or InvalidUrl; on any second line, a declared host or InvalidUrl;
 * on any third line, a ServiceUrl on the declared hosts or InvalidUrl, and on any fourth, a reference resolved on the
 * same service or InvalidUrl; never another exception, and what they return always reads back as itself.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Url\Exception\InvalidUrl;
use Phil\Url\ServiceHosts;
use Phil\Url\ServiceUrl;
use Phil\Url\Url;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    [$address, $host, $service, $reference] = explode("\n", $input, 4) + ['', '', '', ''];

    try {
        $url = Url::fromString($address);

        if (!Url::fromString($url->toString())->equals($url)) {
            throw new LogicException(sprintf('"%s" does not read back as itself.', $url->toString()));
        }
    } catch (InvalidUrl) {
    }

    try {
        $hosts = new ServiceHosts('whisper', '10.0.0.5', '::1', $host);
    } catch (InvalidUrl) {
        $hosts = new ServiceHosts('whisper', '10.0.0.5', '::1');
    }

    try {
        $url = ServiceUrl::fromString($service, $hosts);
    } catch (InvalidUrl) {
        $url = ServiceUrl::fromString('http://whisper:8080/v1/', $hosts);
    }

    if (!$hosts->declares($url->host) || !ServiceUrl::fromString($url->toString(), $hosts)->equals($url)) {
        throw new LogicException(sprintf('"%s" does not read back as itself.', $url->toString()));
    }

    try {
        $resolved = $url->resolve($reference);
    } catch (InvalidUrl) {
        return;
    }

    if ($resolved->origin() !== $url->origin()) {
        throw new LogicException(sprintf('"%s" left "%s".', $resolved->toString(), $url->origin()));
    }
});
$config->setMaxLen(4096);
