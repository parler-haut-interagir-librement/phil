<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\IdentityProviderBundle\Infrastructure\Http\LoginLinkController;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sets `check_post_only` on every `login_link` authenticator of Symfony Security that checks the bundle's route,
 * whatever the firewall says: a sign-in link is consumed by the POST of its confirmation page, never by the GET a
 * mail scanner or a link preview sends when it follows the link.
 */
final readonly class LoginLinkPostOnlyPass implements CompilerPassInterface
{
    private const string AUTHENTICATOR = 'security.authenticator.login_link';

    private const int OPTIONS = 4;

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if (!$definition instanceof ChildDefinition || self::AUTHENTICATOR !== $definition->getParent()) {
                continue;
            }

            $options = $definition->getArguments()['index_'.self::OPTIONS] ?? null;

            if (\is_array($options) && LoginLinkController::CHECK === ($options['check_route'] ?? null)) {
                $definition->replaceArgument(self::OPTIONS, ['check_post_only' => true] + $options);
            }
        }
    }
}
