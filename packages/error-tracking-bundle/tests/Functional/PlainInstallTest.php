<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use Phil\ErrorTrackingBundle\Tests\Functional\Plain\PlainKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The bundle installed without the bundles it bridges to: the container compiles, the health check is left out when
 * phil/monitoring-bundle cannot be loaded or is not enabled, and the queued alerts are handled on the default bus only.
 */
#[CoversNothing]
final class PlainInstallTest extends TestCase
{
    private const int SECONDS = 120;

    public function testAPlainInstallCompilesWithoutTheMonitoringBundle(): void
    {
        $php = new PhpExecutableFinder()->find(false);
        self::assertIsString($php, 'No PHP binary.');
        $autoload = \dirname((string) new ReflectionClass(ClassLoader::class)->getFileName(), 2).'/autoload.php';
        $process = new Process([$php, __DIR__.'/Plain/boot.php', $autoload], null, null, null, self::SECONDS);
        $process->run();

        self::assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        self::assertSame(
            ['monitoring' => false, 'services' => ['health_check' => false, 'alert_buses' => ['command.bus']]],
            json_decode($process->getOutput(), true, 4, \JSON_THROW_ON_ERROR),
        );
    }

    public function testAPlainInstallLeavesTheCheckOutWhenTheMonitoringBundleIsInstalledButNotEnabled(): void
    {
        $kernel = new PlainKernel('plain', true);
        $kernel->boot();

        self::assertSame(
            ['health_check' => false, 'alert_buses' => ['command.bus']],
            $kernel->getContainer()->getParameter(PlainKernel::PARAMETER),
        );
        $kernel->shutdown();
    }
}
