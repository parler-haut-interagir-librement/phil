<?php

declare(strict_types=1);

/*
 * What the console posts to `keys`, read from bytes nobody wrote. Whatever the bytes, the request reads, or is refused
 * with MalformedDocument; nothing else is thrown, and every request read writes back to a document that reads the
 * same. The console keys an agent stores are read the same way.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Signature\ConsoleKeys;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $request = KeyRequest::fromJson($input);

        if (KeyRequest::fromJson($request->toJson())->toJson() !== $request->toJson()) {
            throw new LogicException('A key request read does not read the same once written back.');
        }
    } catch (MalformedDocument) {
    }

    try {
        $keys = ConsoleKeys::fromJson($input);
    } catch (MalformedDocument) {
        return;
    }

    if (ConsoleKeys::fromJson($keys->toJson())->toJson() !== $keys->toJson()) {
        throw new LogicException('Console keys read do not read the same once written back.');
    }
});
