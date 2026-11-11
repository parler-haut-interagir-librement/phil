<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\DependencyInjection;

use Phil\EditLockBundle\Application\BlockSaves;
use Phil\EditLockBundle\Application\Broadcaster;
use Phil\EditLockBundle\Application\EditLocks;
use Phil\EditLockBundle\Application\NullBroadcaster;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Infrastructure\Doctrine\DoctrineLeaseRepository;
use Phil\EditLockBundle\Infrastructure\Http\ClientConfig;
use Phil\EditLockBundle\Infrastructure\Http\EditLockController;
use Phil\EditLockBundle\Infrastructure\Http\EditLockRequest;
use Phil\EditLockBundle\Infrastructure\Memory\InMemoryLeaseRepository;
use Phil\EditLockBundle\Infrastructure\Mercure\MercureBroadcaster;
use Phil\EditLockBundle\Infrastructure\Mercure\TurboStreams;
use Phil\Ujo\Ujo;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the store, the broadcaster — Mercure when the hub is there —, the leases and the block saves
 * on the services the configuration names, the check of the controller's requests on FrameworkBundle's CSRF token
 * manager, the endpoints and the controller's configuration.
 */
final class EditLockServices
{
    /**
     * @param array{seconds: int, store: string, connection: string, holder_provider: ?string, policy: ?string, block_store: ?string, mercure: array{enabled: bool, hub: string, topic_prefix: string, private: bool}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemoryLeaseRepository::class, InMemoryLeaseRepository::class);
            $container->setAlias(LeaseRepository::class, InMemoryLeaseRepository::class)->setPublic(true);
        } else {
            $container->register(DoctrineLeaseRepository::class, DoctrineLeaseRepository::class)
                ->setArguments([new Reference('doctrine.dbal.'.$config['connection'].'_connection')]);
            $container->setAlias(LeaseRepository::class, DoctrineLeaseRepository::class)->setPublic(true);
        }

        $hub = null;

        if ($config['mercure']['enabled']) {
            $hub = new Reference($config['mercure']['hub']);
            $container->register(MercureBroadcaster::class, MercureBroadcaster::class)->setArguments([
                $hub,
                new Reference(TurboStreams::class),
                $config['mercure']['topic_prefix'],
                $config['mercure']['private'],
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $container->setAlias(Broadcaster::class, MercureBroadcaster::class);
        } else {
            $container->register(NullBroadcaster::class, NullBroadcaster::class);
            $container->setAlias(Broadcaster::class, NullBroadcaster::class);
        }

        $container->register(TurboStreams::class, TurboStreams::class);
        $container->register(EditLocks::class, EditLocks::class)
            ->setArguments([
                new Reference(LeaseRepository::class),
                new Reference('clock'),
                $config['seconds'],
                self::service($config['policy']),
                new Reference(Broadcaster::class),
            ])
            ->setPublic(true);
        $container->register(BlockSaves::class, BlockSaves::class)
            ->setArguments([
                new Reference(EditLocks::class),
                new Reference(Broadcaster::class),
                new Reference(Ujo::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                self::service($config['block_store']),
            ])
            ->setPublic(true);
        $container->register(EditLockRequest::class, EditLockRequest::class)
            ->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(EditLockController::class, EditLockController::class)
            ->setArguments([
                new Reference(EditLocks::class),
                new Reference(BlockSaves::class),
                new Reference('clock'),
                new Reference(EditLockRequest::class),
                self::service($config['holder_provider']),
                null !== $config['holder_provider'] && null !== $config['policy'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(ClientConfig::class, ClientConfig::class)
            ->setArguments([
                $config['seconds'],
                new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $hub,
                $config['mercure']['topic_prefix'],
                new Reference(EditLockRequest::class),
            ])
            ->setPublic(true);
    }

    private static function service(?string $id): ?Reference
    {
        return null === $id ? null : new Reference($id);
    }
}
