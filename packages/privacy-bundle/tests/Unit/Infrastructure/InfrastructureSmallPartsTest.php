<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\Contract\DataSubjectUser;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Message\ProcessDueAccountDeletions;
use Phil\PrivacyBundle\Application\Message\RunRetention;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Console\DisabledRightsWarning;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessDeletionsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\RunRetentionCommand;
use Phil\PrivacyBundle\Infrastructure\Log\LoggedConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Scheduler\PrivacySchedule;
use Phil\PrivacyBundle\Infrastructure\Security\SecurityCurrentDataSubject;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\FixedScheduleSource;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryAccountDeletions;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\MemoryExportArchive;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingEraser;
use Phil\PrivacyBundle\Tests\Unit\Application\Fixture\RecordingPurger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(LoggedConsentProofs::class)]
#[CoversClass(SecurityCurrentDataSubject::class)]
#[CoversClass(MisconfiguredPrivacy::class)]
#[CoversClass(DisabledRightsWarning::class)]
#[CoversClass(RunRetentionCommand::class)]
#[CoversClass(ProcessDeletionsCommand::class)]
#[CoversClass(PrivacySchedule::class)]
final class InfrastructureSmallPartsTest extends TestCase
{
    public function testAProofInTheLogNamesNoOne(): void
    {
        $log = new TestHandler();
        new LoggedConsentProofs(new Logger('privacy', [$log]))->record(new ConsentChoice(str_repeat('a', 32), '2', ['analytics'], new DateTimeImmutable('2026-01-01T00:00:00Z'), true));

        self::assertSame('Consent {id} recorded on version {version}.', $log->getRecords()[0]->message);
        self::assertSame(['id' => str_repeat('a', 32), 'version' => '2', 'granted' => ['analytics'], 'global_privacy_control' => true, 'decided_at' => '2026-01-01T00:00:00+00:00'], $log->getRecords()[0]->context);
    }

    public function testNobodySignedInIsNull(): void
    {
        self::assertNull(new SecurityCurrentDataSubject(new TokenStorage())->current());
    }

    public function testTheUserSaysWhichIdentifierDesignatesThem(): void
    {
        $user = new class implements UserInterface, DataSubjectUser {
            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function getUserIdentifier(): string
            {
                return 'someone@example.org';
            }

            public function dataSubjectIdentifier(): string
            {
                return 'stable-1';
            }
        };
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main'));

        self::assertSame('stable-1', new SecurityCurrentDataSubject($tokens)->current()?->value);
    }

    public function testAUserWithoutAStableIdentifierIsRefusedRatherThanReadFromItsEmail(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('someone@example.org', null), 'main'));

        $this->expectException(MisconfiguredPrivacy::class);
        $this->expectExceptionMessageIsOrContains('does not implement DataSubjectUser');

        new SecurityCurrentDataSubject($tokens)->current();
    }

    public function testEveryCommandIsWarnedOnTheErrorOutput(): void
    {
        $output = new ConsoleOutput();
        $error = new BufferedOutput();
        $output->setErrorOutput(new class($error) extends Output {
            public function __construct(private readonly BufferedOutput $inner)
            {
                parent::__construct();
            }

            protected function doWrite(string $message, bool $newline): void
            {
                $this->inner->write($message, $newline, self::OUTPUT_RAW);
            }
        });

        new DisabledRightsWarning(new PrivacyRights(erasure: false))(new ConsoleCommandEvent(new Command('x'), new ArrayInput([]), $output));

        self::assertStringContainsString('DANGER: phil_privacy.rights.erasure is DISABLED', $error->fetch());
    }

    public function testAPlainOutputIsWarnedToo(): void
    {
        $output = new BufferedOutput();
        new DisabledRightsWarning(new PrivacyRights(export: false))(new ConsoleCommandEvent(new Command('x'), new ArrayInput([]), $output));

        self::assertSame("\n DANGER: ".new PrivacyRights(export: false)->warnings()[0]." \n\n", $output->fetch());
    }

    public function testTheRetentionCommandOnlyListsWithoutConfirm(): void
    {
        $purger = new RecordingPurger('trash', purged: 2);
        $tester = new CommandTester(new RunRetentionCommand($this->job([$purger])));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame([], $purger->orders);
        $display = $tester->getDisplay();
        self::assertStringContainsString('R-1: trash, before 2026-03-01T00:00:00Z', $display);
        self::assertStringContainsString('R-2: no cutoff', $display);
        self::assertStringContainsString('Nothing was purged', $display);
    }

    public function testTheRetentionCommandPurgesOnConfirm(): void
    {
        $purger = new RecordingPurger('trash', purged: 2);
        $tester = new CommandTester(new RunRetentionCommand($this->job([$purger])));

        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true]));
        self::assertCount(1, $purger->orders);
        self::assertStringContainsString('R-1: 2 purged', $tester->getDisplay());
        self::assertStringNotContainsString('Nothing was purged', $tester->getDisplay());
    }

    public function testTheRetentionCommandFailsOnAJobWithoutPurger(): void
    {
        $tester = new CommandTester(new RunRetentionCommand($this->job([])));

        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertStringContainsString('No retention purger answers for the store "trash"', $tester->getDisplay());
    }

    public function testTheRetentionCommandSaysWhenAnotherRunIsInProgress(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $locks->createLock(RetentionJob::LOCK, autoRelease: false)->acquire();
        $tester = new CommandTester(new RunRetentionCommand($this->job([new RecordingPurger('trash')], $locks)));

        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertStringContainsString('Another retention run is in progress.', $tester->getDisplay());
    }

    public function testTheDeletionsCommandRunsWhatIsDueAndNamesRequestsOnly(): void
    {
        $clock = new MockClock('2026-01-01T00:00:00Z');
        $archive = new MemoryExportArchive();
        $archive->put('old', 'x', new DateTimeImmutable('2025-01-01T00:00:00Z'));

        $deletions = $this->deletions(new RecordingEraser('members'), $clock, $archive);
        $request = $deletions->request(DataSubject::fromString('member-1'));
        $clock->modify('+30 days');
        $tester = new CommandTester(new ProcessDeletionsCommand($deletions));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame($request->id.": erased\n", $tester->getDisplay());
        self::assertSame([$request->id], array_keys($archive->files));
    }

    public function testTheDeletionsCommandFailsAndGoesOnWhenAnErasureFails(): void
    {
        $clock = new MockClock('2026-01-01T00:00:00Z');
        $deletions = $this->deletions(new RecordingEraser('members', fails: true), $clock, new MemoryExportArchive());
        $one = $deletions->request(DataSubject::fromString('member-1'));
        $two = $deletions->request(DataSubject::fromString('member-2'));
        $clock->modify('+30 days');
        $tester = new CommandTester(new ProcessDeletionsCommand($deletions));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString($one->id.': failed in members', $tester->getDisplay());
        self::assertStringContainsString($two->id.': failed in members', $tester->getDisplay());
        self::assertStringNotContainsString('member-1', $tester->getDisplay());
    }

    public function testTheScheduleHoldsBothMessages(): void
    {
        $schedule = new PrivacySchedule('15 minutes', '1 day');
        $messages = array_values($schedule->getSchedule()->getRecurringMessages());

        self::assertSame($schedule->getSchedule(), $schedule->getSchedule());
        self::assertCount(2, $messages);
        self::assertInstanceOf(ProcessDueAccountDeletions::class, iterator_to_array($messages[0]->getMessages(new MessageContext('x', 'y', $messages[0]->getTrigger(), new DateTimeImmutable())))[0]);
        self::assertInstanceOf(RunRetention::class, iterator_to_array($messages[1]->getMessages(new MessageContext('x', 'y', $messages[1]->getTrigger(), new DateTimeImmutable())))[0]);
    }

    public function testTheScheduleLeavesOutWhatHasNoFrequency(): void
    {
        self::assertCount(1, new PrivacySchedule('15 minutes', null)->getSchedule()->getRecurringMessages());
        self::assertCount(1, new PrivacySchedule(null, '1 day')->getSchedule()->getRecurringMessages());
    }

    private function deletions(RecordingEraser $eraser, MockClock $clock, MemoryExportArchive $archive): AccountDeletions
    {
        return new AccountDeletions(new MemoryAccountDeletions(), DeletionWorkflow::create(), new LockFactory(new InMemoryStore()), new SubjectExport([]), new SubjectErasure([$eraser]), $archive, $clock, 'P30D', 'P7D');
    }

    /**
     * @param list<RecordingPurger> $purgers
     */
    private function job(array $purgers, LockFactory $locks = new LockFactory(new InMemoryStore())): RetentionJob
    {
        $run = new RetentionRun(new FixedScheduleSource(new RetentionSchedule([
            new RetentionRule('R-1', 'trash', [], RetentionPeriod::fixed('P30D'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-2', 'accounts', [], RetentionPeriod::untilEvent('closed'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
        ])), $purgers);

        return new RetentionJob($run, $locks, new MockClock('2026-03-31T00:00:00Z'));
    }
}
