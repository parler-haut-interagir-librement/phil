<?php

declare(strict_types=1);

namespace Phil\Profile\SecureInput\Tests\Functional\App;

use Override;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * An application that installed the profile and registered every bundle it brings, in config/bundles.php, with no
 * configuration of theirs: what a project sees right after `composer require phil/profile-secure-input`.
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
        return sys_get_temp_dir().'/phil-profile-secure-input-test/'.hash('xxh128', __DIR__).'/cache/'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-profile-secure-input-test/'.hash('xxh128', __DIR__).'/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        // The administration page of the quarantine is registered whenever Twig and symfony/security-csrf are installed,
        // and asks for the token manager: an application with both turns CSRF protection on, with its sessions.
        $csrf = interface_exists(CsrfTokenManagerInterface::class);
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'csrf_protection' => ['enabled' => $csrf],
            'session' => ['enabled' => $csrf, 'storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
    }
}
