<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Limits;
use Phil\EmailAddressBundle\Application\Bounces\BounceIngestor;
use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Application\Bounces\MailboxPoller;
use Phil\EmailAddressBundle\Application\Contract\BounceMailbox;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Application\MailboxMessage;
use Phil\EmailAddressBundle\Infrastructure\Console\PollMailboxCommand;
use Phil\EmailAddressBundle\Infrastructure\Console\ReadBounceCommand;
use Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository;
use Phil\EmailAddressBundle\Tests\Unit\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ReadBounceCommand::class)]
#[CoversClass(PollMailboxCommand::class)]
final class CommandsTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures/';

    public function testAFileIsReadAndItsDecisionsPrintedWithoutTheAddress(): void
    {
        $tester = $this->tester(new ReadBounceCommand($this->ingestor()));

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => self::FIXTURES.'postfix-hard.eml']));
        self::assertSame("suspend\n", $tester->getDisplay());
        self::assertStringNotContainsString('example.org', $tester->getDisplay());
    }

    public function testTheStandardInputIsReadUpToTheLimit(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, (string) file_get_contents(self::FIXTURES.'delayed.eml'));
        rewind($stream);
        $tester = $this->tester(new ReadBounceCommand($this->ingestor(), new Limits(), $stream));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("count\n", $tester->getDisplay());
        self::assertIsResource($stream);
    }

    public function testTheInputIsCutOneBytePastTheLimit(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, (string) file_get_contents(self::FIXTURES.'postfix-hard.eml'));
        rewind($stream);
        $tester = $this->tester(new ReadBounceCommand($this->ingestor(), new Limits(maxBytes: 10), $stream));

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => '-']));
        self::assertSame(11, ftell($stream));
    }

    public function testAnIgnoredMessageSaysSoWhenVerbose(): void
    {
        $tester = $this->tester(new ReadBounceCommand($this->ingestor()));

        $tester->execute(['file' => self::FIXTURES.'out-of-office.eml']);
        self::assertSame('', $tester->getDisplay());
        $tester->execute(['file' => self::FIXTURES.'out-of-office.eml'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        self::assertSame("ignored\n", $tester->getDisplay());
    }

    public function testAFileThatCannotBeReadFails(): void
    {
        $tester = $this->tester(new ReadBounceCommand($this->ingestor()));

        self::assertSame(Command::FAILURE, $tester->execute(['file' => self::FIXTURES.'missing.eml']));
        self::assertStringContainsString('[ERROR] The message', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['file' => self::FIXTURES]));
    }

    public function testTheMailboxIsPolled(): void
    {
        $mailbox = new class implements BounceMailbox {
            /** @var list<int> */
            public array $limits = [];

            #[Override]
            public function fetch(int $limit): iterable
            {
                $this->limits[] = $limit;

                yield new MailboxMessage('a', (string) file_get_contents(CommandsTest::fixture('postfix-hard.eml')));
                yield new MailboxMessage('b', (string) file_get_contents(CommandsTest::fixture('out-of-office.eml')));
            }

            #[Override]
            public function acknowledge(MailboxMessage $message): void
            {
            }
        };
        $tester = $this->tester(new PollMailboxCommand(new MailboxPoller($mailbox, $this->ingestor())));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("a: suspend\nb: ignored\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '5'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertSame("a: duplicate\nb: ignored\n2 message(s) read.\n", $tester->getDisplay());
        self::assertSame([50, 5], $mailbox->limits);
    }

    public function testTheLimitIsAtLeastOne(): void
    {
        $mailbox = new class implements BounceMailbox {
            #[Override]
            public function fetch(int $limit): iterable
            {
                return [];
            }

            #[Override]
            public function acknowledge(MailboxMessage $message): void
            {
            }
        };
        $tester = $this->tester(new PollMailboxCommand(new MailboxPoller($mailbox, $this->ingestor())));

        self::assertSame(Command::INVALID, $tester->execute(['--limit' => '0']));
        self::assertStringContainsString('The limit is at least 1.', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '1']));
    }

    public static function fixture(string $name): string
    {
        return self::FIXTURES.$name;
    }

    private function tester(callable $command): CommandTester
    {
        $application = new Application();
        $added = $application->addCommand($command);
        self::assertInstanceOf(Command::class, $added);

        return new CommandTester($added);
    }

    private function ingestor(): BounceIngestor
    {
        $clock = new MockClock('2026-11-20 10:00:00');

        return new BounceIngestor(
            new BounceParser(),
            new BounceNotices(new EmailAddresses('key'), clock: $clock),
            new BounceRules(new InMemoryBounceHistoryRepository($clock), new RecordingDispatcher(), $clock),
        );
    }
}
