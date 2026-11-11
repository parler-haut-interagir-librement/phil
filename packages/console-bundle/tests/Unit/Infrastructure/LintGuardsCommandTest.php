<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Phil\ConsoleBundle\Infrastructure\Command\LintGuardsCommand;
use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ClassicCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ConfirmedCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ForbiddenCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(LintGuardsCommand::class)]
final class LintGuardsCommandTest extends TestCase
{
    public function testTheGuardedCommandsAsATable(): void
    {
        $tester = $this->tester(new Command('test:forbidden', new ForbiddenCommand()), new Command('test:confirmed', new ConfirmedCommand()), new ClassicCommand());

        self::assertSame(0, $tester->execute([]));
        self::assertSame(<<<'TXT'
             ---------------- --------------- -----------------
              Command          Forbidden in    Confirmation in
             ---------------- --------------- -----------------
              test:classic                     *
              test:confirmed   staging, dev    prod
              test:forbidden   prod, staging
             ---------------- --------------- -----------------


            TXT, $this->trimmed($tester->getDisplay()));
    }

    public function testTheGuardedCommandsAsJson(): void
    {
        $regional = new #[ForbidEnvironment('eu/prod')] class {
            public function __invoke(): int
            {
                return 0;
            }
        };
        $tester = $this->tester(new Command('test:forbidden', new ForbiddenCommand()), new ClassicCommand(), new Command('test:regional')->setCode($regional));

        self::assertSame(0, $tester->execute(['--format' => 'json']));
        self::assertSame([
            ['command' => 'test:classic', 'forbidden' => [], 'confirmed' => ['*']],
            ['command' => 'test:forbidden', 'forbidden' => ['prod', 'staging'], 'confirmed' => []],
            ['command' => 'test:regional', 'forbidden' => ['eu/prod'], 'confirmed' => []],
        ], json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertStringContainsString("\n    {\n        \"command\": \"test:classic\",", $tester->getDisplay());
        self::assertStringContainsString('"eu/prod"', $tester->getDisplay());
    }

    public function testNoGuardedCommand(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->execute([]));
        self::assertSame("\n [INFO] No command is guarded.\n\n", $this->trimmed($tester->getDisplay()));
        self::assertSame(0, $tester->execute(['--format' => 'json']));
        self::assertSame("[]\n", $tester->getDisplay());
    }

    public function testAConfirmationWhereTheCommandIsForbiddenFails(): void
    {
        $contradiction = new #[ForbidEnvironment('prod', 'dev')] #[ConfirmEnvironment('prod', 'test')] class {
            public function __invoke(): int
            {
                return 0;
            }
        };
        $everywhere = new #[ForbidEnvironment('dev')] #[ConfirmEnvironment] class {
            public function __invoke(): int
            {
                return 0;
            }
        };
        $tester = $this->tester(new Command('test:both')->setCode($contradiction), new Command('test:all')->setCode($everywhere));

        self::assertSame(1, $tester->execute([], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('test:both', $tester->getDisplay());
        $errors = (string) preg_replace('/\s+/', ' ', $tester->getErrorOutput());
        self::assertStringContainsString('The command "test:all" asks for a confirmation in "dev", where it is forbidden.', $errors);
        self::assertStringContainsString('The command "test:both" asks for a confirmation in "prod", where it is forbidden.', $errors);
    }

    public function testAnUnknownFormatFails(): void
    {
        $this->expectExceptionMessageIsOrContains('The format "xml" is not supported: use "txt" or "json".');

        $this->tester()->execute(['--format' => 'xml']);
    }

    public function testACommandWithoutApplicationListsNothing(): void
    {
        $tester = new CommandTester(new Command(null, new LintGuardsCommand(new CommandGuardReader())));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('No command is guarded.', $tester->getDisplay());
    }

    private function trimmed(string $display): string
    {
        return (string) preg_replace('/ +$/m', '', $display);
    }

    private function tester(Command ...$commands): CommandTester
    {
        $application = new Application();
        $application->addCommands($commands);

        $lint = $application->addCommand(new Command(null, new LintGuardsCommand(new CommandGuardReader())));
        self::assertNotNull($lint);
        self::assertSame('lint:console-guards', $lint->getName());

        return new CommandTester($lint);
    }
}
