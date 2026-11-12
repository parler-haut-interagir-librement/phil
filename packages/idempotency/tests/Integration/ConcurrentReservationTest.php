<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Integration;

use Composer\Autoload\ClassLoader;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Phil\Idempotency\Tests\Unit\Store\DbalIdempotencyStoreTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Eight processes ask for the same key at the same moment: exactly one proceeds, and every other one is told that a
 * run is in progress — through a SQLite file, and through a filesystem cache under file locks.
 */
#[CoversClass(IdempotencyGuard::class)]
#[CoversClass(DbalIdempotencyStore::class)]
#[CoversClass(CacheIdempotencyStore::class)]
final class ConcurrentReservationTest extends TestCase
{
    private const int CONTENDERS = 8;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-idempotency-race-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    #[TestWith(['dbal'])]
    #[TestWith(['cache'])]
    public function testOneContenderProceedsAndTheOthersWait(string $adapter): void
    {
        $path = $this->directory.'/store';

        if ('dbal' === $adapter) {
            DbalIdempotencyStoreTest::connection($path)->close();
        }

        $signal = $this->directory.'/go';
        $autoload = \dirname((string) new ReflectionClass(ClassLoader::class)->getFileName(), 2).'/autoload.php';
        $processes = [];

        for ($i = 0; $i < self::CONTENDERS; ++$i) {
            $process = new Process([\PHP_BINARY, __DIR__.'/race.php', $autoload, $adapter, $path, $signal]);
            $process->start();
            $processes[] = $process;
        }

        usleep(300_000);
        touch($signal);

        $outcomes = [];

        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $outcomes[] = $process->getOutput();
        }

        sort($outcomes);

        self::assertSame([...array_fill(0, self::CONTENDERS - 1, 'InProgress'), 'Proceed'], $outcomes);
    }
}
