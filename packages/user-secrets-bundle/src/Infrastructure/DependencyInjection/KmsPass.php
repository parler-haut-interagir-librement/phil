<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

/**
 * Stops the container with a message that says what to change when the KMS the bundle seals through is not defined:
 * the component's bundle not registered, no client configured, or a service name that names nothing.
 */
final readonly class KmsPass implements CompilerPassInterface
{
    /**
     * @throws LogicException when the KMS is not defined
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(UserSecretsServices::KMS_PARAMETER)) {
            return;
        }

        $kms = $container->getParameter(UserSecretsServices::KMS_PARAMETER);

        if (!\is_string($kms) || $container->has($kms)) {
            return;
        }

        if (DataKeyGeneratorInterface::class === $kms) {
            throw new LogicException('phil/user-secrets-bundle seals secrets through Symfony\'s KeyManagement component, but it has no client: register Symfony\Component\KeyManagement\KeyManagementBundle and configure "key_management.clients", such as a local "sodium://" one, or set "phil_user_secrets.kms" to the service of a KMS.');
        }

        throw new LogicException(\sprintf('"phil_user_secrets.kms" names the service "%s", which is not defined: name a client of "key_management.clients", such as "key_management.<client>".', $kms));
    }
}
