<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\ConversationBundle\Application\Conversations;
use Phil\ConversationBundle\Application\Reception;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Phil\HelpdeskBundle\Application\Contract\CannedReplies;
use Phil\HelpdeskBundle\Application\Helpdesk;
use Phil\HelpdeskBundle\Application\NoCannedReplies;
use Phil\HelpdeskBundle\Application\Queues;
use Phil\HelpdeskBundle\Domain\ValueObject\QueueName;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

/**
 * The help desk over SQLite in memory, or the connection given, its tables and those of the conversations created: the
 * queues `billing` (ROLE_BILLING) and `tech` (ROLE_TECH, ROLE_ADMIN), the agent working the queues `FixedAccess`
 * names, a mock clock and a dispatcher that records every event, those of the workflow included.
 */
final readonly class Desk
{
    public Connection $connection;

    public MockClock $clock;

    public EventDispatcher $dispatcher;

    public RecordingListener $events;

    public FixedAccess $access;

    public DbalTicketRepository $tickets;

    public Conversations $conversations;

    public Reception $reception;

    public Queues $queues;

    public Helpdesk $helpdesk;

    /**
     * @param list<string> $worked
     */
    public function __construct(array $worked = ['billing'], CannedReplies $cannedReplies = new NoCannedReplies(), ?Connection $connection = null)
    {
        $this->connection = $connection ?? self::sqlite();
        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->dispatcher = new EventDispatcher();
        $this->events = new RecordingListener($this->dispatcher);
        $this->access = new FixedAccess($worked);
        $this->tickets = new DbalTicketRepository($this->connection);
        $sealer = new KeyManagementMessageSealer(new SodiumKms(new InMemoryKeyLoader(['k1' => str_repeat('k', 32)])), 'k1');
        $this->reception = new Reception(new DbalReceptionRepository($this->connection));
        $this->conversations = new Conversations(new DbalConversationRepository($this->connection, $sealer), $this->reception, $this->clock, $this->dispatcher);
        $this->queues = Queues::fromDeclarations([
            'billing' => ['label' => 'queue.billing', 'roles' => ['ROLE_BILLING']],
            'tech' => ['label' => 'queue.tech', 'roles' => ['ROLE_TECH', 'ROLE_ADMIN']],
        ]);
        $this->helpdesk = new Helpdesk($this->tickets, $this->conversations, $this->queues, $this->access, $this->clock, $cannedReplies, $this->dispatcher);
    }

    public static function sqlite(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::create($connection);

        return $connection;
    }

    public static function create(Connection $connection): void
    {
        foreach (HelpdeskTables::configure(ConversationTables::configure(Schema::editor()->create()))->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public static function id(string $value): ParticipantId
    {
        return ParticipantId::fromString($value);
    }

    public static function queue(string $value): QueueName
    {
        return QueueName::fromString($value);
    }

    public function open(string $requester = 'ada', string $queue = 'billing', string $body = 'My invoice is wrong'): ConversationId
    {
        return $this->helpdesk->open(self::id($requester), self::queue($queue), 'Invoice', $body)->conversation;
    }
}
