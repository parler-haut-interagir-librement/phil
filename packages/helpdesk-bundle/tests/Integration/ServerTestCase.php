<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\Desk;
use PHPUnit\Framework\TestCase;

/**
 * The help desk on a real server, read from the environment: its table and those of the conversations created, a request
 * opened, answered, assigned and resolved, the queues read by page and filter, the moments in UTC, and the erasure.
 */
abstract class ServerTestCase extends TestCase
{
    private Connection $connection;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));

        foreach ([HelpdeskTables::TICKET, ConversationTables::BLOCK, ConversationTables::RECEPTION, ConversationTables::MESSAGE, ConversationTables::PARTICIPANT, ConversationTables::CONVERSATION] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        Desk::create($this->connection);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    public function testARequestGoesThroughTheDesk(): void
    {
        $desk = new Desk(['billing', 'tech'], connection: $this->connection);
        $first = $desk->open('ada', 'billing');
        $desk->clock->modify('+1 minute');
        $second = $desk->open('bob', 'tech');
        $desk->clock->modify('+1 minute');

        $desk->helpdesk->reply($first, Desk::id('agent-1'), 'Looking into it.');
        $desk->helpdesk->answer($first, Desk::id('ada'), 'Thanks.');
        $desk->helpdesk->apply($first, 'resolve');

        $ticket = $desk->tickets->find($first);
        self::assertSame([Status::Resolved, 'agent-1', 'billing'], [$ticket?->status(), $ticket?->assignee()?->value, $ticket?->queue()->value]);
        self::assertSame('2026-11-13T10:02:00+00:00', $ticket?->updatedAt()->format(\DATE_ATOM));
        self::assertSame([$first->value, $second->value], array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $desk->helpdesk->desk()));
        self::assertSame([$second->value], array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $desk->helpdesk->desk(null, Status::New)));
        self::assertSame([$first->value], array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $desk->helpdesk->desk(Desk::queue('billing'), null, Desk::id('agent-1'))));
        self::assertCount(3, $desk->conversations->messages($first, 10));

        self::assertSame(1, $desk->tickets->forget(Desk::id('ada')));
        self::assertNull($desk->tickets->find($first)?->requester());
    }
}
