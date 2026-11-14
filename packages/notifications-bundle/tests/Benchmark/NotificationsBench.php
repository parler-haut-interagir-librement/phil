<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\Preferences\ConfiguredPreferences;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use Phil\NotificationsBundle\Infrastructure\Http\NullToasts;
use Phil\NotificationsBundle\Infrastructure\Notifier\EmptyRecipientDirectory;
use Phil\NotificationsBundle\Infrastructure\Notifier\PlainTranslator;
use Symfony\Component\Clock\MockClock;

/**
 * The two hot paths: the unread count, asked on every page that shows the bell, and the delivery of one notification
 * to the centre, collapsed or not. The table holds the notifications of a thousand recipients, so that the count must
 * use its index on the recipient and the state, and stay flat as the table grows.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"notifications"})
 */
final class NotificationsBench
{
    private DbalNotificationRepository $repository;

    private NotificationDispatcher $dispatcher;

    private int $sent = 0;

    public function __construct()
    {
        $this->setUp(['rows' => 0]);
    }

    /**
     * @param array{rows?: int} $params
     */
    public function setUp(array $params): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create(), false)->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        $this->repository = new DbalNotificationRepository($connection);
        $at = new DateTimeImmutable('2026-11-08 00:00 UTC');
        $connection->beginTransaction();

        for ($i = 0; $i < ($params['rows'] ?? 10_000); ++$i) {
            $this->repository->record(new Notification('n'.$i, 'comment.replied', (string) ($i % 1000), null, $at->modify('+'.$i.' seconds')), false);
        }

        $connection->commit();
        $types = new NotificationTypes(new NotificationType('comment.replied', collapse: true), new NotificationType('welcome'));
        $directory = new EmptyRecipientDirectory();
        $this->dispatcher = new NotificationDispatcher($types, $this->repository, new ConfiguredPreferences(), new NotificationRenderer(new PlainTranslator(), $types, $directory), $directory, new NullToasts(), new MockClock('2026-11-08 12:00:00 UTC'));
    }

    /**
     * @return iterable<string, array{rows: int}>
     */
    public function tables(): iterable
    {
        yield '10k' => ['rows' => 10_000];
        yield '100k' => ['rows' => 100_000];
    }

    /**
     * @ParamProviders("tables")
     *
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function benchUnreadCount(): void
    {
        $this->repository->unreadCount('42');
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function benchDispatchToTheCentre(): void
    {
        $this->dispatcher->deliver(new SendNotification('welcome', ['r'.(++$this->sent % 500)]), 'r'.($this->sent % 500));
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function benchDispatchCollapsed(): void
    {
        $this->dispatcher->deliver(new SendNotification('comment.replied', ['42'], null, 'comment', '9'), '42');
    }
}
