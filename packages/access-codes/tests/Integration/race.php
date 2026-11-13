<?php

declare(strict_types=1);

/*
 * One racer of ServerCodeStoreTestCase: connects to the server, waits until the starting file exists, redeems the
 * code, and prints the batch it won or "refused".
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Phil\AccessCodes\Dbal\DbalCodeStore;

require dirname(__DIR__, 4).'/vendor/autoload.php';

[, $url, $hash, $redeemer, $barrier] = $argv;
$connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
$connection->getServerVersion();
$store = new DbalCodeStore($connection);

$deadline = microtime(true) + 10.0;

while (!file_exists($barrier) && microtime(true) < $deadline) {
    usleep(500);
}

echo $store->redeem([$hash], $redeemer, new DateTimeImmutable('2026-11-13 10:00:10')) ?? 'refused', "\n";
