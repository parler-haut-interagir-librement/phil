<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\EmailTemplatesBundle\Infrastructure\Http\AdminController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Stops the container with a message that says what to change when the administration is enabled and the application
 * turns FrameworkBundle's CSRF protection off: its forms are checked with Symfony's token manager, which then does not
 * exist.
 */
final readonly class CsrfProtectionPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AdminController::class) || $container->has(self::TOKEN_MANAGER)) {
            return;
        }

        throw new LogicException(\sprintf('The administration of phil/email-templates-bundle checks its forms with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Set it to true: the token "%s" is stateless and needs no session.', AdminController::TOKEN_ID));
    }
}
