<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Stops the container with a message that says what to change when the key management client the configuration names
 * is not defined: the conversations are never kept in the clear. An application that aliases `MessageSealer` to its
 * own service keeps it.
 */
final readonly class EncryptionPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(KeyManagementMessageSealer::class) || KeyManagementMessageSealer::class !== $container->getAlias(MessageSealer::class)->__toString()) {
            return;
        }

        $kms = $container->getDefinition(KeyManagementMessageSealer::class)->getArgument(0);
        $id = $kms instanceof Reference ? (string) $kms : '';

        if (!$container->has($id)) {
            throw new LogicException(\sprintf('"phil_conversation.encryption" seals the conversations through the key management client "%s", which is not defined: register Symfony\Component\KeyManagement\KeyManagementBundle and configure "key_management.clients", or set "phil_conversation.encryption.kms" to the service of a client.', $id));
        }
    }
}
