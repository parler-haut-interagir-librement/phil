<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Functional\App;

use Override;
use Phil\Geo\Geocoding\Geocoder;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms in `fr_CA`; DoctrineBundle on SQLite with
 * a `Store` that embeds its position; TwigBundle.
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
        return sys_get_temp_dir().'/phil-geo-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-geo-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'default_locale' => 'fr_CA',
            'form' => ['csrf_protection' => false],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\GeoBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_geo', ['geocoding' => [
            'providers' => ['nominatim'],
            'nominatim' => ['user_agent' => 'Tests (tests@example.com)'],
            'cache' => ['pool' => 'cache.app'],
        ]]);

        // Nominatim, answered without the network.
        $container->services()->set(NominatimResponses::class)->public();
        $container->services()->set('http_client', MockHttpClient::class)->args([new Reference(NominatimResponses::class)]);
        $container->services()->alias('test.geocoder', Geocoder::class)->public();

        // The form factory, for the tests to build forms with.
        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }
}
