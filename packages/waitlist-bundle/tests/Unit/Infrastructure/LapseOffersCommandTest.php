<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Unit\Infrastructure;

use Phil\WaitlistBundle\Application\Policy\FirstInLine;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WaitlistBundle\Infrastructure\Console\LapseOffersCommand;
use Phil\WaitlistBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\WaitlistBundle\Tests\Unit\Fixture\InMemoryEntries;
use Phil\WaitlistBundle\Tests\Unit\Fixture\InMemoryWaitlists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LapseOffersCommand::class)]
final class LapseOffersCommandTest extends TestCase
{
    public function testTheCommandLapsesTheOffersWhoseDeadlineHasCome(): void
    {
        $clock = new FrozenClock();
        $lists = new InMemoryWaitlists();
        $waitlists = new Waitlists($lists, new InMemoryEntries($lists), new FirstInLine(), $clock, null, 600);
        $waitlist = $waitlists->open('session', 'S-1');
        $entry = $waitlists->join($waitlist->id, 'account-1');
        $waitlists->release($waitlist->id, 1);
        $clock->move('+10 minutes');

        $application = new Application();
        $application->addCommand(new LapseOffersCommand($waitlists));

        $tester = new CommandTester($application->find('phil:waitlist:lapse'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 offers lapsed.', $tester->getDisplay());
        self::assertSame(EntryStatus::Lapsed, $entry->status());
        self::assertSame(LapseOffersCommand::NAME, $application->find('phil:waitlist:lapse')->getName());
    }
}
