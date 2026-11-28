<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Infrastructure\Federation;

use Override;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Infrastructure\Federation\Contract\ExternalProvider;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSignIn;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Google as a federated provider of `phil/identity-provider-bundle`, tagged `phil_identity_provider.external_provider`:
 * `/login/with/google` sends the person to Google, and the bundle's callback reads them back here. The bundle then
 * signs in, links or creates the account by its own rules.
 */
final readonly class GoogleProvider implements ExternalProvider
{
    public function __construct(
        private GoogleSignIn $signIn,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return GoogleSettings::NAME;
    }

    #[Override]
    public function label(): string
    {
        return GoogleSettings::LABEL;
    }

    /**
     * @throws Refused when Google's endpoints cannot be read
     */
    #[Override]
    public function start(Request $request, string $callbackUrl): RedirectResponse
    {
        try {
            return new RedirectResponse($this->signIn->start($request->getSession(), $callbackUrl));
        } catch (AuthenticationException) {
            throw Refused::because('external.failed');
        }
    }

    #[Override]
    public function finish(Request $request, string $callbackUrl): ExternalIdentity
    {
        return $this->signIn->finish($request->getSession(), $request->query->all());
    }
}
