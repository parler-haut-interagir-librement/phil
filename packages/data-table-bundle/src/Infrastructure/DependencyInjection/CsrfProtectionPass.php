<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\DataTableBundle\Infrastructure\Http\TableGuard;
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
     * @throws LogicException when the CSRF protection is off
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has(self::TOKEN_MANAGER)) {
            return;
        }

        throw new LogicException(\sprintf('phil/data-table-bundle checks the changes of a table with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "%s" is stateless and needs no session.', TableGuard::TOKEN_ID));
    }
}
