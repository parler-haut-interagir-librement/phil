<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\MonitoringBundle\Application\Queue\Queues;
use Phil\MonitoringBundle\Infrastructure\Http\QueuesController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the queues their transports, read from the receivers Messenger tags, once every bundle has loaded; removes the
 * queues and their page when the application has no Messenger bus, and the page alone when it lacks Twig, the router,
 * the authorization checker or the CSRF token manager.
 */
final class MonitoringPass implements CompilerPassInterface
{
    private const array PAGE_SERVICES = ['twig', 'router', 'security.authorization_checker', 'security.csrf.token_manager'];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(Queues::class)) {
            return;
        }

        if (!$container->has('messenger.default_bus')) {
            $container->removeDefinition(Queues::class);
            $container->removeDefinition(QueuesController::class);

            return;
        }

        $receivers = [];
        $failure = [];

        foreach ($container->findTaggedServiceIds('messenger.receiver') as $id => $tags) {
            foreach ($tags as $tag) {
                if (!\is_array($tag) || !\is_string($tag['alias'] ?? null)) {
                    continue;
                }

                $receivers[$tag['alias']] = new Reference($id);

                if (true === ($tag['is_failure_transport'] ?? false)) {
                    $failure[] = $tag['alias'];
                }
            }
        }

        $container->getDefinition(Queues::class)
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $receivers))
            ->replaceArgument(1, array_keys($receivers))
            ->replaceArgument(2, $failure);

        if (array_any(self::PAGE_SERVICES, static fn (string $service): bool => !$container->has($service))) {
            $container->removeDefinition(QueuesController::class);
        }
    }
}
