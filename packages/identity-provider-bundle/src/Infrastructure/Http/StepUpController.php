<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\ExternalIdentities;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Infrastructure\Security\OneTimeCodeAuthenticator;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page of a re-authentication: the methods the account has, strongest first — a passkey, a code of the
 * authenticator application, its password through the sign-in form, a federated provider it is linked to —, each
 * proving it again and coming back to the
 * action that asked.
 */
final readonly class StepUpController
{
    public function __construct(
        private Pages $pages,
        private ?Passkeys $passkeys = null,
        private ?SecondFactors $factors = null,
        private ?string $loginRoute = null,
        private ?ExternalIdentities $identities = null,
    ) {
    }

    public function page(): Response
    {
        $account = $this->pages->requireAccount();

        return $this->pages->render('step_up/reauthenticate', [
            'passkey' => [] !== ($this->passkeys?->of($account) ?? []),
            'totp' => true === $this->factors?->hasTotp($account),
            'password' => $account->hasPassword() && null !== $this->loginRoute,
            'login_route' => $this->loginRoute,
            'csrf_id' => OneTimeCodeAuthenticator::CSRF_ID,
            'providers' => array_values(array_unique(array_map(static fn (LinkedIdentity $identity): string => $identity->provider(), $this->identities?->of($account) ?? []))),
        ]);
    }
}
