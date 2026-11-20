<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App;

use Override;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\BlamePrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\OwnerPrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\VersionsPrivacy;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * An application as small as the bundle needs: FrameworkBundle, the ORM on SQLite in memory, entities that have
 * every behaviour, and the bundle's configuration in YAML, as an application writes it.
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
        return sys_get_temp_dir().'/phil-doctrine-behaviours-bundle-test/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-doctrine-behaviours-bundle-test/'.hash('xxh128', __DIR__).'/log';
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
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => [
                'mappings' => ['App' => ['type' => 'attribute', 'dir' => '%kernel.project_dir%/Entity', 'prefix' => __NAMESPACE__.'\Entity']],
            ],
        ]);
        $container->import(__DIR__.'/config/packages/*.yaml');
        $container->services()->set('logger', NullLogger::class);
        $container->services()->alias('test.workflow_registry', 'workflow.registry')->public();
        $container->services()->alias('test.blame_privacy', BlamePrivacy::class)->public();
        $container->services()->alias('test.owner_privacy', OwnerPrivacy::class)->public();
        $container->services()->alias('test.versions_privacy', VersionsPrivacy::class)->public();
    }
}
