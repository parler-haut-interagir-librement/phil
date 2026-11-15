<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Infrastructure\Console\OutstandingCommand;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OutstandingCommand::class)]
final class CommandTest extends TestCase
{
    public function testTheMembersWhoAreNotInOrderAreListed(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['policy' => 'terms']));
        self::assertSame("<b>\nc\n", $tester->getDisplay());
    }

    public function testTheMembersWhoHaveNotAcceptedTheLatestVersion(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['policy' => 'terms', '--latest' => true]));
        self::assertSame("a\n<b>\nc\n", $tester->getDisplay());
    }

    public function testTheirNumberOnly(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['policy' => 'terms', '--count' => true]));
        self::assertSame("2\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['policy' => 'privacy', '--count' => true]));
        self::assertSame("0\n", $tester->getDisplay());
    }

    public function testAnUndeclaredPolicyIsRefused(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['policy' => 'cookies']));
        self::assertSame("No policy \"cookies\" is declared.\n", $tester->getDisplay());
        self::assertSame(Command::INVALID, $tester->execute(['policy' => 'cookies', '--latest' => true], ['decorated' => true]));
        self::assertSame("\e[37;41mNo policy \"cookies\" is declared.\e[39;49m\n", $tester->getDisplay());
    }

    public function testTheCommandIsNamedAndDescribed(): void
    {
        $command = new OutstandingCommand(new Kit()->outstanding(), new Kit()->clock);

        self::assertSame('phil:terms:outstanding', $command->getName());
        self::assertTrue($command->getDefinition()->getArgument('policy')->isRequired());
        self::assertFalse($command->getDefinition()->getOption('latest')->acceptValue());
        self::assertFalse($command->getDefinition()->getOption('count')->acceptValue());
    }

    private function tester(): CommandTester
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->accept('a', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->publish('terms', ['en' => 'two'], effectiveAt: new DateTimeImmutable('2026-06-01'));

        return new CommandTester(new OutstandingCommand($kit->outstanding(['a', '<b>', 'c']), $kit->clock));
    }
}
