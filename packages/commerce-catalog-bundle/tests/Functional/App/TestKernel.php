<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the module needs: FrameworkBundle, DoctrineBundle on an in-memory SQLite database,
 * the core of commerce with GST and QST on goods, and the module with names in French and English — or the module without the core, when a test asks
 * for it.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug, private readonly bool $withCore = true)
    {
        parent::__construct($environment, $debug);
    }

    /**
     * Replaces the trait's own, which reads `config/bundles.php` only.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/config/'.($this->withCore ? 'bundles.php' : 'bundles-without-core.php');
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-commerce-catalog-bundle-test/'.($this->withCore ? 'core' : 'alone').'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-commerce-catalog-bundle-test/'.($this->withCore ? 'core' : 'alone').'/log';
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

        // Nothing the tests do is meant to be logged; the default logger would print it.
        $container->services()->set('logger', NullLogger::class);

        if ($this->withCore) {
            $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
            $container->extension('phil_commerce', ['tax_rates' => ['goods' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]]);
            $container->extension('phil_commerce_catalog', [
                'pay_what_you_want' => ['default_maximum' => 5_000],
                'translations' => ['locales' => ['fr' => ['en'], 'en' => []]],
                'product_feeds' => ['google' => [
                    'path' => '%kernel.cache_dir%/google.xml',
                    'title' => 'The Dice Shop',
                    'link' => 'http://shop.localhost/',
                    'description' => 'Dice and games.',
                    'locale' => 'fr',
                    'product_link' => 'http://shop.localhost/products/{sku}',
                    'image_link' => 'http://shop.localhost/images/{sku}.jpg',
                    'attributes' => ['color' => 'colour'],
                    'allow_localhost' => true,
                ]],
            ]);
        }
    }
}
