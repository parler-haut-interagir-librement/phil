<?php

declare(strict_types=1);

/*
 * The reading of what nobody wrote by hand: a stored pickup row read back, and a scanned QR payload. Each input gives
 * a value or InvalidPickup — never another exception —, and a row read writes back the same array.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Pickup;
use Phil\LocalPickup\ScannedCode;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $scanned = ScannedCode::parse($input);

        if (8 !== strlen($scanned->code)) {
            throw new LogicException('A scanned code is eight characters.');
        }
    } catch (InvalidPickup) {
    }

    try {
        $data = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $row = Pickup::fromArray($data)->toArray();
    } catch (InvalidPickup) {
        return;
    }

    if (Pickup::fromArray($row)->toArray() !== $row) {
        throw new LogicException('A pickup read back writes the same row.');
    }
});
