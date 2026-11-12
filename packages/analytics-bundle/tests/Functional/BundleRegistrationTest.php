<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Functional;

use Phil\AnalyticsBundle\Application\Collection\Collector;
use Phil\AnalyticsBundle\Application\Contract\CounterStore;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsConfiguration;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsServices;
use Phil\AnalyticsBundle\PhilAnalyticsBundle;
use Phil\AnalyticsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The bundle registered in an application: its extension is known under `phil_analytics`, its reference lists its
 * keys, an unknown key fails the container build, and without DoctrineBundle nothing that counts is wired.
 */
#[CoversClass(PhilAnalyticsBundle::class)]
#[CoversClass(AnalyticsConfiguration::class)]
#[CoversClass(AnalyticsServices::class)]
final class BundleRegistrationTest extends FunctionalTestCase
{
    public function testTheKernelBootsWithTheBundleUnderItsAlias(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $bundle = $kernel->getBundle('PhilAnalyticsBundle');

        self::assertSame('Phil\\AnalyticsBundle', $bundle->getNamespace());
        self::assertSame('phil_analytics', $bundle->getContainerExtension()?->getAlias());
    }

    public function testTheConfigurationReferenceListsTheAdapters(): void
    {
        $tester = $this->command('config:dump-reference');

        self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'phil_analytics']));

        foreach (['small_number_threshold: 5', 'store: +cache', 'store: +dbal', 'limiter: +symfony', 'projection: +config', "time: +'05:00'"] as $line) {
            self::assertMatchesRegularExpression('/^ +'.$line.'$/m', $tester->getDisplay());
        }
    }

    public function testAnUnknownKeyFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, ['foo' => true]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "foo" under "phil_analytics".');

        $kernel->boot();
    }

    /**
     * Without DoctrineBundle, the default store has no connection: the bundle boots, and the endpoint and the jobs wait
     * for the application to name a store.
     */
    public function testWithoutDoctrineNothingThatCountsIsWired(): void
    {
        $kernel = new TestKernel('test', true, [], false);
        $kernel->boot();

        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        self::assertFalse($container->has(CounterStore::class));
        self::assertFalse($container->has(Collector::class));
        $kernel->shutdown();
    }
}
