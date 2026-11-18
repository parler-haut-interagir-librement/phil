<?php

declare(strict_types=1);

/*
 * The body of a SCORM commit on bytes nobody wrote: it is read as a commit or as nothing, and a commit read is applied
 * to the tracking data of an attempt of each version, as the endpoint does; nothing is thrown, the data stays within
 * its bound, and what is kept loads back as tracking data.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\CoursesBundle\Infrastructure\Http\ScormCommitPayload;
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
    $payload = ScormCommitPayload::fromJson($input);

    if (!$payload instanceof ScormCommitPayload) {
        return;
    }

    foreach (ScormVersion::cases() as $version) {
        $data = new CmiData($version, maxBytes: 8_192);
        CmiCommit::apply($data, $payload->values);
        $kept = new CmiData($version, $data->values());

        if ($payload->finish) {
            CmiAttempt::end($kept);
        }

        if (strlen(implode('', array_keys($data->values())).implode('', $data->values())) > 8_192) {
            throw new LogicException('The data of an attempt outgrows its bound.');
        }
    }
});
$config->setMaxLen(4096);
