<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentities;
use Phil\IdentityProviderBundle\Infrastructure\Federation\Contract\ExternalProvider;
use Phil\IdentityProviderBundle\Infrastructure\Federation\ExternalProviders;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * The callback of a federated provider, `phil_identity_provider_external_callback`: the provider's package reads the
 * identity, `ExternalIdentities` finds, links or creates its account, and the account is signed in — or, already
 * signed in, keeps its session with the identity linked. No method is stated: an external sign-in is not a second
 * factor, and an account with an authenticator application is asked its code next.
 */
final class ExternalAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    use TargetPathTrait;

    public const string ROUTE = 'phil_identity_provider_external_callback';

    public function __construct(
        private readonly ExternalProviders $providers,
        private readonly ExternalIdentities $identities,
        private readonly TokenStorageInterface $tokens,
        private readonly UrlGeneratorInterface $urls,
        private readonly string $defaultTarget = '/',
        private readonly ?string $failureRoute = null,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return self::ROUTE === $request->attributes->get('_route');
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $name = $request->attributes->get('provider');
        $provider = \is_string($name) ? $this->providers->get($name) : null;
        $current = $this->tokens->getToken()?->getUser();

        if (!$provider instanceof ExternalProvider) {
            throw new CustomUserMessageAuthenticationException('external.unknown');
        }

        try {
            $identity = $provider->finish($request, $this->urls->generate(self::ROUTE, ['provider' => $provider->name()], UrlGeneratorInterface::ABSOLUTE_URL));
            $signIn = $this->identities->resolve($identity, $current instanceof AccountUser ? $current->account : null);
        } catch (Refused $refused) {
            throw new CustomUserMessageAuthenticationException($refused->reasons[0] ?? 'external.failed', [], 0, $refused);
        }

        $user = new AccountUser($signIn->account);

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): AccountUser => $user));
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        $target = $this->getTargetPath($request->getSession(), $firewallName);
        $this->removeTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse(\is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : $this->defaultTarget);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse(null === $this->failureRoute ? $this->defaultTarget : $this->urls->generate($this->failureRoute));
    }

    #[Override]
    public function isInteractive(): bool
    {
        return true;
    }
}
