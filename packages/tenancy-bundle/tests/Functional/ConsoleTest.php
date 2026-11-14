<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Console\TenantOption;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A command of the application run through the console: in the tenant `--tenant` names, or in none.
 */
#[CoversClass(TenantOption::class)]
final class ConsoleTest extends FunctionalTestCase
{
    public function testTheCommandRunsInTheTenantOfItsOption(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->run(['command' => 'app:invoices', '--tenant' => 'retail']));
        self::assertSame("retail\nR-1\nR-2\n", $tester->getDisplay(true));
        self::assertNull($this->service(TenantContext::class, TenantContext::class)->current());
    }

    public function testWithoutTheOptionTheCommandRunsInNoTenant(): void
    {
        $tester = $this->tester();

        self::assertSame(0, $tester->run(['command' => 'app:invoices']));
        self::assertSame("(none)\n", $tester->getDisplay(true));
    }

    public function testAMalformedTenantStopsTheCommand(): void
    {
        $tester = $this->tester();
        $tester->run(['command' => 'app:invoices', '--tenant' => str_repeat('x', 256)], ['capture_stderr_separately' => true]);

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('(none)', $tester->getDisplay(true));
    }

    private function tester(): ApplicationTester
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
