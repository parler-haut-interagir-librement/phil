<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Unit;

use Phil\VideoBundle\Application\Contract\ViewingConsent;
use Phil\VideoBundle\Application\Contract\ViewingStore;
use Phil\VideoBundle\Application\NoViewingConsent;
use Phil\VideoBundle\Application\ViewingTracker;
use Phil\VideoBundle\Infrastructure\Cache\CacheViewingStore;
use Phil\VideoBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\VideoBundle\Infrastructure\DependencyInjection\VideoServices;
use Phil\VideoBundle\Infrastructure\Http\ProgressController;
use Phil\VideoBundle\Infrastructure\Memory\InMemoryViewingStore;
use Phil\VideoBundle\Infrastructure\Twig\PlayerComponent;
use Phil\VideoBundle\PhilVideoBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilVideoBundle::class)]
#[CoversClass(VideoServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilVideoBundleTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $extension = new PhilVideoBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $extension->load([$config], $container);

        return $container;
    }

    public function testTrackingIsOffAndNobodyAgreedByDefault(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_video', new PhilVideoBundle()->getContainerExtension()?->getAlias());
        self::assertSame(NoViewingConsent::class, $container->getDefinition(ViewingConsent::class)->getClass());
        self::assertSame(InMemoryViewingStore::class, $container->getDefinition(ViewingStore::class)->getClass());
        self::assertSame([['method' => 'reset']], $container->getDefinition(ViewingStore::class)->getTag('kernel.reset'));
        self::assertTrue($container->getDefinition(ViewingStore::class)->isPublic());
        self::assertEquals([new Reference(ViewingConsent::class), new Reference(ViewingStore::class), new Reference('event_dispatcher'), false], $container->getDefinition(ViewingTracker::class)->getArguments());
        self::assertTrue($container->getDefinition(ViewingTracker::class)->isPublic());
        self::assertEquals([new Reference(ViewingTracker::class)], $container->getDefinition(ProgressController::class)->getArguments());
        self::assertTrue($container->getDefinition(ProgressController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(ProgressController::class)->isPublic());
        self::assertEquals([null, new Reference(ViewingTracker::class), '/_phil/video/progress'], $container->getDefinition(PlayerComponent::class)->getArguments());
        self::assertFalse($container->getDefinition(PlayerComponent::class)->isShared());
        self::assertTrue($container->getDefinition(PlayerComponent::class)->isAutoconfigured());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['tracking' => ['enabled' => true, 'endpoint' => '/views', 'consent' => 'app.consent', 'store' => 'cache', 'cache_pool' => 'cache.heat'], 'kit_subdirectory' => 'ui']);

        self::assertSame('app.consent', (string) $container->getAlias(ViewingConsent::class));
        self::assertSame(CacheViewingStore::class, $container->getDefinition(ViewingStore::class)->getClass());
        self::assertEquals([new Reference('cache.heat')], $container->getDefinition(ViewingStore::class)->getArguments());
        self::assertTrue($container->getDefinition(ViewingStore::class)->isPublic());
        self::assertTrue($container->getDefinition(ViewingTracker::class)->getArgument(3));
        self::assertSame(['ui', '/views'], [$container->getDefinition(PlayerComponent::class)->getArgument(0), $container->getDefinition(PlayerComponent::class)->getArgument(2)]);

        $service = $this->load(['tracking' => ['store' => 'app.heat']]);
        self::assertSame('app.heat', (string) $service->getAlias(ViewingStore::class));
        self::assertTrue($service->getAlias(ViewingStore::class)->isPublic());
        self::assertEquals([new Reference('cache.app')], $this->load(['tracking' => ['store' => 'cache']])->getDefinition(ViewingStore::class)->getArguments());
    }

    public function testThePassNamesTheKitAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'shadcn');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('shadcn', $container->getDefinition(PlayerComponent::class)->getArgument(0));

        $none = $this->load([]);
        new KitSubdirectoryPass()->process($none);
        self::assertSame('', $none->getDefinition(PlayerComponent::class)->getArgument(0));

        $own = $this->load(['kit_subdirectory' => 'ui']);
        $own->setParameter('.ux_toolkit.components.subdirectory', 'shadcn');
        new KitSubdirectoryPass()->process($own);
        self::assertSame('ui', $own->getDefinition(PlayerComponent::class)->getArgument(0));

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(PlayerComponent::class));

        $built = new ContainerBuilder();
        new PhilVideoBundle()->build($built);
        self::assertContains(KitSubdirectoryPass::class, array_map(static fn (object $pass): string => $pass::class, $built->getCompilerPassConfig()->getBeforeOptimizationPasses()));
    }
}
