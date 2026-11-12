<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Functional\App;

use Override;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle with forms, the validator and the translator in
 * `fr_CA`; DoctrineBundle on SQLite; TwigBundle; and two configured dates, with the database.
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
        return sys_get_temp_dir().'/phil-almanac-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-almanac-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
            'orm' => [],
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_almanac', [
            'timezone' => 'America/Toronto',
            'database' => true,
            'dates' => [
                'noel' => ['title' => 'Noël', 'rule' => '12-25', 'tags' => ['fete']],
                'thanksgiving' => ['title' => 'Action de grâce', 'rule' => '2nd monday of october', 'tags' => ['fete']],
            ],
            'eclipses' => ['solar_title' => 'Éclipse solaire', 'lunar_title' => 'Éclipse lunaire'],
            'places' => [
                'montreal' => ['latitude' => 45.5017, 'longitude' => -73.5673],
                'paris' => ['latitude' => 48.8566, 'longitude' => 2.3522, 'timezone' => 'Europe/Paris'],
            ],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias('test.form.factory', 'form.factory')->public();
    }
}
