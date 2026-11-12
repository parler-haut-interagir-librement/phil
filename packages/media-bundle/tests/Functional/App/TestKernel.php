<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Functional\App;

use Override;
use Phil\MediaBundle\Tests\Unit\Fixtures\Grants;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * An application as small as the bundle needs: FrameworkBundle, DoctrineBundle on SQLite, Twig and its components, a
 * thumbnail format, the media routes under `/media` and the explorer under `/admin/media`. In the environment
 * `user_content`, phil/browser-security-bundle too, and the placeholders.
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
        return sys_get_temp_dir().'/phil-media-bundle-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-media-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['secret' => 'test', 'test' => true, 'http_method_override' => false, 'php_errors' => ['log' => true], 'router' => ['utf8' => true]]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:'], 'orm' => []]);
        $container->extension('twig', []);
        $container->extension('twig_component', ['defaults' => ['App\\Twig\\Components\\' => 'components/'], 'anonymous_template_directory' => 'components/']);
        $container->extension('phil_media', ['storage_dir' => sys_get_temp_dir().'/phil-media-bundle-test/'.hash('xxh128', __DIR__).'/files', 'url_ttl' => 3600, 'formats' => ['thumb' => ['width' => 64, 'height' => 64, 'widths' => [32]]], 'placeholder' => ['enabled' => 'user_content' === $this->environment]]);
        if ('user_content' === $this->environment) {
            // The reports' rate limit needs symfony/rate-limiter, which the bundle installed alone does not have.
            $container->extension('phil_browser_security', ['reporting' => ['rate_limit' => ['enabled' => false]]]);
        }

        $container->services()->set('logger', NullLogger::class);
        $container->services()->set('security.authorization_checker', Grants::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../config/routes.php')->prefix('/media');
        $routes->import(__DIR__.'/../../../config/admin_routes.php')->prefix('/admin/media');
    }
}
