<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Outbox\Backoff;
use Phil\Outbox\Codecs;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\RecordedEvent;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\RecordId;
use Phil\Outbox\RecordState;
use Phil\Outbox\Relay;
use Phil\OutboxBundle\Infrastructure\Console\DeadRemoveCommand;
use Phil\OutboxBundle\Infrastructure\Console\DeadRetryCommand;
use Phil\OutboxBundle\Infrastructure\Console\DeadShowCommand;
use Phil\OutboxBundle\Infrastructure\Console\PurgeCommand;
use Phil\OutboxBundle\Infrastructure\Console\RelayCommand;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;
use Phil\OutboxBundle\Tests\Unit\Fixture\ListSpyStore;
use Phil\OutboxBundle\Tests\Unit\Fixture\Outboxes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(RelayCommand::class)]
#[CoversClass(DeadShowCommand::class)]
#[CoversClass(DeadRetryCommand::class)]
#[CoversClass(DeadRemoveCommand::class)]
#[CoversClass(PurgeCommand::class)]
final class CommandsTest extends TestCase
{
    public function testTheRelayRunsUntilTheOutboxIsEmpty(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(3);

        $tester = $this->tester(new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock), 2), 'phil:outbox:relay');

        self::assertSame(Command::SUCCESS, $tester->execute(['--stop-when-empty' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));

        $display = $tester->getDisplay();
        self::assertStringContainsString($this->flat('Claimed 2: 2 delivered, 0 to try again, 0 dead, 0 left to their lease, 0 taken over.'), $this->flat($display));
        self::assertStringContainsString($this->flat('Claimed 1: 1 delivered'), $this->flat($display));
        self::assertStringContainsString($this->flat('[OK] Claimed 3: 3 delivered, 0 to try again, 0 dead, 0 left to their lease, 0 taken over.'), $this->flat($display));
        self::assertSame(3, substr_count($display, 'Claimed'), 'Two batches and the sum: an empty batch is not told.');
        self::assertCount(3, $outboxes->received);
    }

    public function testTheBatchesAreQuietWithoutVerbose(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(3);

        $tester = $this->tester(new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock)), 'phil:outbox:relay');

        $tester->execute(['--batch-size' => '1', '--limit' => '2']);

        self::assertSame(1, substr_count($tester->getDisplay(), 'Claimed'));
        $this->assertSees('Claimed 2: 2 delivered', $tester);
    }

    public function testTheRelayGivesWayToARunThatHoldsTheLock(): void
    {
        $outboxes = new Outboxes();
        $locks = new LockFactory(new InMemoryStore());
        $held = $locks->createLock(RelayRunner::LOCK);
        $held->acquire();

        $tester = $this->tester(new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock, $locks)), 'phil:outbox:relay');

        self::assertSame(Command::SUCCESS, $tester->execute(['--stop-when-empty' => true]));
        $this->assertSees('Another relay holds the lock: this one gives way.', $tester);
    }

    public function testTheRelayRunsUntilItsTimeIsSpent(): void
    {
        $outboxes = new Outboxes();
        $tester = $this->tester(new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock)), 'phil:outbox:relay');

        self::assertSame(Command::SUCCESS, $tester->execute(['--time-limit' => '3', '--sleep' => '0.5']));
        self::assertSame('2026-11-17 10:00:03', $outboxes->clock->now()->format('Y-m-d H:i:s'));
    }

    public function testASignalEndsTheRelayAfterTheBatchUnderWay(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(3);

        $command = new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock), 1);
        $outboxes->dispatcher->addListener(RecordedEvent::class, static function () use ($command): void {
            self::assertFalse($command->handleSignal(15));
        });

        $this->tester($command, 'phil:outbox:relay')->execute([]);

        self::assertCount(1, $outboxes->received);
        self::assertFalse($command->handleSignal(2));
        self::assertSame(\extension_loaded('pcntl') ? [2, 15] : [], $command->getSubscribedSignals());

        $this->tester($command, 'phil:outbox:relay')->execute(['--limit' => '1']);
        self::assertSame(2, $outboxes->receivedCount(), 'A new run forgets the signal of the last one.');
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function refusedOptions(): iterable
    {
        yield 'no batch' => [['--batch-size' => '0']];
        yield 'a batch too large' => [['--batch-size' => '1001']];
        yield 'a batch that is not a number' => [['--batch-size' => 'many']];
        yield 'a limit that is not a number' => [['--limit' => 'many']];
        yield 'a negative limit' => [['--limit' => '-1']];
        yield 'a negative time limit' => [['--time-limit' => '-1']];
        yield 'a negative sleep' => [['--sleep' => '-0.5']];
        yield 'a sleep too long' => [['--sleep' => '3601']];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('refusedOptions')]
    public function testAnOptionOutOfBoundsIsRefused(array $options): void
    {
        $outboxes = new Outboxes();
        $tester = $this->tester(new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock)), 'phil:outbox:relay');

        self::assertSame(Command::INVALID, $tester->execute($options));
        $this->assertSees('--batch-size is a number from 1 to 1000, --sleep from 0 to 3600, --limit and --time-limit 0 or more.', $tester);
    }

    public function testTheBoundsOfTheOptionsAreAccepted(): void
    {
        $outboxes = new Outboxes();
        $tester = $this->tester(new RelayCommand(new RelayRunner($outboxes->relay, $outboxes->clock)), 'phil:outbox:relay');

        self::assertSame(Command::SUCCESS, $tester->execute(['--batch-size' => '1000', '--sleep' => '3600', '--limit' => '1']));
        self::assertSame(Command::SUCCESS, $tester->execute(['--batch-size' => '1', '--sleep' => '0', '--limit' => '1']));
    }

    public function testTheDeadRecordsAreListedWithTheCounts(): void
    {
        $outboxes = $this->withDead(2);
        $tester = $this->tester(new DeadShowCommand($outboxes->housekeeping), 'phil:outbox:dead:show');

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        self::assertStringContainsString($this->flat('1 pending, 1 delivered, 2 dead.'), $this->flat($display));
        self::assertStringContainsString($this->flat('quiz.completed'), $this->flat($display));
        self::assertStringContainsString($this->flat('RuntimeException (code 0)'), $this->flat($display));
        self::assertStringNotContainsString('The listener is down', $display);
        self::assertStringContainsString($this->flat('2026-11-26 10:00:00'), $this->flat($display));
        self::assertSame(2, substr_count($display, 'quiz:'));
        self::assertStringContainsString('Identifier', $display);

        foreach ($outboxes->housekeeping->dead() as $record) {
            self::assertStringContainsString($record->id->value, $display);
        }

        $tester->execute(['--limit' => '1', '--offset' => '1']);
        self::assertSame(1, substr_count($tester->getDisplay(), 'quiz:q1'));
        self::assertSame(0, substr_count($tester->getDisplay(), 'quiz:q0'));

        $tester->execute(['--limit' => '0', '--offset' => '-4']);
        self::assertSame(1, substr_count($tester->getDisplay(), 'quiz:q0'), 'The list holds one record at least, from the first.');
        self::assertSame(0, substr_count($tester->getDisplay(), 'quiz:q1'));
    }

    public function testALongErrorIsCutInTheList(): void
    {
        $outboxes = new Outboxes();
        $outboxes->outbox->record('quiz.completed', ['quiz' => 'q0']);
        $outboxes->dispatcher->addListener(RecordedEvent::class, static function (): never {
            throw InvalidRecord::because(str_repeat('x', 100));
        });
        $relay = new Relay($outboxes->store, new Codecs([new RecordedEventCodec(['quiz.completed'])]), $outboxes->dispatcher, $outboxes->clock, new Backoff(maxAttempts: 1));
        $relay->relay();

        $tester = $this->tester(new DeadShowCommand($outboxes->housekeeping), 'phil:outbox:dead:show');

        $tester->execute([]);

        $error = 'Phil\\Outbox\\Exception\\InvalidRecord (code 0): ';
        $this->assertSees(mb_strimwidth($error.str_repeat('x', 100), 0, 80, '…'), $tester);
        self::assertStringNotContainsString(str_repeat('x', 80 - \strlen($error)), $tester->getDisplay());
    }

    public function testNoDeadRecordIsSaidSo(): void
    {
        $tester = $this->tester(new DeadShowCommand(new Outboxes()->housekeeping), 'phil:outbox:dead:show');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSees('No dead record.', $tester);
        self::assertStringNotContainsString('Identifier', $tester->getDisplay());
    }

    public function testAHundredAndOneDeadRecordsAreListedFiftyAtATimeFromTheFirst(): void
    {
        $store = new ListSpyStore();
        $clock = new MockClock('2026-11-17 10:00:00', 'UTC');
        $this->kill($store, 51, RecordState::Dead);
        $tester = $this->tester(new DeadShowCommand(new Housekeeping($store, $clock)), 'phil:outbox:dead:show');

        $tester->execute([]);
        self::assertSame(50, substr_count($tester->getDisplay(), 'order-'));
        self::assertSame([RecordState::Dead, 50, 0], $store->lists[0]);

        $tester->execute(['--offset' => '-4']);
        self::assertSame([RecordState::Dead, 50, 0], $store->lists[1]);
    }

    public function testADeadRecordWithoutASettlementIsListedAndShown(): void
    {
        $outboxes = new Outboxes();
        $at = $outboxes->clock->now();
        $record = new OutboxRecord(RecordId::generate(), 'quiz.completed', '{}', 'order-0', null, $at, $at, RecordState::Dead, 3, 'broken');
        $outboxes->store->add($record);

        $list = $this->tester(new DeadShowCommand($outboxes->housekeeping), 'phil:outbox:dead:show');
        self::assertSame(Command::SUCCESS, $list->execute([]));
        $this->assertSees('order-0', $list);

        self::assertSame(Command::SUCCESS, $list->execute(['id' => $record->id->value]));
        $this->assertSees('Type quiz.completed', $list);
        $this->assertSees('Settled at Last error broken', $list);
    }

    public function testADeadRecordIsShown(): void
    {
        $outboxes = $this->withDead(1);
        [$record] = $outboxes->housekeeping->dead();
        $tester = $this->tester(new DeadShowCommand($outboxes->housekeeping), 'phil:outbox:dead:show');

        self::assertSame(Command::SUCCESS, $tester->execute(['id' => $record->id->value]));

        $display = $tester->getDisplay();

        self::assertNotNull($record->settledAt);
        $this->assertSees('Type quiz.completed', $tester);
        $this->assertSees('Settled at '.$record->settledAt->format('Y-m-d H:i:s'), $tester);

        foreach (['Identifier', $record->id->value, 'dead', '{"quiz":"q0"}', 'quiz:q0', 'once:q0', 'Occurred at', 'Attempts', 'Settled at', 'RuntimeException (code 0)'] as $expected) {
            self::assertStringContainsString($this->flat($expected), $this->flat($display));
        }
    }

    public function testAnUnknownOrMalformedRecordIsNotShown(): void
    {
        $tester = $this->tester(new DeadShowCommand(new Outboxes()->housekeeping), 'phil:outbox:dead:show');
        $id = RecordId::generate()->value;

        self::assertSame(Command::FAILURE, $tester->execute(['id' => $id]));
        $this->assertSees(\sprintf('No record %s.', $id), $tester);
        self::assertSame(Command::FAILURE, $tester->execute(['id' => 'nope']));
        $this->assertSees('"nope" is not a record identifier', $tester);
    }

    public function testDeadRecordsAreTriedAgain(): void
    {
        $outboxes = $this->withDead(3);
        $dead = $outboxes->housekeeping->dead();
        $tester = $this->tester(new DeadRetryCommand($outboxes->housekeeping), 'phil:outbox:dead:retry');

        self::assertSame(Command::SUCCESS, $tester->execute(['ids' => [$dead[0]->id->value]]));
        $this->assertSees('1 records back to pending.', $tester);
        self::assertSame(Command::FAILURE, $tester->execute(['ids' => [$dead[1]->id->value, 'nope', $dead[0]->id->value]]));
        $this->assertSees('nope is not a dead record.', $tester);
        $this->assertSees($dead[0]->id->value.' is not a dead record.', $tester);
        $this->assertSees('1 records back to pending.', $tester);
        self::assertSame(1, $outboxes->store->count(RecordState::Dead));

        self::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        $this->assertSees('1 records back to pending.', $tester);
        self::assertSame(0, $outboxes->store->count(RecordState::Dead));
    }

    public function testRetryingAllIsBoundedByTheLimit(): void
    {
        $outboxes = $this->withDead(3);
        $tester = $this->tester(new DeadRetryCommand($outboxes->housekeeping), 'phil:outbox:dead:retry');

        $tester->execute(['--all' => true, '--limit' => '0']);
        self::assertSame(2, $outboxes->store->count(RecordState::Dead), 'A limit below 1 retries one record.');

        $tester->execute(['--all' => true, '--limit' => '2']);
        self::assertSame(0, $outboxes->store->count(RecordState::Dead));
    }

    public function testAHundredDeadRecordsAreTriedAgainByDefault(): void
    {
        $outboxes = new Outboxes();
        $this->kill($outboxes->store, 101, RecordState::Dead);
        $tester = $this->tester(new DeadRetryCommand($outboxes->housekeeping), 'phil:outbox:dead:retry');

        $tester->execute(['--all' => true]);

        $this->assertSees('100 records back to pending.', $tester);
        self::assertSame(1, $outboxes->store->count(RecordState::Dead));
    }

    public function testAHundredDeadRecordsAreRemovedByDefault(): void
    {
        $outboxes = new Outboxes();
        $this->kill($outboxes->store, 101, RecordState::Dead);
        $tester = $this->tester(new DeadRemoveCommand($outboxes->housekeeping), 'phil:outbox:dead:remove');

        $tester->execute(['--all' => true, '--force' => true]);

        $this->assertSees('100 records removed.', $tester);
        self::assertSame(1, substr_count($tester->getDisplay(), 'records removed'));
        self::assertSame(1, $outboxes->store->count(RecordState::Dead));
    }

    public function testAThousandDeliveredRecordsArePurgedByDefault(): void
    {
        $outboxes = new Outboxes();
        $this->kill($outboxes->store, 1001, RecordState::Delivered);
        $tester = $this->tester(new PurgeCommand($outboxes->housekeeping, 0), 'phil:outbox:purge');
        $outboxes->clock->sleep(1);

        $tester->execute([]);

        $this->assertSees('1000 delivered records removed.', $tester);
        self::assertSame(1, $outboxes->store->count(RecordState::Delivered));
    }

    public function testARetryNamesItsRecords(): void
    {
        $tester = $this->tester(new DeadRetryCommand(new Outboxes()->housekeeping), 'phil:outbox:dead:retry');

        self::assertSame(Command::INVALID, $tester->execute([]));
        $this->assertSees('Name the dead records, or give --all.', $tester);
    }

    public function testDeadRecordsAreRemovedOnlyWithForce(): void
    {
        $outboxes = $this->withDead(3);
        $dead = $outboxes->housekeeping->dead();
        $tester = $this->tester(new DeadRemoveCommand($outboxes->housekeeping), 'phil:outbox:dead:remove');

        self::assertSame(Command::INVALID, $tester->execute(['--force' => true]));
        $this->assertSees('Name the dead records, or give --all.', $tester);
        self::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        $this->assertSees('Nothing removed: run again with --force to remove the records for good.', $tester);
        self::assertSame(3, $outboxes->store->count(RecordState::Dead));

        self::assertSame(Command::SUCCESS, $tester->execute(['ids' => [$dead[0]->id->value], '--force' => true]));
        $this->assertSees('1 records removed.', $tester);
        self::assertSame(Command::FAILURE, $tester->execute(['ids' => [$dead[1]->id->value, 'nope'], '--force' => true]));
        $this->assertSees('nope is not a dead record.', $tester);
        $this->assertSees('1 records removed.', $tester);

        self::assertSame(Command::SUCCESS, $tester->execute(['--all' => true, '--force' => true, '--limit' => '0']));
        $this->assertSees('1 records removed.', $tester);
        self::assertSame(0, $outboxes->store->count(RecordState::Dead));
    }

    public function testRemovingAllIsBoundedByTheLimit(): void
    {
        $outboxes = $this->withDead(3);
        $tester = $this->tester(new DeadRemoveCommand($outboxes->housekeeping), 'phil:outbox:dead:remove');

        $tester->execute(['--all' => true, '--force' => true, '--limit' => '0']);
        self::assertSame(2, $outboxes->store->count(RecordState::Dead), 'A limit below 1 removes one record.');
        $tester->execute(['--all' => true, '--force' => true, '--limit' => '2']);
        self::assertSame(0, $outboxes->store->count(RecordState::Dead));
    }

    public function testTheDeliveredRecordsArePurgedAfterTheConfiguredRetention(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(3);
        $outboxes->relay->relay();

        $tester = $this->tester(new PurgeCommand($outboxes->housekeeping, 120), 'phil:outbox:purge');

        $outboxes->clock->sleep(120);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSees('0 delivered records removed.', $tester);

        $outboxes->clock->sleep(1);
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '0']));
        $this->assertSees('1 delivered records removed.', $tester);
        $tester->execute(['--limit' => '2']);
        $this->assertSees('2 delivered records removed.', $tester);
    }

    public function testTheRetentionIsChosenOnTheCommandLine(): void
    {
        $outboxes = new Outboxes();
        $outboxes->record(1);
        $outboxes->relay->relay();

        $tester = $this->tester(new PurgeCommand($outboxes->housekeeping), 'phil:outbox:purge');
        $outboxes->clock->sleep(11);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSees('0 delivered records removed.', $tester);
        self::assertSame(Command::SUCCESS, $tester->execute(['--retention' => '10']));
        $this->assertSees('1 delivered records removed.', $tester);
        self::assertSame(Command::INVALID, $tester->execute(['--retention' => '-1']));
        $this->assertSees('--retention is 0 seconds or more.', $tester);
        self::assertSame(Command::SUCCESS, $tester->execute(['--retention' => '0']));
    }

    /**
     * Adds records of a state straight to a store, the dead and the delivered settled a moment ago.
     */
    private function kill(OutboxStore $store, int $count, RecordState $state): void
    {
        $at = new DateTimeImmutable('2026-11-17 10:00:00', new DateTimeZone('UTC'));

        for ($index = 0; $index < $count; ++$index) {
            $store->add(new OutboxRecord(RecordId::generate(), 'quiz.completed', '{}', 'order-'.$index, null, $at, $at, $state, 1, 'broken', $at));
        }
    }

    private function withDead(int $count): Outboxes
    {
        $outboxes = new Outboxes();
        $outboxes->outbox->record('quiz.completed', ['quiz' => 'delivered']);
        $outboxes->relay->relay(1);

        for ($index = 0; $index < $count; ++$index) {
            $outboxes->outbox->record('quiz.completed', ['quiz' => 'q'.$index], 'quiz:q'.$index, 'once:q'.$index);
        }

        $outboxes->dispatcher->addListener(RecordedEvent::class, static function (): never {
            throw new RuntimeException('The listener is down.');
        });

        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $outboxes->relay->relay();
            $outboxes->clock->sleep(86_400);
        }

        $outboxes->outbox->record('quiz.completed', ['quiz' => 'pending']);

        return $outboxes;
    }

    private function tester(callable|Command $command, string $name): CommandTester
    {
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find($name));
    }

    /**
     * Whether the display holds a sentence, wherever a console block wrapped it at the terminal's width.
     */
    private function assertSees(string $expected, CommandTester $tester): void
    {
        self::assertStringContainsString($this->flat($expected), $this->flat($tester->getDisplay()));
    }

    private function flat(string $text): string
    {
        return (string) preg_replace('/[\s!]+/', '', $text);
    }
}
