<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\Sites;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Signs in the account named by `X-Login`, as a login form would once its password is checked.
 */
final class HeaderAuthenticator extends AbstractAuthenticator
{
    #[Override]
    public function supports(Request $request): bool
    {
        return $request->headers->has('X-Login');
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        return new SelfValidatingPassport(new UserBadge((string) $request->headers->get('X-Login')));
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response('', Response::HTTP_UNAUTHORIZED);
    }
}
