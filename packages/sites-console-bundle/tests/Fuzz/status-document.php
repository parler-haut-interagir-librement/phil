<?php

declare(strict_types=1);

/*
 * The status an agent answers, read from bytes nobody wrote. Whatever the bytes, the status reads, or is refused with
 * MalformedDocument; nothing else is thrown, and every status read writes back to a document that reads the same.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SitesConsoleBundle\Domain\Document\Json;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $status = StatusDocument::fromJson($input);
    } catch (MalformedDocument) {
        return;
    }

    if (StatusDocument::fromJson(Json::encode($status->toArray()))->toArray() !== $status->toArray()) {
        throw new LogicException('A status read does not read the same once written back.');
    }
});
