<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Domain\Ticket\Status;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\SchemaListener;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\Desk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalTicketRepository::class)]
#[CoversClass(HelpdeskTables::class)]
#[CoversClass(SchemaListener::class)]
final class DbalTicketRepositoryTest extends TestCase
{
    public function testARequestIsWrittenAndReadBackInUtcWhateverTheTimeZoneOfPhp(): void
    {
        $zone = date_default_timezone_get();
        $connection = Desk::sqlite();
        $tickets = new DbalTicketRepository($connection);
        $id = ConversationId::generate();

        try {
            date_default_timezone_set('America/Toronto');
            $ticket = new Ticket($id, Desk::queue('billing'), Desk::id('ada'), new DateTimeImmutable('2026-11-13T10:00:00-05:00'));
            $tickets->save($ticket);
            $ticket->assign(Desk::id('agent-1'));
            $ticket->setMarking('closed');
            $ticket->touch(new DateTimeImmutable('2026-11-13T11:00:00-05:00'));
            $tickets->save($ticket);
            date_default_timezone_set('Asia/Tokyo');
            $read = $tickets->find($id);
        } finally {
            date_default_timezone_set($zone);
        }

        self::assertSame(['2026-11-13 15:00:00', '2026-11-13 16:00:00', '2026-11-13 16:00:00'], array_values((array) $connection->fetchAssociative('SELECT opened_at, updated_at, closed_at FROM phil_helpdesk_ticket')));
        self::assertEquals($ticket, $read);
        self::assertSame([Status::Closed, 'agent-1', 'ada', 'billing'], [$read?->status(), $read?->assignee()?->value, $read?->requester()?->value, $read?->queue()->value]);
        self::assertSame('UTC', $read?->openedAt->getTimezone()->getName());
        self::assertNull($tickets->find(ConversationId::generate()));
    }

    public function testTheQueuesAreReadByPageLatestChangeFirst(): void
    {
        $tickets = new DbalTicketRepository(Desk::sqlite());
        $made = [];

        foreach (range(1, 5) as $minute) {
            $ticket = new Ticket(ConversationId::generate(), Desk::queue(0 === $minute % 2 ? 'tech' : 'billing'), Desk::id('member-'.$minute), new DateTimeImmutable('2026-11-13 10:0'.$minute.':00 UTC'), 3 === $minute ? Status::Open : Status::New, 5 === $minute ? Desk::id('agent') : null);
            $tickets->save($ticket);
            $made[$minute] = $ticket->conversation->value;
        }

        $ids = static fn (array $list): array => array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $list);
        self::assertSame([$made[5], $made[3], $made[1]], $ids($tickets->inQueues([Desk::queue('billing')], null, null, 10)));
        self::assertSame([$made[5], $made[4]], $ids($tickets->inQueues([Desk::queue('billing'), Desk::queue('tech')], null, null, 2)));
        self::assertSame([$made[3], $made[2]], $ids($tickets->inQueues([Desk::queue('billing'), Desk::queue('tech')], null, null, 2, 2)));
        self::assertSame([$made[3]], $ids($tickets->inQueues([Desk::queue('billing')], Status::Open, null, 10)));
        self::assertSame([$made[5]], $ids($tickets->inQueues([Desk::queue('billing')], null, Desk::id('agent'), 10)));
        self::assertSame([$made[2]], $ids($tickets->ofRequester(Desk::id('member-2'), 10)));
        self::assertSame([], $ids($tickets->ofRequester(Desk::id('member-2'), 10, 1)));
    }

    public function testAParticipantIsForgottenFromTheRequests(): void
    {
        $tickets = new DbalTicketRepository(Desk::sqlite());
        $asked = new Ticket(ConversationId::generate(), Desk::queue('billing'), Desk::id('ada'), new DateTimeImmutable('2026-11-13 10:00:00 UTC'));
        $handled = new Ticket(ConversationId::generate(), Desk::queue('billing'), Desk::id('bob'), new DateTimeImmutable('2026-11-13 10:01:00 UTC'), Status::Open, Desk::id('ada'));
        $other = new Ticket(ConversationId::generate(), Desk::queue('billing'), Desk::id('bob'), new DateTimeImmutable('2026-11-13 10:02:00 UTC'));
        $tickets->save($asked);
        $tickets->save($handled);
        $tickets->save($other);

        self::assertSame([$asked->conversation->value, $handled->conversation->value], array_map(static fn (Ticket $ticket): string => $ticket->conversation->value, $tickets->concerning(Desk::id('ada'))));
        self::assertSame(2, $tickets->forget(Desk::id('ada')));
        self::assertNull($tickets->find($asked->conversation)?->requester());
        self::assertNull($tickets->find($handled->conversation)?->assignee());
        self::assertSame('bob', $tickets->find($handled->conversation)?->requester()?->value);
        self::assertSame([], $tickets->concerning(Desk::id('ada')));
        self::assertSame(0, $tickets->forget(Desk::id('ada')));
    }

    public function testTheTableIsAddedToTheSchemaOfItsConnectionOnly(): void
    {
        $connection = Desk::sqlite();
        $schema = HelpdeskTables::configure(Schema::editor()->create());

        self::assertTrue($schema->hasTable(HelpdeskTables::TICKET));
        self::assertSame($schema, HelpdeskTables::configure($schema), 'a schema that has the table is left as it is');

        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $event = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new SchemaListener($connection)->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(HelpdeskTables::TICKET));

        $other = new GenerateSchemaEventArgs($manager, Schema::editor()->create());
        new SchemaListener(Desk::sqlite())->postGenerateSchema($other);
        self::assertFalse($other->getSchema()->hasTable(HelpdeskTables::TICKET));
    }
}
