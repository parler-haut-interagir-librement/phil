<?php

declare(strict_types=1);

/*
 * The reader of the payloads a store kept, on bytes nobody wrote: each is read as a payload within its bounds, or
 * refused as one; nothing else is thrown, and nothing warns. A payload read is written back to JSON that reads as the
 * same payload, within 4,096 bytes.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Payload;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $payload = Payload::fromJson($input);
    } catch (InvalidRecord) {
        return;
    }

    $json = $payload->toJson();

    if (strlen($json) > Payload::MAX_JSON_BYTES || count($payload->values) > Payload::MAX_VALUES) {
        throw new LogicException('A payload out of its bounds is read.');
    }

    if (Payload::fromJson($json)->values !== $payload->values) {
        throw new LogicException('A payload written back reads as another one.');
    }
});
$config->setMaxLen(4200);
