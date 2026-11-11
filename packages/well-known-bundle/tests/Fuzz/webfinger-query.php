<?php

declare(strict_types=1);

/*
 * The reader of WebFinger queries, on text nobody wrote: whatever it reads, it returns a query whose resource reads back
 * to the same URI and whose relations are within their bound, or throws `InvalidValue`, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerQuery;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $query = WebFingerQuery::parse($input);
    } catch (InvalidValue) {
        return;
    }

    if (WebFingerResource::parse($query->resource->uri)->uri !== $query->resource->uri) {
        throw new LogicException('The URI of a resource does not read back to itself.');
    }

    if (count($query->rels) > WebFingerQuery::MAX_RELS || 1 === preg_match('/[\x00-\x20\x7F]/', $query->resource->uri)) {
        throw new LogicException('A query read beyond its bounds.');
    }
});
$config->setMaxLen(512);
