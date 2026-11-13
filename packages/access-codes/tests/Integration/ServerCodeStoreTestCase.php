<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use Phil\AccessCodes\Tests\Unit\CodeStoreTestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Every promise of a store, on a real server read from the environment, and the redemption raced by eight processes at
 * the same instant: exactly one wins.
 */
abstract class ServerCodeStoreTestCase extends CodeStoreTestCase
{
    private const int RACERS = 8;

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testAnotherConnectionCannotRedeemACodeRedeemedInAnOpenTransaction(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 1), [self::hash(1)]);

        $other = $this->connect();
        $other->beginTransaction();
        self::assertSame('b1', new DbalCodeStore($other)->redeem([self::hash(1)], 'member-1', self::at(1)));
        $other->commit();
        $other->close();

        self::assertNull($store->redeem([self::hash(1)], 'member-2', self::at(2)));
        self::assertSame('member-1', $store->find([self::hash(1)])?->redeemedBy);
    }

    public function testEightProcessesRedeemingOneCodeLeaveOneWinner(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 1), [self::hash(1)]);

        $url = (string) getenv($this->variable());
        $php = new PhpExecutableFinder()->find(false);
        self::assertIsString($php);
        $barrier = sys_get_temp_dir().'/phil-access-codes-race-'.bin2hex(random_bytes(8));
        $processes = [];

        for ($racer = 0; $racer < self::RACERS; ++$racer) {
            $process = new Process([$php, __DIR__.'/race.php', $url, self::hash(1), 'member-'.$racer, $barrier]);
            $process->start();
            $processes[] = $process;
        }

        // Every racer has had the time to connect; the file sets them off together.
        usleep(500_000);
        touch($barrier);
        $outcomes = [];

        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $outcomes[] = trim($process->getOutput());
        }

        unlink($barrier);
        sort($outcomes);
        self::assertSame(['b1', ...array_fill(0, self::RACERS - 1, 'refused')], $outcomes);
        self::assertSame(1, $store->counts('b1')->redeemed);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    #[Override]
    protected function store(): CodeStore
    {
        $connection = $this->connection ??= $this->connect();
        $store = new DbalCodeStore($connection);

        foreach ([DbalCodeStore::CODE_TABLE, DbalCodeStore::BATCH_TABLE] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        return $store;
    }

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }
}
