<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Functional;

use LogicException;
use Override;
use Phil\CommerceLogisticsBundle\PhilCommerceLogisticsBundle;
use Phil\CommerceLogisticsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The module registered in an application beside the core of commerce, and refused without it.
 */
#[CoversClass(PhilCommerceLogisticsBundle::class)]
final class BundleRegistrationTest extends KernelTestCase
{
    /**
     * Symfony's `ServicesBundle` wires the event dispatcher only when `ContainerBuilder::willBeAvailable()` says
     * so, and it does not when `symfony/event-dispatcher` is installed for development only while
     * `symfony/dependency-injection` is not — the bundle installed on its own. No kernel boots there, so the suite
     * runs from an application that requires an HTTP kernel, such as a monorepo root.
     */
    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testTheKernelBootsWithTheModuleBesideTheCore(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        $bundle = $kernel->getBundle('PhilCommerceLogisticsBundle');

        self::assertSame('Phil\\CommerceLogisticsBundle', $bundle->getNamespace());
        self::assertSame('phil_commerce_logistics', $bundle->getContainerExtension()?->getAlias());
        self::assertSame('phil_commerce', $kernel->getBundle('PhilCommerceBundle')->getContainerExtension()?->getAlias());

        $kernel->shutdown();
    }

    public function testTheModuleWithoutTheCoreFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, withCore: false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('register PhilCommerceBundle, from phil/commerce-bundle, too.');

        $kernel->boot();
    }
}
