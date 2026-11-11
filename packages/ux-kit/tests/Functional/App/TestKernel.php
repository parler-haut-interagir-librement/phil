<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

use Override;
use Phil\QrCodeBundle\PhilQrCodeBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;

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

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('twig', ['strict_variables' => true, 'paths' => array_fill_keys(self::recipeTemplates(), null)]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(ChartjsStubExtension::class)->tag('twig.extension');
    }
}
