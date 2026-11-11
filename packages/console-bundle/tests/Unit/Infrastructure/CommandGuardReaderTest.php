<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Infrastructure;

use Phil\ConsoleBundle\Infrastructure\Console\CommandGuardReader;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ClassicCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ConfirmedCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\ForbiddenCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\InvokableClassCommand;
use Phil\ConsoleBundle\Tests\Unit\Fixture\MethodCommands;
use Phil\ConsoleBundle\Tests\Unit\Fixture\TraceableCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Stopwatch\Stopwatch;

#[CoversClass(CommandGuardReader::class)]
final class CommandGuardReaderTest extends TestCase
{
    public function testAnInvokableServiceWrappedByTheContainer(): void
    {
        $guards = new CommandGuardReader()->guardsOf(new Command('test:confirmed', new ConfirmedCommand()->__invoke(...)));

        self::assertSame(['staging', 'dev'], $guards->forbidden);
        self::assertSame(['prod'], $guards->confirmed);
    }

    public function testAnInvokableObject(): void
    {
        $guards = new CommandGuardReader()->guardsOf(new Command('test:confirmed', new ConfirmedCommand()));

        self::assertSame(['staging', 'dev'], $guards->forbidden);
        self::assertSame(['prod'], $guards->confirmed);
    }

    public function testAMethodLevelCommand(): void
    {
        $reader = new CommandGuardReader();

        $reset = $reader->guardsOf(new Command('test:reset', new MethodCommands()->reset(...)));
        self::assertSame(['prod'], $reset->forbidden);
        self::assertSame(['test'], $reset->confirmed);

        $callable = [new MethodCommands()];
        $callable[] = 'log';
        self::assertIsCallable($callable);
        $log = $reader->guardsOf(new Command('test:log', $callable));
        self::assertSame([], $log->forbidden);
        self::assertSame(['test'], $log->confirmed);
    }

    public function testAClassicCommandIsItsOwnClass(): void
    {
        $guards = new CommandGuardReader()->guardsOf(new ClassicCommand());

        self::assertTrue($guards->confirmEvery);
        self::assertSame([], $guards->forbidden);
    }

    public function testACommandClassWithInvoke(): void
    {
        self::assertSame(['prod'], new CommandGuardReader()->guardsOf(new InvokableClassCommand())->forbidden);
    }

    public function testAClosureIsTheCommandsClass(): void
    {
        $reader = new CommandGuardReader();

        self::assertTrue($reader->guardsOf(new Command('a')->setCode(static fn (): int => 0))->isEmpty());
        self::assertTrue($reader->guardsOf(new Command('a')->setCode(fn (): int => \count([$this]) - 1))->isEmpty());
        self::assertTrue($reader->guardsOf(new ClassicCommand()->setCode(static fn (): int => 0))->confirmEvery);
    }

    public function testLazyAndTracedCommandsAreUnwrapped(): void
    {
        $command = new Command('test:forbidden', new ForbiddenCommand());
        $lazy = new LazyCommand('test:forbidden', [], '', false, static fn (): Command => $command);
        $reader = new CommandGuardReader();

        self::assertSame(['prod', 'staging'], $reader->guardsOf($lazy)->forbidden);
        self::assertSame(['prod', 'staging'], $reader->guardsOf(self::traced($command))->forbidden);
        self::assertSame(['prod', 'staging'], $reader->guardsOf(new LazyCommand('test:forbidden', [], '', false, static fn (): Command => self::traced($command)))->forbidden);
    }

    public function testACommandWithAPublicCommandIsNotUnwrapped(): void
    {
        $wrapper = new class(new Command('test:forbidden', new ForbiddenCommand())) extends Command {
            public function __construct(
                public readonly Command $command,
            ) {
                parent::__construct('test:wrapper');
            }
        };

        self::assertTrue(new CommandGuardReader()->guardsOf($wrapper)->isEmpty());

        self::assertTrue(new CommandGuardReader()->guardsOf(new TraceableCommand(new Command('test:forbidden', new ForbiddenCommand())))->isEmpty());
    }

    /**
     * FrameworkBundle's traced command, internal to Symfony, built the way FrameworkBundle builds it.
     */
    private static function traced(Command $command): Command
    {
        $class = new ReflectionClass(Command::class)->getNamespaceName().'\\TraceableCommand';
        self::assertTrue(class_exists($class));
        $traced = new ReflectionClass($class)->newInstance($command, new Stopwatch());
        self::assertInstanceOf(Command::class, $traced);

        return $traced;
    }
}
