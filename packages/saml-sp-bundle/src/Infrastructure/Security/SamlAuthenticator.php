<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Security;

use Override;
use Phil\SamlSp\Exception\SamlException;
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Application\SamlUsers;
use Phil\SamlSpBundle\Infrastructure\Routing\RoutedEndpoints;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Signs a person in from the response an identity provider posts to the assertion consumer service: `SamlLogin` reads
 * and checks it, `SamlUsers` gives the user, and the token keeps what single logout needs. The person then goes to the
 * local path the sign-in started from. A refused response answers 403 with no detail — the reason goes to
 * `SamlResponseRefused` — and never with the response.
 */
final class SamlAuthenticator extends AbstractAuthenticator
{
    public const string COOKIE = 'phil_saml_sp_binding';

    public function __construct(
        private readonly SamlLogin $login,
        private readonly SamlUsers $users,
        private readonly EventDispatcherInterface $events,
        private readonly bool $secureCookie = true,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return RoutedEndpoints::ACS === $request->attributes->get('_route') && $request->isMethod('POST') && $request->request->has('SAMLResponse');
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $identityProvider = $request->attributes->getString('idp');
        $fields = $request->request->all();
        $cookies = $request->cookies->all();
        $field = static fn (mixed $value): ?string => \is_string($value) ? $value : null;

        try {
            $completed = $this->login->complete($identityProvider, (string) $field($fields['SAMLResponse'] ?? null), $field($fields['RelayState'] ?? null), $field($cookies[self::COOKIE] ?? null));
            $user = $this->users->user($identityProvider, $completed->identity);
        } catch (SamlException $samlException) {
            $this->events->dispatch(new SamlResponseRefused($identityProvider, $samlException->getMessage(), $samlException::class));

            throw new CustomUserMessageAuthenticationException('The sign-in through the identity provider was refused.', [], 0, $samlException);
        }

        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): UserInterface => $user));
        $passport->setAttribute(SamlAttributes::IDENTITY_PROVIDER, $identityProvider);
        $passport->setAttribute(SamlAttributes::NAME_ID, $completed->identity->nameId);
        $passport->setAttribute(SamlAttributes::NAME_ID_FORMAT, $completed->identity->nameIdFormat);
        $passport->setAttribute(SamlAttributes::SESSION_INDEX, $completed->identity->sessionIndex);
        $passport->setAttribute(SamlAttributes::TARGET, $completed->target);

        return $passport;
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();
        $token = new PostAuthenticationToken($user, $firewallName, array_values($user->getRoles()));

        foreach (SamlAttributes::ALL as $name) {
            $token->setAttribute($name, $passport->getAttribute($name));
        }

        return $token;
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        $target = $token->hasAttribute(SamlAttributes::TARGET) ? $token->getAttribute(SamlAttributes::TARGET) : null;
        $response = new RedirectResponse(\is_string($target) ? $target : '/', Response::HTTP_SEE_OTHER);
        $response->headers->clearCookie(self::COOKIE, '/', null, $this->secureCookie, true, $this->secureCookie ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response('The sign-in through the identity provider was refused.', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
