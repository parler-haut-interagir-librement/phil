<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\DeliveryFailed;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentities;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\PasswordRules;
use Phil\IdentityProviderBundle\Application\PhoneVerification;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Application\SignedLinks;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ArrayChallengeStore;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Authenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\CountingAccounts;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\CountingFactors;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\RecordingSms;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Signature\SignatureHasher;

/**
 * What the services of the application write, when they write it and with which context, down to the limits of their
 * defaults.
 */
#[CoversClass(Accounts::class)]
#[CoversClass(ClientRegistry::class)]
#[CoversClass(DeliveryFailed::class)]
#[CoversClass(ExternalIdentities::class)]
#[CoversClass(OAuthError::class)]
#[CoversClass(Passkeys::class)]
#[CoversClass(PasswordRules::class)]
#[CoversClass(PhoneVerification::class)]
#[CoversClass(Refused::class)]
#[CoversClass(SecondFactors::class)]
#[CoversClass(Sessions::class)]
#[CoversClass(SignedLinks::class)]
#[CoversClass(SigningKeys::class)]
final class ApplicationDetailsTest extends TestCase
{
    public function testAnAccountIsSavedAndJournaledAfterEachChange(): void
    {
        $services = new Services();
        $store = new CountingAccounts();
        $accounts = new Accounts($store, $services->hasher, $services->rules, $services->journal, $services->clock, new LockoutPolicy(1, 60, 3600));
        $account = $accounts->register('ada@example.com', Services::STRONG, 'Ada');
        self::assertNotNull($account);
        self::assertSame(1, $store->saves);

        $accounts->rename($account, 'Ada Lovelace');
        self::assertSame(2, $store->saves);
        self::assertSame('Ada Lovelace', $store->get($account->id())?->displayName());

        $accounts->changePassword($account, 'another long passphrase 7 violet', Services::STRONG);
        self::assertSame(3, $store->saves);
        self::assertSame('another long passphrase 7 violet', $store->get($account->id())?->passwordHash());
        self::assertSame([IdentityEventType::PasswordChanged->value, $account->id(), []], $services->audit->last());

        $accounts->removePassword($account);
        self::assertSame(4, $store->saves);
        self::assertNull($store->get($account->id())?->passwordHash());
        self::assertSame([IdentityEventType::PasswordChanged->value, $account->id(), ['removed' => true]], $services->audit->last());
    }

    public function testAnAccountThatIsHandedBackIsSaved(): void
    {
        $services = new Services();
        $store = new CountingAccounts();
        $accounts = new Accounts($store, $services->hasher, $services->rules, $services->journal, $services->clock, new LockoutPolicy());
        $account = $accounts->register('ada@example.com', Services::STRONG);
        self::assertNotNull($account);
        $account->grantRoles(['ROLE_ADMIN']);
        $saves = $store->saves;

        $accounts->save($account);

        self::assertSame($saves + 1, $store->saves);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $store->get($account->id())?->roles());
    }

    public function testASignInIsRecordedOnlyWhenThereIsSomethingToReset(): void
    {
        $services = new Services();
        $store = new CountingAccounts();
        $accounts = new Accounts($store, $services->hasher, $services->rules, $services->journal, $services->clock, new LockoutPolicy(2, 60, 3600));
        $account = $accounts->register('ada@example.com', Services::STRONG);
        self::assertNotNull($account);
        $saves = $store->saves;

        $accounts->recordSignIn($account);
        self::assertSame($saves, $store->saves);

        $accounts->recordFailedSignIn($account);
        self::assertSame($saves + 1, $store->saves);
        self::assertSame(1, $store->get($account->id())?->failedSignIns());
        self::assertSame([IdentityEventType::SignInFailed->value, $account->id(), ['failures' => 1]], $services->audit->last());
        self::assertNotContains(IdentityEventType::AccountLocked->value, $services->audit->actions());

        $accounts->recordSignIn($account);
        self::assertSame($saves + 2, $store->saves);
        self::assertSame(0, $store->get($account->id())?->failedSignIns());

        $accounts->recordFailedSignIn($account);
        $accounts->recordFailedSignIn($account);
        self::assertSame([IdentityEventType::AccountLocked->value, $account->id(), ['until' => $services->clock->now()->getTimestamp() + 60]], $services->audit->last());
        $accounts->recordFailedSignIn($account);
        self::assertSame(1, array_count_values($services->audit->actions())[IdentityEventType::AccountLocked->value]);

        $locked = $store->get($account->id());
        self::assertNotNull($locked);
        new ReflectionProperty(Account::class, 'failedSignIns')->setValue($locked, 0);
        $store->save($locked);
        $saves = $store->saves;
        $accounts->recordSignIn($locked);
        self::assertSame($saves + 1, $store->saves);
        self::assertNull($store->get($account->id())?->lockedUntil());
    }

    public function testASessionIsSeenAgainAfterTheDefaultInterval(): void
    {
        $services = new Services();
        $store = new InMemoryAccountSessionRepository();
        $sessions = new Sessions($store, $services->journal, $services->clock);
        $account = $services->account();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $opened = $session->lastSeenAt()->getTimestamp();

        $services->clock->modify('+299 seconds');
        $sessions->touch($session->id());
        self::assertSame($opened, $store->get($session->id())?->lastSeenAt()->getTimestamp());
        $services->clock->modify('+1 second');
        $sessions->touch($session->id());
        self::assertSame($opened + 300, $store->get($session->id())->lastSeenAt()->getTimestamp());
    }

    public function testAnUnknownDeviceIsJournaledWithItsSession(): void
    {
        $services = new Services();
        $sessions = new Sessions(new InMemoryAccountSessionRepository(), $services->journal, $services->clock);
        $account = $services->account();
        $sessions->open($account, 'Firefox on Linux', []);
        $risky = $sessions->open($account, 'Safari on iOS', []);

        self::assertSame([IdentityEventType::RiskySignIn->value, $account->id(), ['session' => $risky->id()]], $services->audit->last());
    }

    public function testTheAuthenticatorApplicationAcceptsOnePeriodOfDriftByDefault(): void
    {
        foreach ([[-30, true], [30, true], [-60, false], [60, false]] as [$drift, $accepted]) {
            $services = new Services();
            $factors = new SecondFactors(new CountingFactors()->inner, new RecordingCipher(), new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
            $account = $services->account();
            $now = $services->clock->now()->getTimestamp();
            $totp = Totp::fromBase32($factors->enroll($account)['secret']);
            try {
                $factors->confirm($account, $totp->at($now + $drift), false);
                self::assertTrue($accepted, 'Accepted beyond the window.');
            } catch (Refused) {
                self::assertFalse($accepted, 'Refused inside the window.');
            }
        }
    }

    public function testAPendingSecretIsRemovedBeforeTheNextOneIsSavedAndSealedForItsAccount(): void
    {
        $services = new Services();
        $store = new CountingFactors();
        $cipher = new RecordingCipher();
        $factors = new SecondFactors($store, $cipher, new RecoveryCodes(str_repeat('k', 32)), $services->journal, $services->clock, 'Example');
        $account = $services->account();

        $factors->enroll($account);
        self::assertSame(['save'], $store->operations);
        $factors->enroll($account);
        self::assertSame(['save', 'remove', 'save'], $store->operations);
        self::assertSame(['totp:'.$account->id(), 'totp:'.$account->id()], $cipher->contexts);
    }

    public function testALinkIsSignedWithAKeyOfItsPurpose(): void
    {
        $services = new Services('2100-01-01 00:00:00');
        $account = $services->account();
        $secret = str_repeat('s', 32);
        $links = new SignedLinks($secret, $services->clock);
        $now = $services->clock->now()->getTimestamp();

        $verify = $links->sign(SignedLinks::VERIFY, $account);
        $reset = $links->sign(SignedLinks::RESET, $account);
        self::assertSame($now + 86_400, $verify['expires']);
        self::assertSame($now + 3_600, $reset['expires']);

        foreach ([[SignedLinks::VERIFY, ['email', 'emailVerifiedAt'], $verify], [SignedLinks::RESET, ['password', 'passwordChangedAt'], $reset]] as [$purpose, $properties, $link]) {
            $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), $properties, hash_hmac('sha256', 'phil_identity_provider.'.$purpose, $secret));
            self::assertSame($hasher->computeSignatureHash(new AccountUser($account), $link['expires']), $link['signature']);
        }
    }

    public function testALinkIsValidUntilItsExpiryAndRefusedAfterIt(): void
    {
        $services = new Services('2100-01-01 00:00:00');
        $account = $services->account();
        $links = new SignedLinks(str_repeat('s', 32), $services->clock, 100, 50);
        $link = $links->sign(SignedLinks::VERIFY, $account);

        $services->clock->modify('+100 seconds');
        self::assertTrue($links->verify(SignedLinks::VERIFY, $account, $link['expires'], $link['signature']));
        $services->clock->modify('+1 second');
        self::assertFalse($links->verify(SignedLinks::VERIFY, $account, $link['expires'], $link['signature']));
    }

    public function testALinkTheSystemClockFindsExpiredIsRefusedToo(): void
    {
        $services = new Services('2000-01-01 00:00:00');
        $account = $services->account();
        $links = new SignedLinks(str_repeat('s', 32), $services->clock);
        $link = $links->sign(SignedLinks::VERIFY, $account);

        self::assertFalse($links->verify(SignedLinks::VERIFY, $account, $link['expires'], $link['signature']));
    }

    public function testAPasswordIsCountedInLettersAndBoundedAt4096(): void
    {
        $services = new Services();
        $rules = $services->rules;

        self::assertSame(['password.too_short'], $rules->problems('éééé'));
        self::assertNotSame(['password.too_short'], $rules->problems('éééééééé'));
        self::assertSame(['password.too_long'], $rules->problems(str_repeat('é', PasswordRules::MAX_LENGTH + 1)));
        self::assertNotSame(['password.too_long'], $rules->problems(str_repeat('é', PasswordRules::MAX_LENGTH)));
    }

    public function testARotatedSecretHas43Characters(): void
    {
        $services = new Services();
        $registry = new ClientRegistry(new InMemoryClientRepository(), $services->clock, new ProviderSettings('https://id.example'), $services->journal);
        [$client, $secret] = $registry->register('app', 'App', ['https://app.example/cb']);
        self::assertNotNull($secret);
        self::assertSame(43, \strlen($secret));

        self::assertSame(43, \strlen($registry->rotateSecret($client)));
    }

    public function testASigningKeyIsRemovedOnceItsRetentionIsOver(): void
    {
        $services = new Services();
        $store = new InMemorySigningKeyRepository();
        $cipher = new RecordingCipher();
        $keys = new SigningKeys($store, $cipher, new ProviderSettings('https://id.example', SigningAlgorithm::ES256, keyActivationDelay: 100, retiredKeyRetention: 200), $services->clock, $services->journal);

        $first = $keys->active();
        self::assertSame(['signing-key:'.$first->kid, 'signing-key:'.$first->kid], $cipher->contexts);
        $keys->rotate();
        $services->clock->modify('+100 seconds');
        $result = $keys->rotate();
        self::assertIsString($result['activated']);
        self::assertSame([IdentityEventType::SigningKeyRotated->value, null, ['activated' => $result['activated'], 'created' => $result['created'], 'removed' => 0]], $services->audit->last());
        $published = \count($store->all());

        $services->clock->modify('+200 seconds');
        self::assertSame(0, $keys->rotate()['removed']);
        self::assertGreaterThanOrEqual($published, \count($store->all()));
        $services->clock->modify('+1 second');
        self::assertSame(1, $keys->rotate()['removed']);
        self::assertNull($store->get($first->kid));
    }

    public function testRotatingWithoutAJournal(): void
    {
        $services = new Services();
        $keys = new SigningKeys(new InMemorySigningKeyRepository(), new RecordingCipher(), new ProviderSettings('https://id.example', SigningAlgorithm::ES256), $services->clock);

        self::assertIsString($keys->rotate()['created']);
    }

    public function testTheCodeOfAPhoneIsSixDigitsHashedWithItsAccountAndNumber(): void
    {
        $services = new Services();
        $sms = new RecordingSms();
        $challenges = new ArrayChallengeStore();
        $verification = new PhoneVerification($sms, $challenges, $services->accounts, $services->journal, $services->clock);
        $account = $services->account();

        $digits = [];

        for ($i = 0; $i < 300; ++$i) {
            $verification->start($account, '+15145550123');
            self::assertMatchesRegularExpression('/^\d{6}$/D', $sms->lastCode());
            $digits += array_flip(str_split($sms->lastCode()));
        }

        ksort($digits);
        self::assertSame(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], array_map(strval(...), array_keys($digits)));

        [$owner, $phone, $hash, $expires, $left] = explode(' ', $challenges->values['phone.verify']);
        self::assertSame([$account->id(), '+15145550123'], [$owner, $phone]);
        self::assertSame(hash('sha256', $account->id()."\0+15145550123\0".$sms->lastCode()), $hash);
        self::assertSame((string) ($services->clock->now()->getTimestamp() + 600), $expires);
        self::assertSame('5', $left);
    }

    public function testACodeLastsTenMinutesAndAllowsFiveTries(): void
    {
        $services = new Services();
        $sms = new RecordingSms();
        $verification = new PhoneVerification($sms, new ArrayChallengeStore(), $services->accounts, $services->journal, $services->clock);
        $account = $services->account();
        $verification->start($account, '+15145550123');
        $code = $sms->lastCode();
        $wrong = '000000' === $code ? '111111' : '000000';

        for ($i = 1; $i <= 4; ++$i) {
            try {
                $verification->confirm($account, $wrong);
                self::fail('Accepted.');
            } catch (Refused $refused) {
                self::assertSame(['phone.invalid_code'], $refused->reasons);
                self::assertSame('+15145550123', $verification->pending($account), $i.' tries made');
            }
        }

        try {
            $verification->confirm($account, $wrong);
            self::fail('Accepted.');
        } catch (Refused) {
            self::assertNull($verification->pending($account));
        }

        $verification->start($account, '+15145550123');
        $services->clock->modify('+600 seconds');
        self::assertSame('+15145550123', $verification->confirm($account, $sms->lastCode()));
        self::assertSame('+15145550123', $services->accounts->get($account->id())?->phone());

        $verification->remove($account);
        self::assertNull($services->accounts->get($account->id())?->phone());
    }

    public function testACodeThatExpiredIsNeverAccepted(): void
    {
        for ($i = 0; $i < 30; ++$i) {
            $services = new Services();
            $sms = new RecordingSms();
            $verification = new PhoneVerification($sms, new ArrayChallengeStore(), $services->accounts, $services->journal, $services->clock, lifetime: 60);
            $account = $services->account();
            $verification->start($account, '+15145550123');
            $services->clock->modify('+61 seconds');

            try {
                $verification->confirm($account, $sms->lastCode());
                self::fail('Accepted after its expiry.');
            } catch (Refused $refused) {
                self::assertSame(['phone.expired'], $refused->reasons);
            }
        }
    }

    public function testDeliveryFailureHasNoCode(): void
    {
        self::assertSame(0, DeliveryFailed::because('down')->getCode());
        self::assertSame('down', DeliveryFailed::because('down')->getMessage());
    }

    public function testTheOAuthErrorsCarryTheirStatus(): void
    {
        self::assertSame([400, 'invalid_request: no'], [OAuthError::invalidRequest('no')->status, OAuthError::invalidRequest('no')->getMessage()]);
        self::assertSame([401, 'invalid_client: The client could not be authenticated.'], [OAuthError::invalidClient()->status, OAuthError::invalidClient()->getMessage()]);
        self::assertSame(400, OAuthError::invalidGrant('no')->status);
        self::assertSame(400, OAuthError::unsupportedGrantType()->status);
        self::assertSame(400, OAuthError::invalidScope('no')->status);
        self::assertSame(401, OAuthError::invalidToken()->status);
        self::assertSame(['error' => 'invalid_grant', 'error_description' => 'no'], OAuthError::invalidGrant('no')->toArray());
    }

    public function testRefusalsAreAListOfReasons(): void
    {
        $refused = Refused::because('a', 'b');

        self::assertSame(['a', 'b'], $refused->reasons);
        self::assertSame('a, b', $refused->getMessage());
    }

    public function testExternalIdentitiesDefaultToNoMergeAndOpenRegistration(): void
    {
        $services = new Services();
        $identities = new ExternalIdentities(new InMemoryLinkedIdentityRepository(), $services->accounts, null, $services->accounts(), $services->journal, $services->clock);
        $known = $services->account();
        $known->verifyEmail($services->clock->now());

        $services->accounts->save($known);

        try {
            $identities->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', true));
            self::fail('Merged.');
        } catch (Refused $refused) {
            self::assertSame(['external.email_known'], $refused->reasons);
        }

        self::assertNotContains(IdentityEventType::RegistrationForKnownEmail->value, $services->audit->actions());
        self::assertTrue($identities->resolve(new ExternalIdentity('google', 'sub-2', 'new@example.com', true))->created);
    }

    public function testAnAddressRegisteredMeanwhileIsRefused(): void
    {
        $services = new Services();
        $services->account();
        // The lookup of the identity finds nobody, the registration finds the address taken: a registration that came in between.
        $blind = new CountingAccounts();
        $identities = new ExternalIdentities(new InMemoryLinkedIdentityRepository(), $blind, null, $services->accounts(), $services->journal, $services->clock);

        $this->expectException(Refused::class);
        $this->expectExceptionMessageIsOrContains('external.email_known');

        $identities->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', true));
    }

    public function testASignInByPasskeyAcceptsAResponseUpToItsLimitAndEightLevels(): void
    {
        [$passkeys, $authenticator, $account, $challenges] = $this->passkeys();
        $sign = static function (?int $length = null, int $levels = 0) use ($passkeys, $authenticator): string {
            $options = $passkeys->signInOptions();
            \assert(\is_string($options['challenge']));
            $response = json_decode($authenticator->sign($options['challenge']), true);
            \assert(\is_array($response));

            if ($levels > 0) {
                $extra = 1;

                for ($i = 0; $i < $levels; ++$i) {
                    $extra = ['x' => $extra];
                }

                $response['extra'] = $extra;
            }

            $json = json_encode($response, \JSON_THROW_ON_ERROR);

            return null === $length ? $json : str_pad($json, $length);
        };

        $answer = $sign(Passkeys::MAX_RESPONSE);
        self::assertSame(Passkeys::MAX_RESPONSE, \strlen($answer));
        self::assertSame($account->id(), $passkeys->signIn($answer)[0]->id());
        self::assertSame([], $challenges->values);

        $answer = $sign(Passkeys::MAX_RESPONSE + 1);
        self::assertSame(Passkeys::MAX_RESPONSE + 1, \strlen($answer));
        $this->assertRefused('passkey.refused', static fn (): array => $passkeys->signIn($answer));

        // The depth counts the object, the extra members and the value at the end: eight levels at most.
        self::assertSame($account->id(), $passkeys->signIn($sign(null, 6))[0]->id());
        $this->assertRefused('passkey.refused', static fn (): array => $passkeys->signIn($sign(null, 7)));
    }

    public function testAChallengeIsEverythingAfterTheFirstSpace(): void
    {
        [$passkeys, $authenticator, $account, $challenges] = $this->passkeys();
        $challenges->put('passkey.sign_in', '- two words');

        self::assertSame($account->id(), $passkeys->signIn($authenticator->sign('two words'))[0]->id());
    }

    public function testASignInByPasskeyRefusesWhatIsMalformedInsideTheAnswer(): void
    {
        [$passkeys, $authenticator, $account] = $this->passkeys();
        $answer = static function (callable $change) use ($passkeys, $authenticator): string {
            $options = $passkeys->signInOptions();
            \assert(\is_string($options['challenge']));
            $response = json_decode($authenticator->sign($options['challenge']), true);
            \assert(\is_array($response) && \is_array($response['response']));
            $response['response'] = $change($response['response']);

            return json_encode($response, \JSON_THROW_ON_ERROR);
        };

        foreach ([
            static fn (array $r): array => ['authenticatorData' => Base64Url::encode('short')] + $r,
            static fn (array $r): array => ['signature' => ''] + $r,
            static fn (array $r): array => ['signature' => 5] + $r,
            static fn (array $r): array => ['clientDataJSON' => ''] + $r,
            static fn (array $r): array => ['userHandle' => Base64Url::encode('someone else')] + $r,
        ] as $change) {
            $this->assertRefused('passkey.refused', static fn (): array => $passkeys->signIn($answer($change)));
        }

        // An empty user handle is no user handle.
        self::assertSame($account->id(), $passkeys->signIn($answer(static fn (array $r): array => ['userHandle' => ''] + $r))[0]->id());
    }

    public function testASignInByPasskeyOfAnAccountThatIsGoneIsRefused(): void
    {
        [$passkeys, $authenticator, $account, , $services] = $this->passkeys();
        $options = $passkeys->signInOptions();
        \assert(\is_string($options['challenge']));
        $services->accounts->remove($account);

        $this->assertRefused('passkey.refused', static fn (): array => $passkeys->signIn($authenticator->sign($options['challenge'])));
    }

    /**
     * @return array{Passkeys, Authenticator, Account, ArrayChallengeStore, Services}
     */
    private function passkeys(): array
    {
        $services = new Services();
        $challenges = new ArrayChallengeStore();
        $passkeys = new Passkeys(new RelyingParty('example.com', 'Example', ['https://example.com']), new InMemoryPasskeyRepository(), $services->accounts, $challenges, $services->journal, $services->clock);
        $account = $services->account();
        $authenticator = new Authenticator();
        $options = $passkeys->registrationOptions($account);
        \assert(\is_string($options['challenge']));
        $passkeys->register($account, $authenticator->register($options['challenge']), 'Phone');

        return [$passkeys, $authenticator, $account, $challenges, $services];
    }

    private function assertRefused(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$reason);
        } catch (Refused $refused) {
            self::assertSame([$reason], $refused->reasons);
        }
    }
}
