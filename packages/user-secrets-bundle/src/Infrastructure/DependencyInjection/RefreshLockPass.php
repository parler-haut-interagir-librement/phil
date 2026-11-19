<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;

/**
 * Gives `UserSecrets` the refresh lock over the lock factory the configuration names — FrameworkBundle's `lock.factory`
 * by default — when that service is defined and Symfony's Lock component is installed; without them, refreshes rely
 * on the compare-and-swap alone. A factory the application named itself and that is not defined stops the container.
 */
final readonly class RefreshLockPass implements CompilerPassInterface
{
    /**
     * @throws LogicException when the lock factory the application named is not defined
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(UserSecretsServices::LOCK_FACTORY_PARAMETER) || !$container->hasDefinition(UserSecrets::class)) {
            return;
        }

        $factory = $container->getParameter(UserSecretsServices::LOCK_FACTORY_PARAMETER);

        if (!\is_string($factory)) {
            return;
        }

        if (!class_exists(LockFactory::class) || !$container->has($factory)) {
            if (true === $container->getParameter(UserSecretsServices::LOCK_FACTORY_REQUIRED_PARAMETER)) {
                throw new LogicException(\sprintf('"phil_user_secrets.lock_factory" names the service "%s", which is not defined: install symfony/lock and configure "framework.lock", or name another lock factory.', $factory));
            }

            return;
        }

        $ttl = $container->getParameter(UserSecretsServices::LOCK_TTL_PARAMETER);
        $container->register(SymfonyRefreshLock::class, SymfonyRefreshLock::class)
            ->setArguments([new Reference($factory), \is_int($ttl) ? (float) $ttl : 30.0]);
        $container->getDefinition(UserSecrets::class)->setArgument(UserSecretsServices::LOCK_ARGUMENT, new Reference(SymfonyRefreshLock::class));
    }
}
