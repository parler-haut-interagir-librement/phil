<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\AuthenticationMethodBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Sign-in with a passkey: the JSON of the credential the browser returned, posted to
 * `phil_identity_provider_passkey_login` by the Stimulus controller `passkey`. Signed out, any passkey of the site
 * signs its account in; signed in, only a passkey of the same account does, as a re-authentication. The proofs are
 * `hwk` and `user` (RFC 8176), and `mfa` when the authenticator verified the person by a PIN or a biometric. The answer
 * is JSON: where to go next, or the refusal.
 */
final class PasskeyAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    use TargetPathTrait;

    public const string ROUTE = 'phil_identity_provider_passkey_login';

    public function __construct(
        private readonly Passkeys $passkeys,
        private readonly TokenStorageInterface $tokens,
        private readonly string $defaultTarget = '/',
    ) {
    }

    #[Override]
    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && self::ROUTE === $request->attributes->get('_route');
    }

    #[Override]
    public function authenticate(Request $request): SelfValidatingPassport
    {
        $current = $this->tokens->getToken()?->getUser();

        try {
            [$account, $verified] = $this->passkeys->signIn($request->getContent(), $current instanceof AccountUser ? $current->account : null);
        } catch (Refused $refused) {
            throw new CustomUserMessageAuthenticationException($refused->reasons[0] ?? 'passkey.refused', [], 0, $refused);
        }

        $user = new AccountUser($account);
        $methods = $verified ? [AuthenticationMethod::HARDWARE_KEY, AuthenticationMethod::USER_PRESENCE, AuthenticationMethod::MULTIPLE_FACTORS] : [AuthenticationMethod::HARDWARE_KEY, AuthenticationMethod::USER_PRESENCE];

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): AccountUser => $user), [new AuthenticationMethodBadge(...$methods), new RememberMeBadge()]);
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): JsonResponse
    {
        $session = $request->getSession();
        $target = $session->remove(SecondFactorListener::TARGET);
        $target = \is_string($target) ? $target : $this->getTargetPath($session, $firewallName);
        $this->removeTargetPath($session, $firewallName);

        return new JsonResponse(['redirect' => \is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : $this->defaultTarget]);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }

    #[Override]
    public function isInteractive(): bool
    {
        return true;
    }
}
