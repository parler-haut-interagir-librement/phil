<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App;

use Override;
use Phil\EnumsBundle\Tests\Functional\App\Entity\Parcel;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelOption;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle with the translator, the serializer, the validator
 * and the workflows; DoctrineBundle on SQLite with a `Parcel` entity; TwigBundle. Its `src/` declares the enums the
 * commands and the extractor find; the bundle stores the parcel's options as a bitmask and builds the `parcel`
 * state machine from the status's transitions.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-enums-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-enums-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'default_locale' => 'en',
            'translator' => ['default_path' => __DIR__.'/translations', 'fallbacks' => ['en']],
            'serializer' => true,
            'validation' => true,
            'property_access' => true,
            // Reads the property types the serializer denormalizes into, `EnumSet` among them.
            'property_info' => true,
        ]);

        // The 404 responses the tests ask for are logged; the default logger would print them.
        $container->services()->set('logger', NullLogger::class);

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/enums.sqlite'],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\EnumsBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', [
            'default_path' => __DIR__.'/templates',
            'strict_variables' => true,
        ]);

        $container->services()->set(TestController::class)->tag('controller.service_arguments')->public();

        $container->extension('phil_enums', [
            'paths' => [__DIR__.'/src'],
            'doctrine' => ['sets' => ['parcel_options' => ['enum' => ParcelOption::class, 'storage' => 'bitmask']]],
            'workflows' => ['parcel' => ['enum' => ParcelStatus::class, 'supports' => [Parcel::class]]],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('status', '/status/{status}')->controller([TestController::class, 'status']);
        $routes->add('open', '/open/{status}')
            ->controller([TestController::class, 'open'])
            ->condition("enum_in_group(enum_from(params['status'], '".addslashes(ParcelStatus::class)."'), 'open')");
    }
}
