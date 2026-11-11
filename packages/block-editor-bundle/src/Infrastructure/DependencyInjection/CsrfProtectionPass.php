<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Stops the container with a message that says what to change when the application turns FrameworkBundle's CSRF
 * protection off: the bundle checks its stateless token with Symfony's token manager, which then does not exist.
 */
final readonly class CsrfProtectionPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    /**
     * @param string $service the bundle's service that checks the token
     * @param string $tokenId the identifier of the token, listed among the stateless ones
     */
    public function __construct(
        private string $service,
        private string $tokenId,
    ) {
    }

    /**
     * @throws LogicException when the service is defined and the CSRF protection is off
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition($this->service) || $container->has(self::TOKEN_MANAGER)) {
            return;
        }

        throw new LogicException(\sprintf('phil/block-editor-bundle checks the uploads of its editor with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "%s" is stateless and needs no session. "%s" cannot be built without "%s".', $this->tokenId, $this->service, self::TOKEN_MANAGER));
    }
}
