<?php

declare(strict_types=1);

/*
 * One request of the race of the integration tests, in its own process and on its own connection: it waits until the
 * moment given, issues the gift cards of the order, and prints how many it issued and how many the order has.
 *
 *     php issue.php <url> <order number> <moment as a Unix timestamp with microseconds>
 */

use Phil\CommerceStoredValueBundle\Tests\Integration\Server;

$package = dirname(__DIR__, 2);
$autoload = array_find([$package.'/../../vendor/autoload.php', $package.'/vendor/autoload.php'], static fn (string $file): bool => is_file($file));

$arguments = $_SERVER['argv'] ?? [];

if (null === $autoload || !is_array($arguments) || 4 !== count($arguments)) {
    fwrite(\STDERR, "Usage: php issue.php <url> <order number> <moment>\n");

    exit(2);
}

require $autoload;

[, $url, $number, $moment] = array_map(static fn (mixed $argument): string => is_string($argument) ? $argument : '', $arguments);
$server = Server::connect($url);
$order = Server::order($number);
$cards = $server->giftCards();
$wait = (float) $moment - microtime(true);

if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

$issuance = $cards->issueFor($order, new DateTimeImmutable('2026-11-20 10:05:00'));

echo count($issuance->issued).'/'.count($issuance->cards);
