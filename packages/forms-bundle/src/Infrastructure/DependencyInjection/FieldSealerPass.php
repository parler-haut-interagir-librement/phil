<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Infrastructure\KeyManagement\KeyManagementFieldSealer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

/**
 * Seals the values of sensitive fields through Symfony's KeyManagement component when `phil_forms.sealing.key` names
 * a master key: the sealer over the client `phil_forms.sealing.kms` names, or the component's default client. An
 * application that aliases `FieldSealer` to its own service keeps it; without either, no sealer is defined and a form
 * with a sensitive field is refused, unless `phil_forms.allow_unsealed_sensitive` is true. Stops the container with a
 * message that says what to change when a master key is named without the component or its client.
 */
final readonly class FieldSealerPass implements CompilerPassInterface
{
    /**
     * @param bool|null $keyManagement whether Symfony's KeyManagement component is installed; null to look
     */
    public function __construct(
        private ?bool $keyManagement = null,
    ) {
    }

    /**
     * @throws LogicException when a master key is named without the component or without its client
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(FieldSealer::class) || !$container->hasParameter(FormsServices::SEALING_KEY)) {
            return;
        }

        $key = $container->getParameter(FormsServices::SEALING_KEY);

        if (!\is_string($key)) {
            return;
        }

        if (!($this->keyManagement ?? interface_exists(DataKeyGeneratorInterface::class))) {
            throw new LogicException('"phil_forms.sealing.key" seals the values of sensitive fields through Symfony\'s KeyManagement component, which is not installed: run "composer require symfony/key-management", or remove the option and set "phil_forms.allow_unsealed_sensitive: true" to keep the values as sent.');
        }

        $kms = $container->getParameter(FormsServices::SEALING_KMS);
        $kms = \is_string($kms) ? $kms : DataKeyGeneratorInterface::class;

        if (!$container->has($kms)) {
            throw new LogicException(\sprintf('"phil_forms.sealing.key" seals the values of sensitive fields through the KMS "%s", which is not defined: register Symfony\Component\KeyManagement\KeyManagementBundle and configure "key_management.clients", or set "phil_forms.sealing.kms" to the service of a client.', $kms));
        }

        $container->register(KeyManagementFieldSealer::class, KeyManagementFieldSealer::class)->setArguments([new Reference($kms), $key]);
        $container->setAlias(FieldSealer::class, KeyManagementFieldSealer::class);
    }
}
