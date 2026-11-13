<?php

declare(strict_types=1);

/*
 * Incidents and maintenances read from JSON nobody wrote. Whatever the bytes, the records read, or are refused with
 * InvalidStatusRecord; nothing else is thrown, and every record read is one the page can show.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\StatusPageBundle\Application\RecordsReader;
use Phil\StatusPageBundle\Domain\Exception\InvalidStatusRecord;

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

    try {
        $records = new RecordsReader()->read($data);
    } catch (InvalidStatusRecord) {
        return;
    }

    foreach ($records->incidents as $incident) {
        if ([] === $incident->updates || !$incident->impact->isOutage()) {
            throw new LogicException('An incident was read without an update or with an impact that is not an outage.');
        }
    }

    foreach ($records->maintenances as $maintenance) {
        if ($maintenance->endsAt <= $maintenance->startsAt) {
            throw new LogicException('A maintenance was read that does not end after it starts.');
        }
    }
});
