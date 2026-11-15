<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The authenticator application and the recovery codes: the setup — the secret and its `otpauth://` address, for the
 * QR code —, the confirmation by a first code, which shows the recovery codes once, new recovery codes, the removal,
 * and the challenge page where a code is asked as the second step of a sign-in or as a re-authentication.
 */
final readonly class TotpController
{
    public const string SETUP = 'phil_identity_provider_totp';

    public function __construct(
        private Pages $pages,
        private SecondFactors $factors,
        private ?Passkeys $passkeys = null,
        private bool $recoveryCodes = true,
    ) {
    }

    public function setup(): Response
    {
        $account = $this->pages->requireAccount();

        if ($this->factors->hasTotp($account)) {
            return $this->pages->render('totp/enabled', ['remaining' => $this->factors->remainingRecoveryCodes($account), 'recovery' => $this->recoveryCodes]);
        }

        $account = $this->pages->requireStepUp();

        return $this->pages->render('totp/setup', $this->factors->enroll($account) + ['errors' => []]);
    }

    public function confirm(Request $request): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        try {
            $codes = $this->factors->confirm($account, $request->request->getString('code'), $this->recoveryCodes);
        } catch (Refused $refused) {
            return $this->pages->render('totp/setup', $this->factors->enroll($account) + ['errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->render('totp/recovery_codes', ['codes' => $codes]);
    }

    public function regenerate(Request $request): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        return $this->pages->render('totp/recovery_codes', ['codes' => $this->factors->regenerateRecoveryCodes($account)]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);
        $this->factors->disableTotp($account);

        return $this->pages->redirect(self::SETUP);
    }

    public function challenge(Request $request): Response
    {
        $account = $this->pages->requireAccount();
        $error = $request->hasSession() ? $request->getSession()->remove(SecurityRequestAttributes::AUTHENTICATION_ERROR) : null;

        return $this->pages->render('totp/challenge', [
            'totp' => $this->factors->hasTotp($account),
            'recovery' => $this->recoveryCodes && $this->factors->remainingRecoveryCodes($account) > 0,
            'passkey' => [] !== ($this->passkeys?->of($account) ?? []),
            'error' => $error instanceof AuthenticationException ? $error->getMessageKey() : null,
            'csrf_id' => OneTimeCodeAuthenticator::CSRF_ID,
        ]);
    }
}
