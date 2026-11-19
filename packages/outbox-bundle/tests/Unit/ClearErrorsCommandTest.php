<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\OutboxBundle\Infrastructure\Console\ClearErrorsCommand;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Tests\Unit\Fixture\Databases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ClearErrorsCommand::class)]
final class ClearErrorsCommandTest extends TestCase
{
    public function testTheMessagesAreClearedAndCounted(): void
    {
        $store = new DbalOutboxStore(Databases::sqlite());
        $pending = OutboxRecord::pending(RecordId::generate(), 'a.b', Payload::of([]), null, null, new DateTimeImmutable('2026-11-17 10:00:00'));
        $store->add(new OutboxRecord($pending->id, 'a.b', '{}', null, null, $pending->occurredAt, $pending->availableAt, RecordState::Pending, 1, 'RuntimeException: <jane.doe@example.org> refused'));

        $application = new Application();
        $application->addCommand(new ClearErrorsCommand($store));

        $tester = new CommandTester($application->find('phil:outbox:errors:clear'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 errors cleared of their message.', $tester->getDisplay());
        self::assertSame('RuntimeException', $store->find($pending->id)?->lastError);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('0 errors cleared of their message.', $tester->getDisplay());
    }
}
