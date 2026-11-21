<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Two processes admit a task at the same moment on a Doctrine DBAL usage store with room for one: one is admitted,
 * the other refused, and the meter holds one reservation — for the tokens, the budget and the ceiling.
 */
abstract class ConcurrentAdmissionTestCase extends TestCase
{
    /**
     * What each task reserves: its tokens, or its cost bound in micros.
     */
    public const int ROOM = 10;

    public const string CEILING_SUBJECT = 'site';

    private const int LIMIT = 15;

    private ?Connection $connection = null;

    /**
     * The meters of the tokens, the budget and the ceiling, each with room for one task.
     */
    public static function meters(): Meters
    {
        return new Meters(...array_map(static fn (string $name): Meter => new Meter($name, CalendarPeriod::month(), Limit::of(self::LIMIT)), ['ai_tokens', 'ai_budget', 'ai_ceiling']));
    }

    /**
     * @return iterable<string, array{string, string, string, string}> the meter, both subjects, and the subject counted
     */
    public static function meterProvider(): iterable
    {
        yield 'tokens' => ['ai_tokens', 'project-1', 'project-1', 'project-1'];
        yield 'budget' => ['ai_budget', 'project-1', 'project-1', 'project-1'];
        yield 'ceiling' => ['ai_ceiling', 'project-1', 'project-2', self::CEILING_SUBJECT];
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    #[DataProvider('meterProvider')]
    public function testTwoProcessesAtOnceAreNeverBothAdmitted(string $meter, string $first, string $second, string $counted): void
    {
        $url = $this->url();
        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url));
        $this->connection = $connection;
        $store = new DbalUsageStore($connection);

        foreach ([DbalUsageStore::COUNTER_TABLE, DbalUsageStore::KEY_TABLE] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($store->configureSchema($manager->introspectSchema()));

        $barrier = sys_get_temp_dir().'/phil-ai-barrier-'.bin2hex(random_bytes(8));
        $processes = [$this->start($url, $meter, $first, 'task-1', $barrier), $this->start($url, $meter, $second, 'task-2', $barrier)];

        // Both processes boot and connect, then start together once the barrier exists.
        usleep(500_000);
        touch($barrier);

        try {
            $outcomes = array_map($this->finish(...), $processes);
        } finally {
            unlink($barrier);
        }

        sort($outcomes);

        self::assertSame(['admitted', 'refused '.$meter], $outcomes);

        $used = $connection->fetchOne(\sprintf('SELECT SUM(used) FROM %s WHERE subject = ? AND meter = ?', DbalUsageStore::COUNTER_TABLE), [$counted, $meter]);
        self::assertIsNumeric($used);
        self::assertSame(self::ROOM, (int) $used);
    }

    /**
     * The database URL, the test skipped when there is none.
     */
    abstract protected function url(): string;

    private function start(string $url, string $meter, string $subject, string $taskId, string $barrier): Process
    {
        $process = new Process([\PHP_BINARY, __DIR__.'/Fixture/admit.php', $url, $meter, $subject, $taskId, $barrier], timeout: 30.0);
        $process->start();

        return $process;
    }

    private function finish(Process $process): string
    {
        $process->wait();
        self::assertSame([0, ''], [$process->getExitCode(), $process->getErrorOutput()], $process->getOutput());

        return $process->getOutput();
    }
}
