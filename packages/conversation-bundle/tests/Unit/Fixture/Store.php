<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\ConversationBundle\Application\Contract\ContactRelation;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Application\ConversationLimits;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Application\NoContacts;
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Symfony\Component\Clock\MockClock;

/**
 * The bundle over SQLite in memory, or over the connection given, its tables created: the stores sealed by the two
 * keys of `Keys`, a mock clock, a recording dispatcher, and the entry points.
 */
final readonly class Store
{
    public Connection $connection;

    public MockClock $clock;

    public RecordingEvents $events;

    public DbalConversationRepository $repository;

    public DbalReceptionRepository $receptions;

    public Reception $reception;

    public Conversations $conversations;

    public function __construct(
        ?Connection $connection = null,
        public MessageSealer $sealer = new SealerStandIn(),
        ContactRelation $contacts = new NoContacts(),
        ConversationLimits $limits = new ConversationLimits(),
    ) {
        $this->connection = $connection ?? self::sqlite();
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->events = new RecordingEvents();
        $this->repository = new DbalConversationRepository($this->connection, $sealer);
        $this->receptions = new DbalReceptionRepository($this->connection);
        $this->reception = new Reception($this->receptions, $contacts);
        $this->conversations = new Conversations($this->repository, $this->reception, $this->clock, $this->events, $limits);
    }

    public static function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::create($connection);

        return $connection;
    }

    public static function create(Connection $connection): void
    {
        foreach (ConversationTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public static function id(string $value): ParticipantId
    {
        return ParticipantId::fromString($value);
    }
}
