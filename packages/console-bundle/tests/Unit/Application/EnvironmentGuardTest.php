<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Application;

use Phil\ConsoleBundle\Application\EnvironmentGuard;
use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Phil\ConsoleBundle\Domain\Exception\EnvironmentRefused;
use Phil\ConsoleBundle\Domain\ValueObject\EnvironmentGuards;
use Phil\ConsoleBundle\Tests\Unit\Fixture\MemoryConsoleOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(EnvironmentGuard::class)]
final class EnvironmentGuardTest extends TestCase
{
    public function testAnUnguardedCommandRuns(): void
    {
        $output = new BufferedOutput();
        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ForbidEnvironment('dev')]), $this->input(answer: 'no'), $output);

        self::assertSame('', $output->fetch());
    }

    public function testAForbiddenEnvironmentRefusesEvenWhenForced(): void
    {
        $this->expectExceptionObject(EnvironmentRefused::forbidden('app:a', 'prod'));

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ForbidEnvironment('prod'), new ConfirmEnvironment()]), $this->input(force: true), new BufferedOutput());
    }

    public function testTheEnvironmentNameConfirms(): void
    {
        $output = new BufferedOutput();
        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment('prod')]), $this->input(answer: "  prod \n"), $output);

        self::assertSame('The command "app:a" runs in the "prod" environment. Type "prod" to continue: ', $output->fetch());
    }

    public function testAnotherAnswerRefuses(): void
    {
        $this->expectExceptionObject(EnvironmentRefused::notConfirmed('app:a', 'prod'));

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $this->input(answer: "yes\n"), new BufferedOutput());
    }

    public function testAnEmptyAnswerRefuses(): void
    {
        $this->expectExceptionObject(EnvironmentRefused::notConfirmed('app:a', 'prod'));

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $this->input(answer: "\n"), new BufferedOutput());
    }

    public function testNoAnswerRefuses(): void
    {
        $this->expectExceptionObject(EnvironmentRefused::notConfirmed('app:a', 'prod'));

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $this->input(answer: ''), new BufferedOutput());
    }

    public function testANonInteractiveRunRefuses(): void
    {
        $input = $this->input(answer: "prod\n");
        $input->setInteractive(false);

        $this->expectExceptionObject(EnvironmentRefused::notInteractive('app:a', 'prod'));

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $input, new BufferedOutput());
    }

    public function testTheForcingOptionSkipsTheQuestionOnTheErrorOutput(): void
    {
        $input = $this->input(force: true);
        $input->setInteractive(false);

        $output = new MemoryConsoleOutput();

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $input, $output);

        self::assertSame('', $output->display());
        self::assertSame("The confirmation of the \"prod\" environment is skipped by --force-environment.\n", $output->errors());
    }

    public function testTheForcingOptionCountsOnlyWhenGiven(): void
    {
        $input = $this->input(force: false);
        $input->setInteractive(false);

        $this->expectExceptionObject(EnvironmentRefused::notInteractive('app:a', 'prod'));

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $input, new BufferedOutput());
    }

    public function testAnInputWithoutTheForcingOptionAsks(): void
    {
        $input = new ArrayInput([]);
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, "prod\n");
        rewind($stream);
        $input->setStream($stream);
        $output = new BufferedOutput();

        new EnvironmentGuard('prod')->check('app:a', EnvironmentGuards::of([new ConfirmEnvironment()]), $input, $output);

        self::assertStringContainsString('Type "prod"', $output->fetch());
    }

    private function input(bool $force = false, ?string $answer = null): ArrayInput
    {
        $input = new ArrayInput($force ? ['--force-environment' => true] : [], new InputDefinition([
            new InputOption('force-environment', null, InputOption::VALUE_NONE),
        ]));

        if (null !== $answer) {
            $stream = fopen('php://memory', 'w+');
            self::assertIsResource($stream);
            fwrite($stream, $answer);
            rewind($stream);
            $input->setStream($stream);
        }

        return $input;
    }
}
