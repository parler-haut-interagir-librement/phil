<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Functional\Plain;

use Override;
use Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup;
use Phil\ErrorTrackingBundle\Infrastructure\Monitoring\ErrorStoreCheck;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * A plain install: FrameworkBundle, DoctrineBundle and the bundle, without any of the bundles it bridges to. Messenger
 * has a command bus, the default one, and a query bus beside it; the notifier has a chatter, so that the alerts are
 * queued. Once Messenger has wired the handlers, the kernel records, as a parameter, whether the health check is
 * registered and the buses that handle the queued alerts.
 */
final class PlainKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public const string PARAMETER = 'phil_error_tracking_test.services';

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/cache-'.$this->environment;
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-error-tracking-bundle-test/'.hash('xxh128', __DIR__).'/log';
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $buses = [];

        foreach (['command.bus', 'query.bus'] as $bus) {
            $handlers = $container->getDefinition($bus.'.messenger.handlers_locator')->getArgument(0);

            if (\is_array($handlers) && \array_key_exists(AlertErrorGroup::class, $handlers)) {
                $buses[] = $bus;
            }
        }

        $container->setParameter(self::PARAMETER, [
            'health_check' => $container->hasDefinition(ErrorStoreCheck::class),
            'alert_buses' => $buses,
        ]);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'messenger' => [
                'default_bus' => 'command.bus',
                'buses' => ['command.bus' => [], 'query.bus' => []],
            ],
            'notifier' => ['chatter_transports' => ['chat' => 'null://null']],
        ]);
        $container->extension('doctrine', ['dbal' => ['url' => 'sqlite:///:memory:']]);
        $container->extension('phil_error_tracking', [
            'release' => '1.0.0',
            'rate_limit' => false,
        ]);
    }
}
