<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Functional;

use Override;
use Phil\AnalyticsBundle\Tests\Functional\App\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The bundle in a kernel of its own (`App\TestKernel`), booted for each test with the `phil_analytics` block of
 * {@see self::analyticsConfig()}.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, static::analyticsConfig(), static::withDoctrine());
    }

    /**
     * The `phil_analytics` block of the test's kernel.
     *
     * @return array<string, mixed>
     */
    protected static function analyticsConfig(): array
    {
        return [];
    }

    protected static function withDoctrine(): bool
    {
        return true;
    }

    #[Override]
    protected function setUp(): void
    {
        self::skipWithoutEventDispatcher();
        self::bootKernel();
    }

    /**
     * Symfony's `ServicesBundle` wires the event dispatcher only when `ContainerBuilder::willBeAvailable()` says
     * so, and it does not when `symfony/event-dispatcher` is installed for development only while
     * `symfony/dependency-injection` is not — the bundle installed on its own, with FrameworkBundle in its
     * `require-dev`. No kernel boots there, so the suite runs from an application that requires an HTTP kernel,
     * such as a monorepo root.
     */
    public static function skipWithoutEventDispatcher(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    /**
     * A service of the test kernel's container.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    protected function command(string $name): CommandTester
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        return new CommandTester(new Application($kernel)->find($name));
    }
}
