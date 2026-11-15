<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\DependencyInjection;

use Phil\VideoBundle\Application\Contract\ViewingConsent;
use Phil\VideoBundle\Application\Contract\ViewingStore;
use Phil\VideoBundle\Application\NoViewingConsent;
use Phil\VideoBundle\Application\ViewingTracker;
use Phil\VideoBundle\Infrastructure\Cache\CacheViewingStore;
use Phil\VideoBundle\Infrastructure\Http\ProgressController;
use Phil\VideoBundle\Infrastructure\Memory\InMemoryViewingStore;
use Phil\VideoBundle\Infrastructure\Twig\PlayerComponent;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The services of the bundle, from its processed configuration.
 */
final class VideoServices
{
    private function __construct()
    {
    }

    /**
     * @param array{tracking: array{enabled: bool, endpoint: string, consent: ?string, store: string, cache_pool: string}, kit_subdirectory: ?string} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $tracking = $config['tracking'];

        if (null === $tracking['consent']) {
            $container->register(ViewingConsent::class, NoViewingConsent::class);
        } else {
            $container->setAlias(ViewingConsent::class, $tracking['consent']);
        }

        match ($tracking['store']) {
            'memory' => $container->register(ViewingStore::class, InMemoryViewingStore::class)->addTag('kernel.reset', ['method' => 'reset'])->setPublic(true),
            'cache' => $container->register(ViewingStore::class, CacheViewingStore::class)->setArguments([new Reference($tracking['cache_pool'])])->setPublic(true),
            default => $container->setAlias(ViewingStore::class, $tracking['store'])->setPublic(true),
        };

        $container->register(ViewingTracker::class, ViewingTracker::class)
            ->setArguments([new Reference(ViewingConsent::class), new Reference(ViewingStore::class), new Reference('event_dispatcher'), $tracking['enabled']])
            ->setPublic(true);
        $container->register(ProgressController::class, ProgressController::class)
            ->setArguments([new Reference(ViewingTracker::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        if (class_exists(AsTwigComponent::class)) {
            $container->register(PlayerComponent::class, PlayerComponent::class)
                // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
                ->setArguments([$config['kit_subdirectory'], new Reference(ViewingTracker::class), $tracking['endpoint']])
                ->setShared(false)
                ->setAutoconfigured(true);
        }
    }
}
