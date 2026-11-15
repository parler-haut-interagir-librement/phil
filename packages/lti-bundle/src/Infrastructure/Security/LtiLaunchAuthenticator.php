<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Security;

use Override;
use Phil\Lti\Exception\LtiException;
use Phil\Lti\Message\Launch;
use Phil\Lti\Tool\LaunchValidator;
use Phil\LtiBundle\Application\Contract\LaunchUsers;
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

/**
 * Turns a launch the tool verifies into a security token: on the launch route, a POST with an `id_token` and a `state`
 * is checked by `LaunchValidator`; its user is the application's, by `LaunchUsers`, or an `LtiUser`; the token is an
 * `LtiToken` that carries the launch. The request then goes on to the launch controller. A launch that fails a check
 * answers 401 with the reason, never with the token.
 */
final class LtiLaunchAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'phil_lti_launch';

    private const string LAUNCH = 'phil_lti.launch';

    public function __construct(
        private readonly LaunchValidator $validator,
        private readonly ?LaunchUsers $users = null,
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return self::ROUTE === $request->attributes->get('_route') && $request->isMethod('POST') && $request->request->has('id_token');
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $idToken = $request->request->get('id_token');
        $state = $request->request->get('state');

        if (!\is_string($idToken) || !\is_string($state)) {
            throw new CustomUserMessageAuthenticationException('A launch carries its id_token and its state.');
        }

        try {
            $launch = $this->validator->validate($idToken, $state);
        } catch (LtiException $ltiException) {
            throw new CustomUserMessageAuthenticationException($ltiException->getMessage(), [], 0, $ltiException);
        }

        $user = $this->users?->user($launch) ?? LtiUser::fromLaunch($launch);
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): UserInterface => $user));
        $passport->setAttribute(self::LAUNCH, $launch);

        return $passport;
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $launch = $passport->getAttribute(self::LAUNCH);
        $user = $passport->getUser();

        if (!$launch instanceof Launch) {
            throw new CustomUserMessageAuthenticationException('The passport lost its launch.');
        }

        return new LtiToken($user, $firewallName, array_values($user->getRoles()), $launch);
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response('The launch is refused: '.$exception->getMessageKey(), Response::HTTP_UNAUTHORIZED, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
