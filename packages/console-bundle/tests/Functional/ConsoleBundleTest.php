<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Functional;

use Override;
use Phil\ConsoleBundle\PhilConsoleBundle;
use Phil\ConsoleBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * The bundle in a real kernel: a method-level command forbidden in the test environment, another that asks for a
 * confirmation and logs, the timestamps, the debug logs of a channel, the summary, and the lint command.
 */
#[CoversClass(PhilConsoleBundle::class)]
final class ConsoleBundleTest extends KernelTestCase
{
    private ApplicationTester $tester;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $this->tester = new ApplicationTester($application);
    }

    public function testAForbiddenCommandDoesNotRun(): void
    {
        self::assertSame(77, $this->tester->run(['command' => 'app:wipe'], ['capture_stderr_separately' => true]));
        self::assertSame('', $this->tester->getDisplay());
        self::assertStringContainsString('The command "app:wipe" cannot run in the "test" environment.', $this->tester->getErrorOutput());
    }

    public function testAConfirmedCommandWithItsTimestampsDebugLogsAndSummary(): void
    {
        $this->tester->setInputs(['test']);

        self::assertSame(0, $this->tester->run(['command' => 'app:anonymize', '--timestamps' => 'relative', '--debug-phil' => null], ['interactive' => true, 'capture_stderr_separately' => true]));
        self::assertMatchesRegularExpression('/^\[\+\d+\.\d{3}s\] anonymized\n$/', $this->tester->getDisplay());

        $errors = $this->tester->getErrorOutput();
        self::assertStringContainsString('Type "test" to continue:', $errors);
        self::assertMatchesRegularExpression('/\[\+\d+\.\d{3}s\] \[\S+\] app\.DEBUG: Anonymizing \{count\} customers \{"count":3\} \[\]\n/', $errors);
        self::assertMatchesRegularExpression('/\[\+\d+\.\d{3}s\] app:anonymize: exit code 0 in \d+\.\d{3} s, memory [+-][\d.]+ \w*B, peak [\d.]+ \w+\.\n$/', $errors);
    }

    public function testANonInteractiveRunIsForced(): void
    {
        self::assertSame(77, $this->tester->run(['command' => 'app:anonymize'], ['interactive' => false, 'capture_stderr_separately' => true]));
        self::assertSame(0, $this->tester->run(['command' => 'app:anonymize', '--force-environment' => true], ['interactive' => false]));
        self::assertStringContainsString('anonymized', $this->tester->getDisplay());
    }

    public function testTheLintCommand(): void
    {
        self::assertSame(0, $this->tester->run(['command' => 'lint:console-guards', '--format' => 'json']));

        $display = $this->tester->getDisplay();
        $json = substr($display, (int) strpos($display, '['), (int) strrpos($display, ']') - (int) strpos($display, '[') + 1);
        self::assertSame([
            ['command' => 'app:anonymize', 'forbidden' => [], 'confirmed' => ['test']],
            ['command' => 'app:wipe', 'forbidden' => ['test', 'prod'], 'confirmed' => []],
        ], json_decode($json, true, flags: \JSON_THROW_ON_ERROR));
    }
}
