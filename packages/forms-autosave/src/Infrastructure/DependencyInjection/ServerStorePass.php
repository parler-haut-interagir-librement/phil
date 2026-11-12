<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\DependencyInjection;

use Override;
use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Phil\FormsAutosave\Application\Contract\DraftSealer;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Phil\FormsAutosave\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\FormsAutosave\Infrastructure\Security\TokenDraftOwner;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

/**
 * Completes the services once every bundle is loaded: the user of Symfony Security as the owner of drafts when the
 * application names none and the token storage exists; with the server store, a sealer over a client of Symfony's
 * KeyManagement component while encryption is on. Stops the container with a message that says what to change when
 * the server store lacks what it needs: the CSRF token manager, an owner, or, while encryption is on, the component,
 * a master key or its client.
 */
final readonly class ServerStorePass implements CompilerPassInterface
{
    public const string TOKEN_STORAGE = 'security.token_storage';

    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    /**
     * @param bool|null $keyManagement whether Symfony's KeyManagement component is installed; null to look
     */
    public function __construct(
        private ?bool $keyManagement = null,
    ) {
    }

    /**
     * @throws LogicException when the server store cannot be built
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(DraftOwner::class) && $container->has(self::TOKEN_STORAGE)) {
            $container->register(TokenDraftOwner::class, TokenDraftOwner::class)->setArguments([new Reference(self::TOKEN_STORAGE)]);
            $container->setAlias(DraftOwner::class, TokenDraftOwner::class);
        }

        if (!$container->hasDefinition(DraftRequest::class)) {
            return;
        }

        if (!$container->has(self::TOKEN_MANAGER)) {
            throw new LogicException(\sprintf('phil/forms-autosave checks the drafts sent by its controller with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "%s" is stateless and needs no session.', DraftRequest::TOKEN_ID));
        }

        if (!$container->has(DraftOwner::class)) {
            throw new LogicException('phil/forms-autosave keeps drafts on the server for signed-in users, but nothing names them: install SecurityBundle, or set "phil_forms_autosave.server.owner" to a service implementing DraftOwner.');
        }

        if ($container->has(DraftSealer::class)) {
            return;
        }

        if (!($this->keyManagement ?? interface_exists(DataKeyGeneratorInterface::class))) {
            throw new LogicException('phil/forms-autosave encrypts the drafts it keeps on the server through Symfony\'s KeyManagement component, which is not installed: run "composer require symfony/key-management", or set "phil_forms_autosave.server.encrypt" to false to store them in clear.');
        }

        $key = $container->getParameter(AutosaveServices::KEY);

        if (!\is_string($key)) {
            throw new LogicException('phil/forms-autosave encrypts the drafts it keeps on the server, but no master key is named: set "phil_forms_autosave.server.key" to a master key of the KMS, or "phil_forms_autosave.server.encrypt" to false to store them in clear.');
        }

        $kms = $container->getParameter(AutosaveServices::KMS);
        $kms = \is_string($kms) ? $kms : DataKeyGeneratorInterface::class;

        if (!$container->has($kms)) {
            throw new LogicException(\sprintf('phil/forms-autosave encrypts the drafts it keeps on the server through the KMS "%s", which is not defined: register Symfony\Component\KeyManagement\KeyManagementBundle and configure "key_management.clients", or set "phil_forms_autosave.server.kms" to the service of a client.', $kms));
        }

        $container->register(KeyManagementSealer::class, KeyManagementSealer::class)->setArguments([new Reference($kms), $key]);
        $container->setAlias(DraftSealer::class, KeyManagementSealer::class);
    }
}
