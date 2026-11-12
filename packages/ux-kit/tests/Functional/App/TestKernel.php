<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

use Override;
use Phil\QrCodeBundle\PhilQrCodeBundle;
use Phil\UxKit\PhilUxKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

/**
 * An application where every recipe of the kit is installed as ux:install would: each recipe's `templates/` is a
 * path of Twig, so its components resolve as anonymous components, `<twig:Logo>` and the others.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const string KIT_DIR = __DIR__.'/../../..';

    #[Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        // html_attr(), which the Image recipe writes its attributes with, as an application installing it registers it.
        yield new TwigExtraBundle();
        // image_origin() and image_sources(), which the image recipe calls.
        // @phpstan-ignore generator.valueType (a bundle of the dependency injection component, which the kernel registers too)
        yield new PhilUxKitBundle();
        // @phpstan-ignore generator.valueType (a bundle of the dependency injection component, which the kernel registers too)
        yield new PhilQrCodeBundle();
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-test/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The `templates/` directory of each recipe of the kit.
     *
     * @return list<string>
     */
    public static function recipeTemplates(): array
    {
        $directories = glob(self::KIT_DIR.'/*/templates', \GLOB_ONLYDIR);

        return false === $directories ? [] : $directories;
    }

    /**
     * A recipe added to the kit rebuilds the container, whose Twig paths list the recipes.
     */
    #[Override]
    protected function build(ContainerBuilder $container): void
    {
        $container->addResource(new GlobResource(self::KIT_DIR, '/*/templates', false));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            // preload(), which the image recipe calls for its main image.
            'web_link' => ['enabled' => true],
            // The assets image_sources() reads: the images of the unit tests, under images/.
            'asset_mapper' => ['paths' => [__DIR__.'/../../Unit/Fixtures/images' => 'images']],
        ]);
        // The check of text alternatives follows the debug of the kernel; the environment "quiet" turns it off.
        $container->extension('phil_ux_kit', ['image' => ['hosts' => ['images.example.com']] + ('quiet' === $this->environment ? ['check' => false] : [])]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => array_fill_keys(self::recipeTemplates(), null)]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->services()->set('logger', RecordingLogger::class)->public();
        $container->services()->set(ChartjsStubExtension::class)->tag('twig.extension');
    }
}
