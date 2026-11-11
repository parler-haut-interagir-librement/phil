<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Unit;

use Phil\Sitemap\UrlProvider;
use Phil\SitemapBundle\Infrastructure\Console\DumpSitemapsCommand;
use Phil\SitemapBundle\Infrastructure\DependencyInjection\SitemapServices;
use Phil\SitemapBundle\Infrastructure\Http\SitemapController;
use Phil\SitemapBundle\Infrastructure\Routing\RouteUrlProvider;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use Phil\SitemapBundle\PhilSitemapBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSitemapBundle::class)]
#[CoversClass(SitemapServices::class)]
final class PhilSitemapBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_sitemap', $this->extension()->getAlias());
        self::assertSame(['service_container', RouteUrlProvider::class, Sitemaps::class, SitemapController::class, DumpSitemapsCommand::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('router'), new Reference('clock'), 'default', ['priority' => null, 'changefreq' => null, 'lastmod' => null]], $container->getDefinition(RouteUrlProvider::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(RouteUrlProvider::class)->getTag(SitemapServices::PROVIDER_TAG));
        self::assertEquals([new TaggedIteratorArgument(SitemapServices::PROVIDER_TAG), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Sitemaps::class)->getArguments());
        self::assertTrue($container->getDefinition(Sitemaps::class)->isPublic());
        self::assertEquals([new Reference(Sitemaps::class), new Reference('router'), 50000, 3600], $container->getDefinition(SitemapController::class)->getArguments());
        self::assertTrue($container->getDefinition(SitemapController::class)->isPublic());
        self::assertTrue($container->getDefinition(SitemapController::class)->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference(Sitemaps::class), new Reference('router.request_context'), '%kernel.project_dir%/public', 50000], $container->getDefinition(DumpSitemapsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(DumpSitemapsCommand::class)->hasTag('console.command'));
        self::assertSame([['after' => RouteUrlProvider::class]], $container->getAutoconfiguredInstanceof()[UrlProvider::class]->getTag(SitemapServices::PROVIDER_TAG));
        self::assertSame(['phil_sitemap.url_provider', 50000, ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never']], [SitemapServices::PROVIDER_TAG, SitemapServices::MAX_PER_FILE, SitemapServices::CHANGE_FREQUENCIES]);
    }

    public function testEveryOption(): void
    {
        $container = $this->load([
            'default_section' => 'main',
            'items_by_set' => 1,
            'dump_directory' => '/var/www/public',
            'cache_max_age' => 0,
            'defaults' => ['priority' => 1.0, 'changefreq' => 'daily', 'lastmod' => 'now'],
        ]);
        self::assertSame(0, $container->getDefinition(SitemapController::class)->getArgument(3));
        self::assertSame(['priority' => 0.7, 'lastmod' => '2026-11-01', 'changefreq' => null], $this->load(['defaults' => ['priority' => 0.7, 'lastmod' => '2026-11-01']])->getDefinition(RouteUrlProvider::class)->getArgument(3));

        self::assertEquals([new Reference('router'), new Reference('clock'), 'main', ['priority' => 1.0, 'changefreq' => 'daily', 'lastmod' => 'now']], $container->getDefinition(RouteUrlProvider::class)->getArguments());
        self::assertSame([1, '/var/www/public'], [$container->getDefinition(SitemapController::class)->getArgument(2), $container->getDefinition(DumpSitemapsCommand::class)->getArgument(2)]);
        self::assertSame(1, $container->getDefinition(DumpSitemapsCommand::class)->getArgument(3));
        $defaults = $this->load(['defaults' => ['priority' => 0.0]])->getDefinition(RouteUrlProvider::class)->getArgument(3);
        self::assertSame([['priority' => 0.0, 'changefreq' => null, 'lastmod' => null], 50000], [$defaults, $this->load(['items_by_set' => 50000])->getDefinition(SitemapController::class)->getArgument(2)]);
        self::assertFalse($this->load(['route_options' => false])->hasDefinition(RouteUrlProvider::class));
        self::assertSame(['changefreq' => null, 'priority' => null, 'lastmod' => null], $this->load(['defaults' => ['changefreq' => null]])->getDefinition(RouteUrlProvider::class)->getArgument(3));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'no default section' => [['default_section' => ''], 'default_section'];
        yield 'no file' => [['items_by_set' => 0], 'items_by_set'];
        yield 'too many pages' => [['items_by_set' => 50001], 'items_by_set'];
        yield 'no directory' => [['dump_directory' => ''], 'dump_directory'];
        yield 'a low priority' => [['defaults' => ['priority' => -0.1]], 'priority'];
        yield 'a high priority' => [['defaults' => ['priority' => 1.1]], 'priority'];
        yield 'an unknown changefreq' => [['defaults' => ['changefreq' => 'often']], 'changefreq'];
        yield 'a priority between steps' => [['defaults' => ['priority' => 0.85]], 'A priority is 0.0 to 1.0, in steps of 0.1; 0.85 is not.'];
        yield 'a lastmod that is no date' => [['defaults' => ['lastmod' => 'someday']], 'A lastmod is "now" or a date; "someday" is not.'];
        yield 'a lastmod that is no string' => [['defaults' => ['lastmod' => true]], 'lastmod'];
        yield 'a negative cache age' => [['cache_max_age' => -1], 'cache_max_age'];
        yield 'a section named like a part' => [['default_section' => 'main_1'], '"main_1" is not a section'];
        yield 'a section with capitals' => [['default_section' => 'Main'], '"Main" is not a section'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilSitemapBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
