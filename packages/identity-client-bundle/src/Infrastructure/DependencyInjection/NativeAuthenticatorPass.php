<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\DependencyInjection;

use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Checks, once the security bundle has declared the `oidc_login` authenticator of the firewall, what the bundle builds
 * on: the native services it decorates or reads, under the identifiers this version of Symfony gives them, and the
 * protections the configuration of the application could still turn off after the composed block — PKCE with `S256`
 * and the signature check of the ID token.
 *
 * A later minor version of Symfony that moves a service, or a firewall block that weakens the authenticator, stops the
 * build here with the reason, rather than leaving a sign-in that is less protected than the documentation says.
 */
final class NativeAuthenticatorPass implements CompilerPassInterface
{
    public const string FIREWALL_PARAMETER = '.phil_identity_client.firewall';

    public const string SERVICES_PARAMETER = '.phil_identity_client.native_services';

    private const int OPTIONS_ARGUMENT = 8;

    private const int SIGNATURE_VERIFIER_ARGUMENT = 10;

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter(self::FIREWALL_PARAMETER) || !$container->hasParameter(self::SERVICES_PARAMETER)) {
            return;
        }

        $firewall = $container->getParameter(self::FIREWALL_PARAMETER);
        $services = $container->getParameter(self::SERVICES_PARAMETER);
        if (!\is_string($firewall) || !\is_array($services)) {
            return;
        }

        foreach ($services as $id) {
            if (\is_string($id) && !$container->has($id)) {
                throw new LogicException(\sprintf('phil_identity_client needs the "%s" service of the oidc_login authenticator, which this version of symfony/security-bundle does not declare.', $id));
            }
        }

        $authenticator = $container->getDefinition(ClientServices::NATIVE_AUTHENTICATOR.$firewall);
        $options = $authenticator->getArgument(self::OPTIONS_ARGUMENT);
        if (!\is_array($options) || true !== ($options['pkce_enabled'] ?? null) || 'S256' !== ($options['pkce_method'] ?? null)) {
            throw new LogicException(\sprintf('The oidc_login authenticator of firewall "%s" must use PKCE with S256: phil_identity_client does not sign in without it.', $firewall));
        }

        if (!$authenticator->getArgument(self::SIGNATURE_VERIFIER_ARGUMENT) instanceof Reference) {
            throw new LogicException(\sprintf('The oidc_login authenticator of firewall "%s" must check the signature of the ID token: phil_identity_client does not sign in without it.', $firewall));
        }
    }
}
