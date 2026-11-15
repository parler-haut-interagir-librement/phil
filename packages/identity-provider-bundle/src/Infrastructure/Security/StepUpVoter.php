<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `PHIL_STEP_UP`, the attribute of sensitive actions: granted when the account proved its strongest method within
 * `lifetime` seconds — a passkey or a code (`hwk`, `otp`, `mfa`) for an account that has one, else its password too,
 * else, for an account that signs in only through a federated provider, a new sign-in there.
 * Denied, the vote asks Symfony Security for a re-authentication, which the firewall's `re_authentication_entry_point`
 * starts: the bundle's page, where the person proves it again and comes back.
 *
 * @extends Voter<string, mixed>
 */
final class StepUpVoter extends Voter
{
    public const string ATTRIBUTE = 'PHIL_STEP_UP';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ?SecondFactors $factors = null,
        private readonly ?Passkeys $passkeys = null,
        private readonly int $lifetime = 300,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof AccountUser) {
            return false;
        }

        $strong = true === $this->factors?->hasTotp($user->account) || [] !== ($this->passkeys?->of($user->account) ?? []);
        $methods = match (true) {
            $strong => Proofs::STRONG,
            $user->account->hasPassword() => [...Proofs::STRONG, AuthenticationMethod::PASSWORD],
            // An account that signs in only through a federated provider proves it by signing in there again.
            default => [...Proofs::STRONG, AuthenticationMethod::PASSWORD, AuthenticationMethod::UNSPECIFIED],
        };

        if (Proofs::provenSince($token, $methods, $this->clock->now()->getTimestamp() - $this->lifetime)) {
            $vote?->addReason('The account proved a strong enough method recently.');

            return true;
        }

        $vote?->addReason('The account must prove its strongest method again.');
        $vote?->requestReAuthentication(self::ATTRIBUTE);

        return false;
    }
}
