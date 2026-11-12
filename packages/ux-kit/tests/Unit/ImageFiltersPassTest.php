<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Phil\UxKit\Infrastructure\DependencyInjection\ImageFiltersPass;
use Phil\UxKit\Infrastructure\LiipImagine\ImageFilters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(ImageFiltersPass::class)]
final class ImageFiltersPassTest extends TestCase
{
    public function testImageFiltersStaysWithTheServicesOfLiipImagine(): void
    {
        $container = $this->container(['liip_imagine.cache.manager', 'liip_imagine.filter.configuration']);

        new ImageFiltersPass()->process($container);

        self::assertTrue($container->hasDefinition(ImageFilters::class));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function missing(): iterable
    {
        yield 'neither' => [[]];
        yield 'no filter configuration' => [['liip_imagine.cache.manager']];
        yield 'no cache manager' => [['liip_imagine.filter.configuration']];
    }

    /**
     * @param list<string> $services
     */
    #[DataProvider('missing')]
    public function testImageFiltersIsLeftOutWithoutThem(array $services): void
    {
        $container = $this->container($services);

        new ImageFiltersPass()->process($container);

        self::assertFalse($container->hasDefinition(ImageFilters::class));
    }

    /**
     * @param list<string> $services
     */
    private function container(array $services): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(ImageFilters::class);

        foreach ($services as $service) {
            $container->register($service);
        }

        return $container;
    }
}
