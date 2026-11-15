<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Unit\Ffmpeg;

use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Exception\LimitExceeded;
use Phil\Audio\Ffmpeg\ProcessCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The runner on PHP itself, the one program every machine of the tests has.
 */
#[CoversClass(ProcessCommandRunner::class)]
final class ProcessCommandRunnerTest extends TestCase
{
    public function testTheArgumentsReachTheProgramAsTheyAre(): void
    {
        $result = new ProcessCommandRunner()->run([\PHP_BINARY, '-r', 'echo $argv[1]; fwrite(STDERR, "err"); exit(3);', '$(touch /nope); "it\'s"'], 10.0, 1_000);

        self::assertSame(3, $result->exitCode);
        self::assertSame('$(touch /nope); "it\'s"', $result->output);
        self::assertSame('err', $result->errorOutput);
    }

    public function testAnOutputWithinItsLimitIsKept(): void
    {
        $result = new ProcessCommandRunner()->run([\PHP_BINARY, '-r', 'echo str_repeat("a", 64);'], 10.0, 64);

        self::assertTrue($result->isSuccessful());
        self::assertSame(str_repeat('a', 64), $result->output);
    }

    public function testAProgramThatPrintsTooMuchIsStopped(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" printed more than 64 bytes and was stopped.', \PHP_BINARY));

        new ProcessCommandRunner()->run([\PHP_BINARY, '-r', 'echo str_repeat("a", 65); flush(); sleep(5);'], 10.0, 64);
    }

    public function testTheOutputIsCountedInAllItsPieces(): void
    {
        $this->expectException(LimitExceeded::class);

        new ProcessCommandRunner()->run([\PHP_BINARY, '-r', 'echo str_repeat("a", 40); flush(); usleep(300000); echo str_repeat("a", 40); flush(); sleep(5);'], 10.0, 64);
    }

    public function testAProgramThatRunsTooLongIsStopped(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" ran longer than 0.2 seconds and was stopped.', \PHP_BINARY));

        new ProcessCommandRunner()->run([\PHP_BINARY, '-r', 'sleep(5);'], 0.2, 64);
    }

    public function testAProgramThatCannotStartIsUnavailableOrNotFound(): void
    {
        try {
            $result = new ProcessCommandRunner()->run(['/nonexistent/phil-ffmpeg'], 10.0, 1_000);
            self::assertContains($result->exitCode, [126, 127]);
        } catch (AudioToolUnavailable $audioToolUnavailable) {
            self::assertStringStartsWith('"/nonexistent/phil-ffmpeg" cannot be started: ', $audioToolUnavailable->getMessage());
        }
    }
}
