<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

use Liip\ImagineBundle\LiipImagineBundle;
use Override;
use Phil\UxKit\PhilUxKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

/**
 * The application of the image recipes with LiipImagine and its filter sets: a set of each width in JPEG, and the same
 * in AVIF, whose addresses image_filters() reads from LiipImagine, under the host of the site.
 */
final class LiipImagineKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new TwigExtraBundle();
        yield new LiipImagineBundle();
        // @phpstan-ignore generator.valueType (a bundle of the dependency injection component, which the kernel registers too)
        yield new PhilUxKitBundle();
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-test/'.hash('xxh128', __DIR__).'/liip/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-test/'.hash('xxh128', __DIR__).'/liip/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'web_link' => ['enabled' => true],
            'router' => ['default_uri' => 'https://www.example.org'],
        ]);
        $container->extension('liip_imagine', [
            'driver' => 'gd',
            'twig' => ['mode' => 'lazy'],
            'filter_sets' => [
                'hall_480' => ['filters' => ['thumbnail' => ['size' => [480, 320], 'mode' => 'outbound']]],
                'hall_960' => ['filters' => ['thumbnail' => ['size' => [960, 640], 'mode' => 'outbound']]],
                'hall_avif_480' => ['format' => 'avif', 'filters' => ['thumbnail' => ['size' => [480, 320], 'mode' => 'outbound']]],
                'hall_avif_960' => ['format' => 'avif', 'filters' => ['thumbnail' => ['size' => [960, 640], 'mode' => 'outbound']]],
            ],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => array_fill_keys(TestKernel::recipeTemplates(), null)]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@LiipImagineBundle/Resources/config/routing.yaml');
    }
}
