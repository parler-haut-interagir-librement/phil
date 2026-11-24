<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Fixtures;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Schema\Schema;
use Phil\FollowBundle\Application\Activities;
use Phil\FollowBundle\Application\AutoFollow;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalActivityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalDeliveryRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowerVisibilityRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalFollowRequestRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\DbalMutualRepository;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowTables;
use Phil\NotificationsBundle\Application\Channels;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\NotificationDispatcher;
use Phil\NotificationsBundle\Application\NotificationRenderer;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Application\Preferences\ConfiguredPreferences;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\NotificationsBundle\Infrastructure\Http\NullToasts;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Notifier\EmptyRecipientDirectory;
use Phil\NotificationsBundle\Infrastructure\Notifier\PlainTranslator;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle on SQLite in memory, its tables created from their definition, its statements counted, the real
 * notifications of phil/notifications-bundle handing their messages to a recording bus, and the application's ports
 * set by each test.
 */
final class Stage
{
    public readonly Connection $connection;

    public readonly CountingLogger $log;

    public readonly MockClock $clock;

    public readonly RecordingBus $notificationBus;

    public readonly Ports $ports;

    public readonly DbalFollowRepository $follows;

    public readonly DbalFollowRequestRepository $requests;

    public readonly DbalMutualRepository $mutuals;

    public readonly DbalActivityRepository $activities;

    public readonly DbalDeliveryRepository $deliveries;

    public readonly DbalFollowerVisibilityRepository $visibility;

    public readonly Notifications $notifications;

    public readonly SubjectChain $chain;

    public function __construct(?Connection $connection = null)
    {
        $this->log = new CountingLogger();
        $this->connection = $connection ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([new Middleware($this->log)]));
        self::createTables($this->connection);
        $this->clock = new MockClock('2026-11-21 12:00:00 UTC');
        $this->notificationBus = new RecordingBus();
        $this->ports = new Ports();
        $this->follows = new DbalFollowRepository($this->connection);
        $this->requests = new DbalFollowRequestRepository($this->connection);
        $this->mutuals = new DbalMutualRepository($this->connection);
        $this->activities = new DbalActivityRepository($this->connection);
        $this->deliveries = new DbalDeliveryRepository($this->connection);
        $this->visibility = new DbalFollowerVisibilityRepository($this->connection);
        $types = new NotificationTypes(
            new NotificationType(FollowTypes::ACTIVITY, [Channels::SITE, Channels::EMAIL], collapse: true),
            new NotificationType(FollowTypes::QUIET, [Channels::SITE], collapse: true),
            new NotificationType(FollowTypes::REQUESTED),
            new NotificationType(FollowTypes::ACCEPTED),
            new NotificationType(FollowTypes::FOLLOWED),
        );
        $directory = new EmptyRecipientDirectory();
        $handler = new SendNotificationHandler(new NotificationDispatcher($types, new InMemoryNotificationRepository(), new ConfiguredPreferences(), new NotificationRenderer(new PlainTranslator(), $types, $directory), $directory, new NullToasts(), $this->clock));
        $this->notifications = new Notifications($types, $handler, $this->notificationBus);
        $this->chain = new SubjectChain($this->ports, 5);
    }

    /**
     * The number one query returns, such as a `COUNT(*)`.
     */
    public function number(string $sql): int
    {
        $value = $this->connection->fetchOne($sql);

        return is_numeric($value) ? (int) $value : -1;
    }

    public static function createTables(Connection $connection): void
    {
        foreach (FollowTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function follows(): Follows
    {
        return new Follows($this->follows, $this->requests, $this->deliveries, $this->ports, $this->ports, $this->chain, $this->notifications, $this->clock);
    }

    public function requests(bool $followEachOther = true): FollowRequests
    {
        return new FollowRequests($this->requests, $this->follows, $this->mutuals, $this->notifications, $this->clock, followEachOther: $followEachOther);
    }

    public function mutuals(bool $enabled = true, bool $followEachOther = true): Mutuals
    {
        return new Mutuals($this->mutuals, $this->requests, $this->requests($followEachOther), $this->notifications, $this->clock, $enabled);
    }

    public function followers(bool $hiddenByDefault = false): Followers
    {
        return new Followers($this->follows, $this->visibility, $hiddenByDefault);
    }

    public function autoFollow(bool $onCreate = true, bool $onReply = true, FollowLevel $level = FollowLevel::Watching): AutoFollow
    {
        return new AutoFollow($this->follows, $this->ports, $this->ports, $this->clock, $onCreate, $onReply, $level);
    }

    public function handler(int $batchSize = 500, bool $onceUntilSeen = true, int $resendAfter = 3600, ?RecordingBus $bus = null): FanOutActivityHandler
    {
        return new FanOutActivityHandler($this->activities, $this->follows, $this->deliveries, $this->chain, $this->ports, $this->notifications, $this->clock, new FollowTypes(), $batchSize, $onceUntilSeen, $resendAfter, $bus);
    }

    public function activities(?FanOutActivityHandler $handler = null, ?RecordingBus $bus = null): Activities
    {
        return new Activities($this->activities, $handler ?? $this->handler(), $this->clock, $bus);
    }

    /**
     * @return list<SendNotification>
     */
    public function sent(?string $type = null): array
    {
        return array_values(array_filter($this->notificationBus->of(SendNotification::class), static fn (SendNotification $message): bool => null === $type || $message->type === $type));
    }

    /**
     * The members each notification of the type was sent to, in the order sent.
     *
     * @return list<string>
     */
    public function recipients(string $type): array
    {
        return array_merge(...array_map(static fn (SendNotification $message): array => $message->recipients, $this->sent($type)));
    }

    public static function subject(string $type, string $id): SubjectReference
    {
        return new SubjectReference($type, $id);
    }
}
