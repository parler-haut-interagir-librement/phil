<?php

declare(strict_types=1);

/*
 * One visit of the race of the integration tests, in its own process and on its own connection: it waits until the
 * moment given, counts a visit of the link, and prints `counted` or `refused`.
 *
 *     php visit.php <url> <link> <moment as a Unix timestamp with microseconds>
 */

use Phil\ShortLinkBundle\Tests\Integration\Server;

$package = dirname(__DIR__, 2);
$autoload = array_find([$package.'/../../vendor/autoload.php', $package.'/vendor/autoload.php'], static fn (string $file): bool => is_file($file));

$arguments = $_SERVER['argv'] ?? [];

if (null === $autoload || !is_array($arguments) || 4 !== count($arguments)) {
    fwrite(\STDERR, "Usage: php visit.php <url> <link> <moment>\n");

    exit(2);
}

require $autoload;

[, $url, $link, $moment] = array_map(static fn (mixed $argument): string => is_string($argument) ? $argument : '', $arguments);
$repository = Server::connect($url)->repository();
$wait = (float) $moment - microtime(true);

if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

echo $repository->recordVisit((int) $link, new DateTimeImmutable()) ? 'counted' : 'refused';
