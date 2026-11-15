<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\EntryPoint\ReAuthenticationEntryPointInterface;

/**
 * The firewall's `re_authentication_entry_point`: a signed-in person who must prove it again — for `PHIL_STEP_UP`,
 * `IS_AUTHENTICATED_RECENTLY` or `IS_AUTHENTICATED_VERY_RECENTLY` — goes to the bundle's page, which offers the methods
 * their account has. Symfony Security has kept where they were going.
 */
final readonly class ReAuthenticationEntryPoint implements ReAuthenticationEntryPointInterface
{
    public const string ROUTE = 'phil_identity_provider_reauthenticate';

    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Override]
    public function startReAuthentication(Request $request, TokenInterface $token): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate(self::ROUTE));
    }
}
