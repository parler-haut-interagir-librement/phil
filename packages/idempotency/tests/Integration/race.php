<?php

declare(strict_types=1);

/*
 * One contender of ConcurrentReservationTest: waits for the start signal, then asks the guard for the key once and
 * prints the outcome.
 *
 *     php race.php <autoload> <dbal|cache> <path> <start-signal>
 */

use Doctrine\DBAL\DriverManager;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

[, $autoload, $adapter, $path, $signal] = $argv;

require $autoload;

$clock = new NativeClock();
$store = 'dbal' === $adapter
    ? new DbalIdempotencyStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]), $clock)
    : new CacheIdempotencyStore(new FilesystemAdapter('race', 0, $path), new LockFactory(new FlockStore($path)), $clock);
$guard = new IdempotencyGuard($store, $clock);

// Every contender is connected and ready before the first one asks.
$deadline = microtime(true) + 10;

while (!is_file($signal) && microtime(true) < $deadline) {
    usleep(500);
}

echo $guard->begin(IdempotencyScope::of('race'), IdempotencyKey::fromString('the-key'), PayloadFingerprint::ofParts('race'))->outcome->name;
