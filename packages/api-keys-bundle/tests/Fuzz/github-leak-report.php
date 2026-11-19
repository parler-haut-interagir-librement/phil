<?php

declare(strict_types=1);

/*
 * The reader of the reports GitHub's secret scanning sends, on text nobody wrote: whatever it reads, it returns matches
 * within bounds, each a non-empty token and a type, or throws UnreadableReport, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\LeakReport;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $report = LeakReport::fromJson($input);
    } catch (UnreadableReport) {
        return;
    }

    if (count($report->matches) > LeakReport::MAX_MATCHES) {
        throw new LogicException('A report holds more matches than its bound.');
    }

    foreach ($report->matches as $match) {
        if ('' === $match['token'] || strlen($match['token']) > LeakReport::MAX_TOKEN_LENGTH || strlen($match['type']) > LeakReport::MAX_TYPE_LENGTH) {
            throw new LogicException('A match passes its bounds.');
        }
    }
});
$config->setMaxLen(4096);
