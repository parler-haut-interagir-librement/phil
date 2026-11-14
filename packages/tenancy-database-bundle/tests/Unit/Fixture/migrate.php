<?php

declare(strict_types=1);

/*
 * A migration command for the tests of the runner: `migrate.php <log> [--tenant=…]`. It appends its start and its end
 * to the log, and behaves by tenant: `broken` fails, `slow` outlives any short timeout, `noisy` fails with a long
 * output, `exact` with an output of exactly 4096 bytes, `both` with a line on each output, a tenant whose reference starts with `wait_` takes a second, any other succeeds.
 *
 * For the stops: a tenant whose reference starts with `slow_` appends its process id to `<log>.pids` and runs for half
 * a minute; `stubborn_` does the same and ignores SIGTERM; `graceful_` does the same and, on SIGTERM, prints that it
 * finished and exits with 0.
 */

$arguments = is_array($_SERVER['argv'] ?? null) ? array_values(array_filter($_SERVER['argv'], is_string(...))) : [];
$log = $arguments[1] ?? '';
$tenant = 'shared';

foreach (array_slice($arguments, 2) as $argument) {
    if (str_starts_with($argument, '--tenant=')) {
        $tenant = substr($argument, 9);
    }
}

file_put_contents($log, 'start '.$tenant."\n", \FILE_APPEND | \LOCK_EX);

if (1 === preg_match('/^(slow|stubborn|graceful)_/', $tenant, $kind)) {
    pcntl_async_signals(true);

    if ('stubborn' === $kind[1]) {
        pcntl_signal(\SIGTERM, \SIG_IGN);
    }

    if ('graceful' === $kind[1]) {
        pcntl_signal(\SIGTERM, static function (): never {
            echo 'finished on time';

            exit(0);
        });
    }

    file_put_contents($log.'.pids', getmypid()."\n", \FILE_APPEND | \LOCK_EX);

    for ($i = 0; $i < 600; ++$i) {
        usleep(50_000);
    }

    exit(0);
}

usleep(str_starts_with($tenant, 'wait_') ? 1_000_000 : 150_000);

switch ($tenant) {
    case 'broken':
        fwrite(\STDERR, 'The database refused the connection.');
        file_put_contents($log, 'end '.$tenant."\n", \FILE_APPEND | \LOCK_EX);

        exit(3);

    case 'slow':
        sleep(5);

        exit(0);

    case 'noisy':
        echo str_repeat('x', 5000).'END';

        exit(1);

    case 'exact':
        echo str_repeat('y', 4095).'Z';

        exit(1);

    case 'both':
        echo 'out';
        fwrite(\STDERR, 'err');

        exit(1);
}

echo 'migrated '.$tenant;
file_put_contents($log, 'end '.$tenant."\n", \FILE_APPEND | \LOCK_EX);
