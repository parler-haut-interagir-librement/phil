<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle;

use Override;
use Phil\InstantMessengerBundle\Infrastructure\Form\HandleType;
use Phil\InstantMessengerBundle\Infrastructure\Twig\MessengerExtension;
use Phil\InstantMessengerBundle\Infrastructure\Validator\HandleOfTypeValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/instant-messenger` in a Symfony application: a field that takes a handle with its service among accepted
 * enum sets, the constraint `HandleOfType`, and handles in Twig.
 */
final class PhilInstantMessengerBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_instant_messenger';

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(HandleType::class)->tag('form.type');
        $services->set(HandleOfTypeValidator::class)->tag('validator.constraint_validator');

        if (class_exists(AsTwigFilter::class)) {
            $services->set(MessengerExtension::class)
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
