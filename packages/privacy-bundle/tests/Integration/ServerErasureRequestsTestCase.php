<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\PrivacyBundle\Domain\Erasure\AttentionReason;
use Phil\PrivacyBundle\Domain\Erasure\ErasureRequest;
use Phil\PrivacyBundle\Domain\Erasure\ErasureStep;
use Phil\PrivacyBundle\Domain\Erasure\RequestState;
use Phil\PrivacyBundle\Domain\Erasure\StepState;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The store of the erasure requests on a real server, read from the environment: the tables as `PrivacyTables`
 * declares them, a step leased once, the transaction of `open()`, and identifiers compared byte by byte.
 */
abstract class ServerErasureRequestsTestCase extends TestCase
{
    private ?Connection $connection = null;

    private DateTimeImmutable $at;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('2026-11-20 10:00:00 UTC');
    }

    #[Override]
    protected function tearDown(): void
    {
        $connection = $this->connection;

        if ($connection instanceof Connection) {
            $this->drop($connection);
            $connection->close();
        }
    }

    public function testAStepGoesFromPendingToCompletedUnderOneLease(): void
    {
        $store = $this->store();
        $id = str_repeat('a', 32);
        $this->open($store, $id, 'member-7');

        self::assertTrue($store->lease($id, 'forum', 'one', $this->at, $this->at->modify('+60 seconds')));
        self::assertFalse($store->lease($id, 'forum', 'two', $this->at->modify('+30 seconds'), $this->at->modify('+90 seconds')));
        self::assertTrue($store->complete($id, 'forum', 'one', ['posts' => ErasureEffect::Anonymised], $this->at->modify('+10 seconds')));
        self::assertSame(['posts' => ErasureEffect::Anonymised], $store->step($id, 'forum')?->effects);

        self::assertTrue($store->flag($id, 'billing', AttentionReason::RetriesExhausted, $this->at));
        self::assertSame(['billing'], $store->reopen($id, $this->at));
        self::assertSame(StepState::Pending, $store->step($id, 'billing')?->state);
        self::assertTrue($store->settle($id, RequestState::Completed, $this->at->modify('+1 hour')));
        self::assertSame(1, $store->purge(new PurgeOrder('R-1', PrivacyTables::ERASURE_REQUESTS, $this->at->modify('+2 hours'))));
        self::assertNull($store->find($id));
        self::assertSame([], $store->steps($id));
    }

    public function testAFailureInTheTransactionKeepsNothing(): void
    {
        $store = $this->store();

        try {
            $store->open(ErasureRequest::forSubject(DataSubject::fromString('member-7'), '0123456789abcdef', $this->at, str_repeat('b', 32)), [ErasureStep::pending(str_repeat('b', 32), 'forum', $this->at)], static function (): never {
                throw new RuntimeException('The outbox is down.');
            });
        } catch (RuntimeException) {
        }

        self::assertNull($store->find(str_repeat('b', 32)));
    }

    public function testThePeopleCompareByteByByte(): void
    {
        $store = $this->store();
        $this->open($store, str_repeat('c', 32), 'Alice');
        $this->open($store, str_repeat('d', 32), 'alice');

        self::assertSame('Alice', $store->find(str_repeat('c', 32))?->subject?->value);
        self::assertEquals(1, $this->connection()->fetchOne('SELECT COUNT(*) FROM '.PrivacyTables::ERASURE_REQUESTS.' WHERE subject = ?', ['alice']));
    }

    private function open(DbalErasureRequests $store, string $id, string $subject): void
    {
        $store->open(
            new ErasureRequest($id, DataSubject::fromString($subject), null, '0123456789abcdef', RequestState::Running, $this->at),
            [ErasureStep::pending($id, 'billing', $this->at), ErasureStep::pending($id, 'forum', $this->at)],
            static function (): void {
            },
        );
    }

    private function store(): DbalErasureRequests
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->connection = $connection;
        $this->drop($connection);
        $platform = $connection->getDatabasePlatform();
        $schema = PrivacyTables::configure(Schema::editor()->create(), $platform);

        foreach ([PrivacyTables::ERASURE_REQUESTS, PrivacyTables::ERASURE_STEPS] as $table) {
            foreach ($platform->getCreateTableSQL($schema->getTable($table)) as $statement) {
                $connection->executeStatement($statement);
            }
        }

        return new DbalErasureRequests($connection);
    }

    private function drop(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS '.PrivacyTables::ERASURE_STEPS);
        $connection->executeStatement('DROP TABLE IF EXISTS '.PrivacyTables::ERASURE_REQUESTS);
    }

    private function connection(): Connection
    {
        return $this->connection ?? throw new RuntimeException('The store is not created.');
    }
}
