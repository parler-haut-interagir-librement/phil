<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use JsonException;
use Phil\IdentityProviderBundle\Application\Contract\ChallengeStore;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Psr\Clock\ClockInterface;

/**
 * Passkeys: the two ceremonies of WebAuthn and the list an account manages.
 *
 * The options carry a challenge kept in the `ChallengeStore` for one answer. The browser's answer is the JSON of
 * `PublicKeyCredential.toJSON()`; it is read with bounds, verified by the `RelyingParty`, and refused with the key
 * `passkey.refused` whatever went wrong, so that a page tells nothing an attacker could use.
 */
final readonly class Passkeys
{
    public const int MAX_RESPONSE = 32_768;

    private const string REGISTER = 'passkey.register';

    private const string SIGN_IN = 'passkey.sign_in';

    public function __construct(
        private RelyingParty $relyingParty,
        private PasskeyRepository $passkeys,
        private AccountRepository $accounts,
        private ChallengeStore $challenges,
        private Journal $journal,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed> the options of `navigator.credentials.create()`
     */
    public function registrationOptions(Account $account): array
    {
        $challenge = RelyingParty::challenge();
        $this->challenges->put(self::REGISTER, $account->id().' '.$challenge);

        return $this->relyingParty->creationOptions(
            $challenge,
            $account->id(),
            $account->email(),
            $account->displayName() ?? $account->email(),
            array_map(static fn (Passkey $passkey): string => $passkey->credentialId(), $this->passkeys->forAccount($account->id())),
        );
    }

    /**
     * @throws Refused when the answer does not verify, or the passkey is already registered
     */
    public function register(Account $account, string $responseJson, string $name): Passkey
    {
        $challenge = $this->challengeFor(self::REGISTER, $account->id());
        $response = $this->response($responseJson);

        try {
            $credential = $this->relyingParty->verifyRegistration(
                Base64Url::decode($this->member($response, 'clientDataJSON')),
                Base64Url::decode($this->member($response, 'attestationObject')),
                $challenge,
            );
        } catch (MalformedInput|VerificationFailed) {
            throw Refused::because('passkey.refused');
        }

        if ($this->passkeys->findByCredentialId($credential->credentialId) instanceof Passkey) {
            throw Refused::because('passkey.known');
        }

        try {
            $passkey = Passkey::register($account->id(), $credential, $name, $this->clock->now());
        } catch (InvalidValue) {
            throw Refused::because('passkey.name_invalid');
        }

        $this->passkeys->save($passkey);
        $this->journal->record(IdentityEventType::PasskeyAdded, $account->id(), ['passkey' => $passkey->id(), 'synced' => $passkey->isBackedUp()]);

        return $passkey;
    }

    /**
     * @return array<string, mixed> the options of `navigator.credentials.get()`, for any passkey of the site
     */
    public function signInOptions(?Account $account = null): array
    {
        $challenge = RelyingParty::challenge();
        $this->challenges->put(self::SIGN_IN, ($account?->id() ?? '-').' '.$challenge);

        return $this->relyingParty->requestOptions(
            $challenge,
            $account instanceof Account ? array_map(static fn (Passkey $passkey): string => $passkey->credentialId(), $this->passkeys->forAccount($account->id())) : [],
        );
    }

    /**
     * The account a passkey signs in, its counter moved forward; with an expected account, as for a step-up, a
     * passkey of another account is refused.
     *
     * @return array{Account, bool} the account, and whether the user was verified
     *
     * @throws Refused when the answer does not verify or names an unknown passkey
     */
    public function signIn(string $responseJson, ?Account $expected = null): array
    {
        $challenge = $this->challengeFor(self::SIGN_IN, $expected?->id() ?? '-');
        $response = $this->response($responseJson);

        try {
            $rawId = Base64Url::decode(\is_string($response['rawId'] ?? null) ? $response['rawId'] : '');
        } catch (MalformedInput) {
            throw Refused::because('passkey.refused');
        }

        $passkey = '' === $rawId ? null : $this->passkeys->findByCredentialId($rawId);
        $account = $passkey instanceof Passkey ? $this->accounts->get($passkey->accountId()) : null;

        if (!$passkey instanceof Passkey || !$account instanceof Account || $account->isDisabled() || ($expected instanceof Account && $expected->id() !== $account->id())) {
            throw Refused::because('passkey.refused');
        }

        try {
            $userHandle = $this->optionalMember($response, 'userHandle');

            if (null !== $userHandle && Base64Url::decode($userHandle) !== $account->id()) {
                throw VerificationFailed::because('The user handle names another account.');
            }

            $assertion = $this->relyingParty->verifyAssertion(
                Base64Url::decode($this->member($response, 'clientDataJSON')),
                Base64Url::decode($this->member($response, 'authenticatorData')),
                Base64Url::decode($this->member($response, 'signature')),
                $challenge,
                $passkey->coseKey(),
                $passkey->signCount(),
            );
        } catch (MalformedInput|VerificationFailed) {
            throw Refused::because('passkey.refused');
        }

        $passkey->recordUse($assertion->signCount, $assertion->backedUp, $this->clock->now());
        $this->passkeys->save($passkey);

        return [$account, $assertion->userVerified];
    }

    /**
     * @return list<Passkey>
     */
    public function of(Account $account): array
    {
        return $this->passkeys->forAccount($account->id());
    }

    /**
     * @throws Refused when the name is empty or too long
     */
    public function rename(Account $account, string $passkeyId, string $name): void
    {
        $passkey = $this->owned($account, $passkeyId);

        try {
            $passkey->rename($name);
        } catch (InvalidValue) {
            throw Refused::because('passkey.name_invalid');
        }

        $this->passkeys->save($passkey);
    }

    /**
     * @throws Refused when the passkey is not the account's, or it is its last way to sign in
     */
    public function remove(Account $account, string $passkeyId): void
    {
        $passkey = $this->owned($account, $passkeyId);

        if (!$account->hasPassword() && 1 === \count($this->passkeys->forAccount($account->id()))) {
            throw Refused::because('passkey.last');
        }

        $this->passkeys->remove($passkey);
        $this->journal->record(IdentityEventType::PasskeyRemoved, $account->id(), ['passkey' => $passkey->id()]);
    }

    private function owned(Account $account, string $passkeyId): Passkey
    {
        $passkey = array_find($this->passkeys->forAccount($account->id()), static fn (Passkey $passkey): bool => $passkey->id() === $passkeyId);

        return $passkey ?? throw Refused::because('passkey.unknown');
    }

    private function challengeFor(string $purpose, string $accountId): string
    {
        $stored = $this->challenges->take($purpose);
        [$owner, $challenge] = explode(' ', $stored ?? '', 2) + [1 => ''];

        if (null === $stored || $owner !== $accountId || '' === $challenge) {
            throw Refused::because('passkey.expired');
        }

        return $challenge;
    }

    /**
     * @return array<array-key, mixed> the `response` member, with `rawId` beside it
     */
    private function response(string $json): array
    {
        if (\strlen($json) > self::MAX_RESPONSE) {
            throw Refused::because('passkey.refused');
        }

        try {
            $credential = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw Refused::because('passkey.refused');
        }

        if (!\is_array($credential) || !\is_array($credential['response'] ?? null) || 'public-key' !== ($credential['type'] ?? null)) {
            throw Refused::because('passkey.refused');
        }

        return $credential['response'] + ['rawId' => $credential['rawId'] ?? null];
    }

    /**
     * @param array<array-key, mixed> $response
     */
    private function member(array $response, string $name): string
    {
        $value = $response[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : throw MalformedInput::because(\sprintf('The response has a "%s".', $name));
    }

    /**
     * @param array<array-key, mixed> $response
     */
    private function optionalMember(array $response, string $name): ?string
    {
        $value = $response[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
