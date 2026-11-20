<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Functional\App;

use Override;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the module needs: FrameworkBundle, DoctrineBundle on an in-memory SQLite database, the
 * core of commerce with GST and QST on goods, the catalogue and the module with a store of images in a temporary
 * directory — or without the catalogue, when a test asks for it.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug, private readonly bool $withCatalogue = true)
    {
        parent::__construct($environment, $debug);
    }

    public static function imagesDirectory(): string
    {
        return sys_get_temp_dir().'/phil-commerce-product-template-bundle-test/images';
    }

    public static function productionDirectory(): string
    {
        return sys_get_temp_dir().'/phil-commerce-product-template-bundle-test/production';
    }

    /**
     * Replaces the trait's own, which reads `config/bundles.php` only.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/'.($this->withCatalogue ? 'bundles.php' : 'bundles-without-catalogue.php');
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-commerce-product-template-bundle-test/'.($this->withCatalogue ? 'full' : 'alone').'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-commerce-product-template-bundle-test/'.($this->withCatalogue ? 'full' : 'alone').'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'messenger' => ['enabled' => true],
        ]);

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('phil_commerce', ['tax_rates' => ['goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]]);
        $container->extension('phil_commerce_product_template', [
            'images_directory' => self::imagesDirectory(),
            'default_locale' => 'en',
            'production' => ['directory' => self::productionDirectory(), 'fonts' => [LootShirts::FONT => LootShirts::font()], 'on_purchase' => true],
        ]);
    }
}
