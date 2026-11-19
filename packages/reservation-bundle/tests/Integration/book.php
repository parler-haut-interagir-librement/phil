<?php

declare(strict_types=1);

/*
 * One request of the race of the integration tests, in its own process and on its own connection: it waits until the
 * moment given, books the resource, and prints `booked` or `refused`.
 *
 *     php book.php <url> <resource> <start> <end> <moment as a Unix timestamp with microseconds>
 */

use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Tests\Integration\Server;

$package = dirname(__DIR__, 2);
$autoload = array_find([$package.'/../../vendor/autoload.php', $package.'/vendor/autoload.php'], static fn (string $file): bool => is_file($file));

$arguments = $_SERVER['argv'] ?? [];

if (null === $autoload || !is_array($arguments) || 6 !== count($arguments)) {
    fwrite(\STDERR, "Usage: php book.php <url> <resource> <start> <end> <moment>\n");

    exit(2);
}

require $autoload;

[, $url, $resource, $start, $end, $moment] = array_map(static fn (mixed $argument): string => is_string($argument) ? $argument : '', $arguments);
$reservations = Server::connect($url)->reservations();
$wait = (float) $moment - microtime(true);

if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $reservations->book($resource, new DateTimeImmutable($start), new DateTimeImmutable($end), holder: 'member-'.getmypid());
    echo 'booked';
} catch (ReservationRefused) {
    echo 'refused';
}
