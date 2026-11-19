<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The tests of the Stimulus controller and its helpers, under Node's own test runner, run with the suites of the
 * package so that `composer test` fails when they fail. Without Node on the machine, the test is skipped and says so.
 */
#[CoversNothing]
final class JavaScriptTest extends TestCase
{
    private const int SECONDS = 60;

    public function testTheControllerOfTheBrowsersErrorsPassesItsTests(): void
    {
        $node = new ExecutableFinder()->find('node');

        if (null === $node) {
            self::markTestSkipped('Node is not installed: the tests of tests/js need it.');
        }

        $package = \dirname(__DIR__, 2);
        $process = new Process([$node, '--import', './tests/js/register.mjs', '--test', '--test-reporter=tap', 'tests/js/'], $package, null, null, self::SECONDS);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
        self::assertMatchesRegularExpression('/^# pass [1-9]\d*$/m', $process->getOutput());
        self::assertMatchesRegularExpression('/^# fail 0$/m', $process->getOutput());
    }
}
