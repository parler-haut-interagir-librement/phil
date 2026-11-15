<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\AuthenticationMethodBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * The code of an authenticator application, or a recovery code, posted to `phil_identity_provider_code_check` by
 * someone already signed in: the second step of a sign-in, or a re-authentication. It authenticates the same account
 * again with the proof `otp`, which Symfony Security adds to those the token already holds, and sends the person on to
 * where they were going.
 */
final class OneTimeCodeAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    use TargetPathTrait;

    public const string ROUTE = 'phil_identity_provider_code_check';

    public const string CSRF_ID = 'phil_identity_provider_code';

    public function __construct(
        private readonly TokenStorageInterface $tokens,
        private readonly SecondFactors $factors,
        private readonly UrlGeneratorInterface $urls,
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
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof AccountUser) {
            throw new CustomUserMessageAuthenticationException('code.signed_out');
        }

        $code = $request->request->getString('code');
        $valid = 'recovery' === $request->request->getString('kind')
            ? $this->factors->useRecoveryCode($user->account, $code)
            : $this->factors->verifyTotp($user->account, $code);

        if (!$valid) {
            throw new CustomUserMessageAuthenticationException('code.invalid');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn (): AccountUser => $user),
            [new AuthenticationMethodBadge(AuthenticationMethod::ONE_TIME_PASSWORD), new CsrfTokenBadge(self::CSRF_ID, $request->request->getString('_token'))],
        );
    }

    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        $session = $request->getSession();
        $target = $session->remove(SecondFactorListener::TARGET);
        $target = \is_string($target) ? $target : $this->getTargetPath($session, $firewallName);
        $this->removeTargetPath($session, $firewallName);

        return new RedirectResponse(\is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : $this->defaultTarget);
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return new RedirectResponse($this->urls->generate(SecondFactorListener::CHALLENGE_ROUTE));
    }

    #[Override]
    public function isInteractive(): bool
    {
        return true;
    }
}
