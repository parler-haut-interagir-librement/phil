<?php

declare(strict_types=1);

/*
 * The reading of what nobody wrote by hand: a stored bundle, a sold bundle on its order line, and a cooperative
 * bundle, each read back from JSON. Each input gives a value or InvalidBundle — never another exception —, and a row
 * read writes back the same array.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ProductBundles\Cooperative\CooperativeBundle;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\ProductBundle;
use Phil\ProductBundles\SoldBundle;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    foreach ([ProductBundle::fromArray(...), SoldBundle::fromArray(...), CooperativeBundle::fromArray(...)] as $read) {
        try {
            $row = $read($data)->toArray();
        } catch (InvalidBundle) {
            continue;
        }

        if ($read($row)->toArray() !== $row) {
            throw new LogicException('A row read back writes the same row.');
        }
    }
});
