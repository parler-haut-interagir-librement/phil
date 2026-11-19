<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTrackingBundle\Application\AlertDestination;
use Phil\ErrorTrackingBundle\Infrastructure\Console\StatusCommand;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\MemoryStore;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(StatusCommand::class)]
final class StatusCommandTest extends TestCase
{
    public function testTheCapturePointsTheStoreAndTheAlerts(): void
    {
        $store = new MemoryStore();
        $store->save(new ErrorGroup(str_repeat('a', 64), 'E', null, GroupStatus::Open, 1, new DateTimeImmutable(), new DateTimeImmutable()));
        $store->save(new ErrorGroup(str_repeat('b', 64), 'F', null, GroupStatus::Resolved, 1, new DateTimeImmutable(), new DateTimeImmutable()));

        $tester = new CommandTester(new StatusCommand($store, AlertDestination::Chat, ['requests' => true, 'commands' => false]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(
            "Capture of requests: on.\nCapture of commands: off.\nStore: answers, 2 group(s) kept.\nAlerts: The alerts are sent as chat messages through the notifier's chatter.\n",
            $tester->getDisplay(),
        );
    }

    public function testAlertsWithoutADestinationAreAWarningAndTheCommandStillSucceeds(): void
    {
        $tester = new CommandTester(new StatusCommand(new MemoryStore(), AlertDestination::None, []));

        self::assertSame(Command::SUCCESS, $tester->execute([], ['decorated' => true]));
        self::assertStringContainsString("\e[33mAlerts: degraded. ".AlertDestination::None->describe()."\e[39m", $tester->getDisplay());
        self::assertStringContainsString('Store: answers, 0 group(s) kept.', $tester->getDisplay());
    }

    public function testAStoreThatDoesNotAnswerIsAFailureThatNamesItsClassOnly(): void
    {
        $tester = new CommandTester(new StatusCommand(new RecordingReader(new RuntimeException('SQLSTATE: db.internal refused jane')), AlertDestination::Disabled, []));

        self::assertSame(Command::FAILURE, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_NORMAL]));
        self::assertSame("Store: does not answer (RuntimeException).\nAlerts: The alerts are turned off.\n", $tester->getDisplay());
    }

    public function testTheCommandIsNamed(): void
    {
        self::assertSame('phil:error-tracking:status', new StatusCommand(new MemoryStore(), AlertDestination::Chat, [])->getName());
    }
}
