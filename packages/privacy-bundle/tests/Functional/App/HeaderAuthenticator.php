<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Signs in from a header: `fresh` has just proved who they are, `stale` signed in long ago.
 */
final class HeaderAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string HEADER = 'X-Test-Sign-In';

    #[Override]
    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER);
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        return new SelfValidatingPassport(new UserBadge($request->headers->get(self::HEADER, ''), static fn (): TestUser => new TestUser()));
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
        $token->setAuthenticationProofs(['unspecified' => 'fresh' === $passport->getBadge(UserBadge::class)?->getUserIdentifier() ? time() : time() - 86400]);

        return $token;
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

    #[Override]
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response('', Response::HTTP_UNAUTHORIZED);
    }
}
