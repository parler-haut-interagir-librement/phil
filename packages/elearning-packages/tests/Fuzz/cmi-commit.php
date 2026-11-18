<?php

declare(strict_types=1);

/*
 * The data model on commits nobody wrote: a JSON object of elements and values is applied to the data of an attempt of
 * each version; nothing is thrown, the data stays within its bound, and what is kept loads back as tracking data.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\ScormVersion;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $values = json_decode($input, true, 4, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($values)) {
        return;
    }

    foreach (ScormVersion::cases() as $version) {
        $data = new CmiData($version, maxBytes: 8_192);
        CmiCommit::apply($data, $values);

        if (strlen(implode('', array_keys($data->values())).implode('', $data->values())) > 8_192) {
            throw new LogicException('The data of an attempt outgrows its bound.');
        }

        $loaded = new CmiData($version, $data->values());
        CmiAttempt::end($loaded);
        $loaded->status();
    }
});
$config->setMaxLen(4096);
