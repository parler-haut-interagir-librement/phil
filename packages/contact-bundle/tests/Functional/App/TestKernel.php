<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Functional\App;

use Override;
use Phil\Contact\JSContactReader;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator and the translator in
 * `fr_CA`; DoctrineBundle on SQLite with a `Shop` that keeps its contact card; TwigBundle; the phone, URL and messaging bundles, numbers read in Canada.
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
        return sys_get_temp_dir().'/phil-contact-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-contact-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'validation' => true,
            'translator' => ['fallbacks' => ['fr']],
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [
                'mappings' => [
                    'App' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Phil\ContactBundle\Tests\Functional\App\Entity',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_phone', ['default_region' => 'CA']);
        $container->extension('phil_contact', ['jscontact' => ['region' => 'CA']]);

        // The form factory, for the tests to build forms with.
        $container->services()->alias('test.form.factory', 'form.factory')->public();
        // The JSContact reader, which no service of the application uses.
        $container->services()->alias('test.jscontact_reader', JSContactReader::class)->public();
    }
}
