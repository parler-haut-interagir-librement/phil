<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPushSubscriptions;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationSchemaListener;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Tests\Unit\Fixture\Browser;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DbalPushSubscriptions::class)]
#[CoversClass(NotificationTables::class)]
#[CoversClass(NotificationSchemaListener::class)]
final class DbalPushSubscriptionsTest extends PushSubscriptionsTestCase
{
    private Connection $connection;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
    }

    public function testARowIsDatedAndKeyedByTheHashOfItsEndpoint(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));

        self::assertSame(
            [['id' => hash('sha256', 'https://fcm.googleapis.com/a'), 'recipient' => '42', 'endpoint' => 'https://fcm.googleapis.com/a', 'content_encoding' => 'aes128gcm', 'subscribed_at' => '2026-11-08 10:00:00']],
            $this->connection->fetchAllAssociative('SELECT id, recipient, endpoint, content_encoding, subscribed_at FROM phil_notification_push_subscriptions'),
        );
        self::assertSame(10, DbalPushSubscriptions::LIMIT);
    }

    public function testTheSubscriptionJustKeptStaysWhenTheOthersAreAsRecent(): void
    {
        $subscriptions = $this->subscriptions(2);

        foreach (['c', 'b', 'a', 'z'] as $path) {
            $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/'.$path));
        }

        self::assertContains('https://fcm.googleapis.com/z', $this->endpoints($subscriptions, '42'));
        self::assertCount(2, $subscriptions->of('42'));
    }

    public function testTheRecipientIsAnIdentifier(): void
    {
        $this->expectException(InvalidNotification::class);

        $this->subscriptions()->subscribe('a b', Browser::subscription());
    }

    public function testARowThatIsNoLongerASubscriptionIsLeftOut(): void
    {
        $subscriptions = $this->subscriptions();
        $subscriptions->subscribe('42', Browser::subscription('https://fcm.googleapis.com/a'));

        // Dated after the valid one, so that it is read first.
        $this->connection->insert(NotificationTables::PUSH_SUBSCRIPTIONS, ['id' => 'x', 'recipient' => '42', 'endpoint' => 'https://127.0.0.1/', 'public_key' => Browser::key(), 'auth_token' => Browser::AUTH, 'content_encoding' => 'aes128gcm', 'subscribed_at' => '2026-11-08 11:00:00']);

        self::assertSame(['https://fcm.googleapis.com/a'], $this->endpoints($subscriptions, '42'));
    }

    public function testTheTableJoinsTheSchemaWhenWebPushIsOn(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $without = new GenerateSchemaEventArgs($entityManager, Schema::editor()->create());
        new NotificationSchemaListener($connection)->postGenerateSchema($without);
        self::assertFalse($without->getSchema()->hasTable(NotificationTables::PUSH_SUBSCRIPTIONS));
        self::assertFalse(NotificationTables::configure(Schema::editor()->create())->hasTable(NotificationTables::PUSH_SUBSCRIPTIONS));

        $with = new GenerateSchemaEventArgs($entityManager, Schema::editor()->create());
        new NotificationSchemaListener($connection, false, true)->postGenerateSchema($with);
        $table = $with->getSchema()->getTable(NotificationTables::PUSH_SUBSCRIPTIONS);

        self::assertFalse($with->getSchema()->hasTable(NotificationTables::PREFERENCES));
        self::assertSame(['id'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame([64, 190, 2048, 88, 24, 16], array_map(static fn (string $column): ?int => $table->getColumn($column)->getLength(), ['id', 'recipient', 'endpoint', 'public_key', 'auth_token', 'content_encoding']));
        self::assertSame('datetime_utc_immutable', $table->getColumn('subscribed_at')->getTypeName());
        self::assertTrue($table->getColumn('subscribed_at')->getNotnull());
        self::assertTrue($table->hasIndex('phil_notification_push_subscriptions_recipient'));
        self::assertSame(['recipient', 'subscribed_at'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $table->getIndex('phil_notification_push_subscriptions_recipient')->getIndexedColumns()));
        self::assertCount(2, NotificationTables::configure($with->getSchema(), false, true)->getTables());
    }

    #[Override]
    protected function subscriptions(int $limit = 10): PushSubscriptions
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');

        foreach ($this->connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create(), false, true)->getTables()) as $sql) {
            $this->connection->executeStatement($sql);
        }

        return new DbalPushSubscriptions($this->connection, $this->clock, $limit);
    }

    #[Override]
    protected function later(): void
    {
        $this->clock->sleep(1);
    }
}
