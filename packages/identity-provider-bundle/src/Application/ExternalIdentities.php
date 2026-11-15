<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Psr\Clock\ClockInterface;

/**
 * External identities and the accounts they sign in, by these rules, in order:
 *
 * 1. an identity already linked signs its account in — and is refused when someone else is signed in;
 * 2. someone signed in links the identity to their own account;
 * 3. an identity whose address an account already has is refused with `external.email_known`: the person signs in
 *    and links it from their account. Accounts are never merged on an address the provider did not verify, and on a
 *    verified one only when `link_verified_email` is on and the account verified it too;
 * 4. otherwise, when registration is open, a new account without a password is created and linked, its address
 *    verified when the provider verified it.
 */
final readonly class ExternalIdentities
{
    public function __construct(
        private LinkedIdentityRepository $identities,
        private AccountRepository $accounts,
        private ?PasskeyRepository $passkeys,
        private Accounts $registration,
        private Journal $journal,
        private ClockInterface $clock,
        private bool $linkVerifiedEmail = false,
        private bool $register = true,
    ) {
    }

    /**
     * @throws Refused when the identity is another account's, its address is known, or registration is closed
     */
    public function resolve(ExternalIdentity $identity, ?Account $current = null): ExternalSignIn
    {
        $now = $this->clock->now();
        $linked = $this->identities->find($identity->provider, $identity->subject);

        if ($linked instanceof LinkedIdentity) {
            $account = $this->accounts->get($linked->accountId());

            if (!$account instanceof Account || $account->isDisabled() || ($current instanceof Account && $current->id() !== $account->id())) {
                throw Refused::because('external.linked_elsewhere');
            }

            $linked->recordUse($now);
            $this->identities->save($linked);

            return new ExternalSignIn($account);
        }

        if ($current instanceof Account) {
            return new ExternalSignIn($this->link($current, $identity), linked: true);
        }

        $known = null === $identity->email ? null : $this->accounts->findByEmail($identity->email);

        if ($known instanceof Account) {
            if ($this->linkVerifiedEmail && $identity->emailVerified && $known->isEmailVerified() && !$known->isDisabled()) {
                return new ExternalSignIn($this->link($known, $identity), linked: true);
            }

            throw Refused::because('external.email_known');
        }

        if (!$this->register || null === $identity->email) {
            throw Refused::because('external.registration_closed');
        }

        $account = $this->registration->register($identity->email, null, $identity->displayName) ?? throw Refused::because('external.email_known');

        if ($identity->emailVerified) {
            $this->registration->verifyEmail($account);
        }

        return new ExternalSignIn($this->link($account, $identity), created: true, linked: true);
    }

    /**
     * @return list<LinkedIdentity>
     */
    public function of(Account $account): array
    {
        return $this->identities->forAccount($account->id());
    }

    /**
     * @throws Refused when the identity is not the account's, or it is its last way to sign in
     */
    public function unlink(Account $account, string $identityId): void
    {
        $linked = $this->identities->forAccount($account->id());
        $identity = array_find($linked, static fn (LinkedIdentity $identity): bool => $identity->id() === $identityId) ?? throw Refused::because('external.unknown');

        if (!$account->hasPassword() && [] === ($this->passkeys?->forAccount($account->id()) ?? []) && 1 === \count($linked)) {
            throw Refused::because('external.last');
        }

        $this->identities->remove($identity);
        $this->journal->record(IdentityEventType::IdentityUnlinked, $account->id(), ['provider' => $identity->provider()]);
    }

    private function link(Account $account, ExternalIdentity $identity): Account
    {
        $this->identities->save(LinkedIdentity::link($account->id(), $identity->provider, $identity->subject, $this->clock->now()));
        $this->journal->record(IdentityEventType::IdentityLinked, $account->id(), ['provider' => $identity->provider]);

        return $account;
    }
}
