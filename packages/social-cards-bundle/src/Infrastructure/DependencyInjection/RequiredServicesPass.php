<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Removes a service of an integration whose package is installed but whose service is not there: the queue and the
 * handler of the cards without a message bus, the images of phil/media-bundle without its Twig extension.
 */
final readonly class RequiredServicesPass implements CompilerPassInterface
{
    /**
     * @param array<string, string> $requirements the id of the service each service needs, by the id of that service
     */
    public function __construct(
        private array $requirements,
    ) {
    }

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach ($this->requirements as $service => $required) {
            if (!$container->has($required)) {
                $container->removeDefinition($service);
            }
        }
    }
}
