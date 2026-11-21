<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Account\AccountState;
use Phil\IdentityProviderBundle\Domain\Account\AddressPurpose;
use Phil\IdentityProviderBundle\Domain\Contract\AccountAddressRepository;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Contract\ClientRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkedIdentityRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkTokenRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Contract\SecondFactorRepository;
use Phil\IdentityProviderBundle\Domain\Contract\SigningKeyRepository;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Account\AccountAddress;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\InterleavedGrants;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * What every store keeps and reads back, the same in memory and in each database: the rows written, read again once
 * the unit of work is cleared, in the order each list promises.
 */
abstract class RepositoryTestCase extends TestCase
{
    protected const string ADA = '01JCZ6Q7Y3W0000000000000AA';

    protected const string BOB = '01JCZ6Q7Y3W0000000000000BB';

    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    abstract protected function accounts(): AccountRepository;

    abstract protected function passkeys(): PasskeyRepository;

    abstract protected function factors(): SecondFactorRepository;

    abstract protected function sessions(): AccountSessionRepository;

    abstract protected function identities(): LinkedIdentityRepository;

    abstract protected function clients(): ClientRepository;

    abstract protected function grants(): GrantRepository;

    abstract protected function keys(): SigningKeyRepository;

    abstract protected function transactions(): Transactions;

    abstract protected function addresses(): AccountAddressRepository;

    abstract protected function linkTokens(): LinkTokenRepository;

    /**
     * Forgets what the store holds in memory, so that the next read comes from its storage.
     */
    abstract protected function clear(): void;

    /**
     * The keys compare byte by byte in every store: on MySQL and MariaDB, through the binary collation the bundle gives
     * them.
     */
    public function testTheKeysCompareByteByByte(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->accounts()->save(Account::register(self::ADA, 'josé@example.com', $now), Account::register(self::BOB, 'jose@example.com', $now));
        $this->clients()->save(Client::register('App', 'Upper', null, ['https://a/cb'], [], [], false, $now), Client::register('app', 'Lower', null, ['https://a/cb'], [], [], false, $now));
        $this->identities()->save(LinkedIdentity::link(self::ADA, 'example', 'AbC', $now), LinkedIdentity::link(self::BOB, 'example', 'abc', $now));
        $this->clear();

        self::assertSame(self::ADA, $this->accounts()->findByEmail('josé@example.com')?->id());
        self::assertSame(self::BOB, $this->accounts()->findByEmail('jose@example.com')?->id());
        self::assertSame('Upper', $this->clients()->get('App')?->name());
        self::assertSame('Lower', $this->clients()->get('app')?->name());
        self::assertNull($this->clients()->get('APP'));
        self::assertSame(self::ADA, $this->identities()->find('example', 'AbC')?->accountId());
        self::assertSame(self::BOB, $this->identities()->find('example', 'abc')?->accountId());
        self::assertNull($this->identities()->find('example', 'ABC'));
        self::assertNull($this->accounts()->get(strtolower(self::ADA)));
    }

    public function testAccounts(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $ada = Account::register(self::ADA, 'ada@example.com', $now, 'Ada');
        $ada->changePassword('$argon2id$hash', $now);
        $ada->grantRoles(['ROLE_ADMIN']);

        $bob = Account::register(self::BOB, 'bob@example.com', $now);
        $this->accounts()->save($ada, $bob);
        $this->clear();

        $found = $this->accounts()->get(self::ADA);
        self::assertNotNull($found);
        self::assertSame(['ada@example.com', 'Ada', '$argon2id$hash', ['ROLE_USER', 'ROLE_ADMIN']], [$found->email(), $found->displayName(), $found->passwordHash(), $found->roles()]);
        self::assertSame($now->getTimestamp(), $found->createdAt()->getTimestamp());
        self::assertSame(self::BOB, $this->accounts()->findByEmail(' BOB@example.com ')?->id());
        self::assertNull($this->accounts()->findByEmail('nobody@example.com'));
        self::assertNull($this->accounts()->get('01JCZ6Q7Y3W0000000000000ZZ'));

        $found->verifyEmail($now);
        $this->accounts()->save($found);
        $this->clear();
        self::assertTrue($this->accounts()->get(self::ADA)?->isEmailVerified());

        $bob = $this->accounts()->get(self::BOB);
        self::assertNotNull($bob);
        $this->accounts()->remove($bob);
        $this->clear();
        self::assertNull($this->accounts()->get(self::BOB));
    }

    public function testPasskeys(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $first = Passkey::register(self::ADA, new RegisteredCredential(str_repeat("\xFF", 64), 'cose-1', 0, str_repeat("\x01", 16), true, true, true), 'Phone', $now);
        $second = Passkey::register(self::ADA, new RegisteredCredential('second', 'cose-2', 5, str_repeat("\x02", 16), false, false, false), 'Key', $now->modify('+1 hour'));
        $other = Passkey::register(self::BOB, new RegisteredCredential('other', 'cose-3', 0, str_repeat("\x03", 16), false, false, false), 'Bob', $now);
        $this->passkeys()->save($second, $first, $other);
        $this->clear();

        $found = $this->passkeys()->findByCredentialId(str_repeat("\xFF", 64));
        self::assertNotNull($found);
        self::assertSame(['cose-1', 'Phone', true, '01010101-0101-0101-0101-010101010101'], [$found->coseKey(), $found->name(), $found->isBackedUp(), $found->aaguid()]);
        self::assertSame(['Phone', 'Key'], array_map(static fn (Passkey $passkey): string => $passkey->name(), $this->passkeys()->forAccount(self::ADA)));
        self::assertNull($this->passkeys()->findByCredentialId('unknown'));

        $found->recordUse(9, false, $now);
        $this->passkeys()->save($found);
        $this->clear();
        self::assertSame(9, $this->passkeys()->findByCredentialId(str_repeat("\xFF", 64))?->signCount());

        $found = $this->passkeys()->findByCredentialId('second');
        self::assertNotNull($found);
        $this->passkeys()->remove($found);
        $this->clear();
        self::assertCount(1, $this->passkeys()->forAccount(self::ADA));
    }

    public function testSecondFactors(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $totp = TotpCredential::enroll(self::ADA, 'plain.SECRET', $now);
        $codes = RecoveryCodeSet::generate(self::ADA, ['a', 'b'], $now);
        $this->factors()->save($totp, $codes);
        $this->clear();

        $found = $this->factors()->totp(self::ADA);
        self::assertNotNull($found);
        self::assertSame(['plain.SECRET', false], [$found->storedSecret(), $found->isConfirmed()]);
        $found->accept(57_000_000_000, $now);
        $set = $this->factors()->recoveryCodes(self::ADA);
        self::assertNotNull($set);
        $set->use(0);
        $this->factors()->save($found, $set);
        $this->clear();

        self::assertSame(57_000_000_000, $this->factors()->totp(self::ADA)?->lastCounter());
        self::assertSame([null, 'b'], $this->factors()->recoveryCodes(self::ADA)?->hashes());
        self::assertNull($this->factors()->totp(self::BOB));
        self::assertNull($this->factors()->recoveryCodes(self::BOB));

        $found = $this->factors()->totp(self::ADA);
        $set = $this->factors()->recoveryCodes(self::ADA);
        self::assertNotNull($found);
        self::assertNotNull($set);
        $this->factors()->remove($found);
        $this->factors()->remove($set);
        $this->clear();
        self::assertNull($this->factors()->totp(self::ADA));
        self::assertNull($this->factors()->recoveryCodes(self::ADA));
    }

    public function testSessions(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $old = AccountSession::open(str_repeat('a', 43), self::ADA, 'Firefox on Linux', ['pwd'], false, $now);
        $recent = AccountSession::open(str_repeat('b', 43), self::ADA, 'Safari on iOS', ['hwk', 'user'], true, $now->modify('+1 hour'));
        $closed = AccountSession::open(str_repeat('c', 43), self::ADA, 'Chrome on Windows', [], false, $now);
        $closed->close($now);
        $this->sessions()->save($old, $recent, $closed);
        $this->clear();

        self::assertSame([str_repeat('b', 43), str_repeat('a', 43)], array_map(static fn (AccountSession $session): string => $session->id(), $this->sessions()->openFor(self::ADA)));
        self::assertSame(['hwk', 'user'], $this->sessions()->get(str_repeat('b', 43))?->methods());
        self::assertTrue($this->sessions()->hasAny(self::ADA));
        self::assertFalse($this->sessions()->hasAny(self::BOB));
        self::assertTrue($this->sessions()->knowsDevice(self::ADA, 'Chrome on Windows'));
        self::assertFalse($this->sessions()->knowsDevice(self::ADA, 'Edge on Windows'));
        self::assertFalse($this->sessions()->knowsDevice(self::BOB, 'Safari on iOS'));
        self::assertNull($this->sessions()->get(str_repeat('z', 43)));

        self::assertSame(2, $this->sessions()->purge($now->modify('+30 minutes')));
        $this->clear();
        self::assertSame([str_repeat('b', 43)], array_map(static fn (AccountSession $session): string => $session->id(), $this->sessions()->openFor(self::ADA)));
        self::assertSame(0, $this->sessions()->purge($now));
    }

    public function testLinkedIdentities(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $google = LinkedIdentity::link(self::ADA, 'google', 'g-1', $now);
        $github = LinkedIdentity::link(self::ADA, 'github', 'h-1', $now->modify('+1 hour'));
        $this->identities()->save($github, $google);
        $this->clear();

        self::assertSame(self::ADA, $this->identities()->find('google', 'g-1')?->accountId());
        self::assertNull($this->identities()->find('google', 'h-1'));
        self::assertSame(['google', 'github'], array_map(static fn (LinkedIdentity $identity): string => $identity->provider(), $this->identities()->forAccount(self::ADA)));

        $found = $this->identities()->find('google', 'g-1');
        self::assertNotNull($found);
        $this->identities()->remove($found);
        $this->clear();
        self::assertCount(1, $this->identities()->forAccount(self::ADA));
    }

    public function testClientsAndConsents(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->clients()->save(Client::register('zeta', 'Zeta', null, ['https://z/cb'], [], [], false, $now), Client::register('alpha', 'Alpha', 'secret', ['https://a/cb'], ['https://a/out'], ['email'], true, $now));
        $this->clear();

        self::assertSame(['alpha', 'zeta'], array_map(static fn (Client $client): string => $client->id(), $this->clients()->all()));
        $alpha = $this->clients()->get('alpha');
        self::assertNotNull($alpha);
        self::assertSame([['https://a/cb'], ['https://a/out'], ['openid', 'email'], true, true], [$alpha->redirectUris(), $alpha->postLogoutRedirectUris(), $alpha->scopes(), $alpha->isFirstParty(), $alpha->verifySecret('secret')]);
        self::assertNull($this->clients()->get('nobody'));

        $this->clients()->saveConsent(Consent::grant(self::ADA, 'alpha', ['openid'], $now));
        $this->clients()->saveConsent(Consent::grant(self::ADA, 'zeta', ['openid', 'email'], $now->modify('+1 hour')));
        $this->clear();
        self::assertSame(['zeta', 'alpha'], array_map(static fn (Consent $consent): string => $consent->clientId(), $this->clients()->consentsOf(self::ADA)));
        self::assertSame(['openid', 'email'], $this->clients()->consent(self::ADA, 'zeta')?->scopes());
        self::assertNull($this->clients()->consent(self::BOB, 'zeta'));

        $consent = $this->clients()->consent(self::ADA, 'alpha');
        self::assertNotNull($consent);
        $this->clients()->removeConsent($consent);
        $this->clear();
        self::assertNull($this->clients()->consent(self::ADA, 'alpha'));
        $zeta = $this->clients()->get('zeta');
        self::assertNotNull($zeta);
        $this->clients()->remove($zeta);
        $this->clear();
        self::assertNull($this->clients()->consent(self::ADA, 'alpha'));
        self::assertSame(['alpha'], array_map(static fn (Client $client): string => $client->id(), $this->clients()->all()));
    }

    public function testGrants(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->grants()->saveCode(AuthorizationCode::issue('code', 'app', self::ADA, 'https://a/cb?x=1', ['openid'], 'nonce', 'challenge', $now, ['pwd'], str_repeat('s', 43), 'FAMILY0000000000000000000A', $now->modify('+60 seconds')));
        $access = IssuedToken::issue('jti-1', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, ['openid'], str_repeat('s', 43), $now, ['pwd'], $now, $now->modify('+10 minutes'));
        $refresh = IssuedToken::issue(IssuedToken::idOfRefresh('r'), TokenKind::Refresh, 'FAMILY0000000000000000000A', 'other', self::ADA, ['openid', 'offline_access'], null, $now, [], $now->modify('+1 second'), $now->modify('+30 days'));
        $foreign = IssuedToken::issue('jti-2', TokenKind::Access, 'FAMILY0000000000000000000B', 'app', self::BOB, [], null, $now, [], $now, $now->modify('+10 minutes'));
        $this->grants()->saveToken($refresh, $access, $foreign);
        $this->clear();

        $code = $this->grants()->code(AuthorizationCode::idOf('code'));
        self::assertNotNull($code);
        self::assertSame(['https://a/cb?x=1', 'nonce', 'challenge', ['pwd'], str_repeat('s', 43), false], [$code->redirectUri(), $code->nonce(), $code->codeChallenge(), $code->methods(), $code->sessionId(), $code->isUsed()]);
        $code->use($now);
        $this->grants()->saveCode($code);
        $this->clear();
        self::assertTrue($this->grants()->code(AuthorizationCode::idOf('code'))?->isUsed());
        self::assertNull($this->grants()->code('unknown'));

        self::assertSame(TokenKind::Refresh, $this->grants()->token(IssuedToken::idOfRefresh('r'))?->kind());
        self::assertSame(['jti-1', IssuedToken::idOfRefresh('r')], array_map(static fn (IssuedToken $token): string => $token->id(), $this->grants()->family('FAMILY0000000000000000000A')));
        self::assertSame(['jti-1', IssuedToken::idOfRefresh('r')], array_map(static fn (IssuedToken $token): string => $token->id(), $this->grants()->activeFor(self::ADA)));
        self::assertSame(['jti-1'], array_map(static fn (IssuedToken $token): string => $token->id(), $this->grants()->activeFor(self::ADA, 'app')));
        self::assertSame(['jti-1'], array_map(static fn (IssuedToken $token): string => $token->id(), $this->grants()->forSession(str_repeat('s', 43))));

        $token = $this->grants()->token('jti-1');
        self::assertNotNull($token);
        $token->revoke($now);
        $this->grants()->saveToken($token);
        $this->clear();
        self::assertSame([IssuedToken::idOfRefresh('r')], array_map(static fn (IssuedToken $token): string => $token->id(), $this->grants()->activeFor(self::ADA)));
        self::assertSame([], $this->grants()->forSession(str_repeat('s', 43)));

        self::assertSame(3, $this->grants()->purge($now->modify('+11 minutes')));
        $this->clear();
        self::assertNull($this->grants()->code(AuthorizationCode::idOf('code')));
        self::assertNull($this->grants()->token('jti-2'));
        self::assertNotNull($this->grants()->token(IssuedToken::idOfRefresh('r')));
    }

    public function testARevokedFamilyIsMarkedAndCatchesTheTokensSavedAfterIt(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $family = 'FAMILY0000000000000000000A';
        $this->grants()->saveToken(IssuedToken::issue('jti-1', TokenKind::Access, $family, 'app', self::ADA, [], null, $now, [], $now, $now->modify('+10 minutes')));
        $this->grants()->saveToken(IssuedToken::issue('jti-2', TokenKind::Access, 'FAMILY0000000000000000000B', 'app', self::ADA, [], null, $now, [], $now, $now->modify('+10 minutes')));
        self::assertFalse($this->grants()->isFamilyRevoked($family));

        $this->grants()->revokeFamily($family, $now, $now->modify('+30 days'));
        $this->clear();
        self::assertTrue($this->grants()->isFamilyRevoked($family));
        self::assertFalse($this->grants()->isFamilyRevoked('FAMILY0000000000000000000B'));
        self::assertFalse($this->grants()->isFamilyRevoked(strtolower($family)), 'The family compares byte by byte.');
        self::assertTrue($this->grants()->token('jti-1')?->isRevoked());
        self::assertFalse($this->grants()->token('jti-2')?->isRevoked());

        // The request that won saves its tokens after the revocation, reads the mark, and revokes the family again.
        $this->grants()->saveToken(IssuedToken::issue('jti-3', TokenKind::Access, $family, 'app', self::ADA, [], null, $now, [], $now->modify('+1 second'), $now->modify('+10 minutes')));
        self::assertTrue($this->grants()->isFamilyRevoked($family));
        $this->grants()->revokeFamily($family, $now->modify('+1 second'), $now->modify('+31 days'));
        $this->clear();
        self::assertSame(['jti-2'], $this->idsOf($this->grants()->activeFor(self::ADA, 'app')));

        self::assertSame(3, $this->grants()->purge($now->modify('+11 minutes')), 'The three tokens expired; the mark is kept.');
        self::assertTrue($this->grants()->isFamilyRevoked($family));
        self::assertSame(0, $this->grants()->purge($now->modify('+30 days')));
        self::assertSame(1, $this->grants()->purge($now->modify('+30 days +1 second')), 'The first mark is kept, not the one of the second revocation.');
        self::assertFalse($this->grants()->isFamilyRevoked($family));
    }

    public function testARefreshReplayedBetweenTheRotationAndTheSaveLeavesNoTokenOfTheFamilyActive(): void
    {
        [$tokens, $grants, $client, $code, $services] = $this->provider();
        $response = $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);
        $refresh = (string) $response['refresh_token'];
        $family = $grants->token(IssuedToken::idOfRefresh($refresh))?->family() ?? '';
        $loser = null;
        $grants->afterUse = static function () use ($tokens, $client, $refresh, &$loser): void {
            try {
                $tokens->refresh($client, $refresh, null);
            } catch (OAuthError $oAuthError) {
                $loser = $oAuthError->error;
            }
        };

        try {
            $tokens->refresh($client, $refresh, null);
            self::fail('The request that rotated the token keeps tokens of a revoked family.');
        } catch (OAuthError $oAuthError) {
            self::assertSame('invalid_grant', $oAuthError->error);
        }

        self::assertSame('invalid_grant', $loser);
        $this->clear();
        self::assertNotSame('', $family);
        self::assertSame([], $this->activeIn($family, $services->clock->now()));
    }

    public function testACodeReplayedBetweenItsUseAndTheSaveLeavesNoTokenOfTheFamilyActive(): void
    {
        [$tokens, $grants, $client, $code, $services] = $this->provider();
        $family = $grants->code(AuthorizationCode::idOf($code))?->family() ?? '';
        $loser = null;
        $grants->afterUse = static function () use ($tokens, $client, $code, &$loser): void {
            try {
                $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);
            } catch (OAuthError $oAuthError) {
                $loser = $oAuthError->error;
            }
        };

        try {
            $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);
            self::fail('The request that used the code keeps tokens of a revoked family.');
        } catch (OAuthError $oAuthError) {
            self::assertSame('invalid_grant', $oAuthError->error);
        }

        self::assertSame('invalid_grant', $loser);
        $this->clear();
        self::assertNotSame('', $family);
        self::assertSame([], $this->activeIn($family, $services->clock->now()));
    }

    public function testInstantsAreWrittenAndReadInUtcWhateverTheTimeZoneOfTheProcess(): void
    {
        $zone = date_default_timezone_get();

        try {
            date_default_timezone_set('America/Toronto');
            // 01:30 happens twice in Toronto on 2026-11-01: first in daylight time, then an hour later in standard time.
            $daylight = new DateTimeImmutable('@1793511000')->setTimezone(new DateTimeZone('America/Toronto'));
            $standard = new DateTimeImmutable('@1793514600')->setTimezone(new DateTimeZone('America/Toronto'));
            self::assertSame($daylight->format('Y-m-d H:i'), $standard->format('Y-m-d H:i'));
            $this->grants()->saveToken(
                IssuedToken::issue('jti-d', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], null, $daylight, [], $daylight, $daylight),
                IssuedToken::issue('jti-s', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], null, $standard, [], $standard, $standard),
            );
            $this->clear();

            date_default_timezone_set('UTC');
            self::assertSame($daylight->format('U.u'), $this->grants()->token('jti-d')?->expiresAt()->format('U.u'));
            self::assertSame($standard->format('U.u'), $this->grants()->token('jti-s')?->expiresAt()->format('U.u'));
            self::assertSame(1, $this->grants()->purge(new DateTimeImmutable('@1793512800')), 'Only the token expired in daylight time is past.');
            $this->clear();
            self::assertNull($this->grants()->token('jti-d'));
            self::assertNotNull($this->grants()->token('jti-s'));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testACodeIsUsedOnceAndARefreshTokenRotatedOnce(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->grants()->saveCode(AuthorizationCode::issue('code', 'app', self::ADA, 'https://a/cb', ['openid'], null, null, $now, [], null, 'FAMILY0000000000000000000A', $now->modify('+60 seconds')));
        $refresh = IssuedToken::issue(IssuedToken::idOfRefresh('r'), TokenKind::Refresh, 'FAMILY0000000000000000000A', 'app', self::ADA, ['offline_access'], null, $now, [], $now, $now->modify('+30 days'));
        $revoked = IssuedToken::issue(IssuedToken::idOfRefresh('gone'), TokenKind::Refresh, 'FAMILY0000000000000000000A', 'app', self::ADA, ['offline_access'], null, $now, [], $now, $now->modify('+30 days'));
        $revoked->revoke($now);
        $this->grants()->saveToken($refresh, $revoked);
        $this->clear();

        $code = $this->grants()->code(AuthorizationCode::idOf('code'));
        self::assertNotNull($code);
        self::assertTrue($this->grants()->consumeCode($code, $now));
        self::assertTrue($code->isUsed());
        self::assertFalse($this->grants()->consumeCode($code, $now));
        $this->clear();
        $stale = $this->grants()->code(AuthorizationCode::idOf('code'));
        self::assertNotNull($stale);
        self::assertTrue($stale->isUsed());
        self::assertFalse($this->grants()->consumeCode($stale, $now));
        self::assertFalse($this->grants()->consumeCode(AuthorizationCode::issue('unknown', 'app', self::ADA, 'https://a/cb', [], null, null, $now, [], null, 'FAMILY0000000000000000000A', $now), $now));

        $token = $this->grants()->token(IssuedToken::idOfRefresh('r'));
        self::assertNotNull($token);
        self::assertTrue($this->grants()->rotateToken($token, $now));
        self::assertTrue($token->isRotated());
        self::assertFalse($this->grants()->rotateToken($token, $now));
        $this->clear();
        self::assertTrue($this->grants()->token(IssuedToken::idOfRefresh('r'))?->isRotated());
        $gone = $this->grants()->token(IssuedToken::idOfRefresh('gone'));
        self::assertNotNull($gone);
        self::assertFalse($this->grants()->rotateToken($gone, $now));
        $this->clear();
        self::assertFalse($this->grants()->token(IssuedToken::idOfRefresh('gone'))?->isRotated());
    }

    public function testAClientAssertionIsRecordedOnceAndPurgedOnceExpired(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');

        self::assertTrue($this->grants()->useAssertion(UsedAssertion::record('machine', 'jti-1', $now->modify('+60 seconds'))));
        self::assertFalse($this->grants()->useAssertion(UsedAssertion::record('machine', 'jti-1', $now->modify('+90 seconds'))));
        self::assertTrue($this->grants()->useAssertion(UsedAssertion::record('other', 'jti-1', $now->modify('+120 seconds'))));
        $this->clear();
        self::assertFalse($this->grants()->useAssertion(UsedAssertion::record('machine', 'jti-1', $now)));

        self::assertSame(0, $this->grants()->purge($now->modify('+60 seconds')));
        self::assertSame(1, $this->grants()->purge($now->modify('+61 seconds')));
        self::assertTrue($this->grants()->useAssertion(UsedAssertion::record('machine', 'jti-1', $now->modify('+60 seconds'))));
        self::assertFalse($this->grants()->useAssertion(UsedAssertion::record('other', 'jti-1', $now)));
    }

    public function testATokenOfTheClientItselfKeepsItsAudience(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->grants()->saveToken(IssuedToken::issue('jti-c', TokenKind::Access, 'FAMILY0000000000000000000C', 'machine', null, ['events:read'], null, $now, [], $now, $now->modify('+10 minutes'), 'https://api.example/'));
        $this->clear();

        $token = $this->grants()->token('jti-c');
        self::assertNotNull($token);
        self::assertSame([null, 'https://api.example/', ['events:read']], [$token->accountId(), $token->audience(), $token->scopes()]);
    }

    public function testAChangeToAFactorAppliesToItsStoredRow(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->factors()->save(TotpCredential::enroll(self::ADA, 'plain.FIRST', $now), RecoveryCodeSet::generate(self::ADA, ['a', 'b'], $now));
        $this->clear();

        $totp = $this->factors()->totp(self::ADA);
        self::assertNotNull($totp);
        self::assertTrue($this->factors()->apply($totp, static function (TotpCredential $current) use ($now): bool {
            $current->accept(57_000_000_000, $now);

            return true;
        }));
        self::assertFalse($this->factors()->apply($totp, static function (TotpCredential $current) use ($now): bool {
            $current->accept(57_000_000_009, $now);

            return false;
        }));
        $this->clear();
        self::assertSame(57_000_000_000, $this->factors()->totp(self::ADA)?->lastCounter());

        $stale = $this->factors()->totp(self::ADA);
        self::assertNotNull($stale);
        $this->factors()->remove($stale);
        self::assertFalse($this->factors()->apply($stale, static fn (TotpCredential $current): bool => true));
        $this->factors()->save(TotpCredential::enroll(self::ADA, 'plain.SECOND', $now));
        $seen = null;
        self::assertFalse($this->factors()->apply($stale, static function (TotpCredential $current) use (&$seen): bool {
            $seen = [$current->storedSecret(), $current->lastCounter()];

            return false;
        }));
        self::assertSame(['plain.SECOND', null], $seen);

        $set = $this->factors()->recoveryCodes(self::ADA);
        self::assertNotNull($set);
        self::assertTrue($this->factors()->apply($set, static function (RecoveryCodeSet $current): bool {
            $current->use(1);

            return true;
        }));
        $this->clear();
        self::assertSame(['a', null], $this->factors()->recoveryCodes(self::ADA)?->hashes());
    }

    public function testAChangeToAnAccountAppliesToItsStoredRow(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->accounts()->save(Account::register(self::ADA, 'ada@example.com', $now));
        $this->clear();
        $held = $this->accounts()->get(self::ADA);
        self::assertNotNull($held);
        $other = $this->accounts()->get(self::ADA);
        self::assertNotNull($other);
        $other->rename('Ada');
        $this->accounts()->save($other);

        $seen = null;
        self::assertTrue($this->accounts()->apply($held, static function (Account $current) use (&$seen, $now): bool {
            $seen = $current->displayName();
            $current->changePassword('$argon2id$hash', $now);

            return true;
        }));
        self::assertSame('Ada', $seen);
        self::assertFalse($this->accounts()->apply($held, static function (Account $current) use ($now): bool {
            $current->changePassword('$argon2id$other', $now);

            return false;
        }));
        $this->clear();
        $stored = $this->accounts()->get(self::ADA);
        self::assertSame(['Ada', '$argon2id$hash'], [$stored?->displayName(), $stored?->passwordHash()]);
        self::assertSame($now->getTimestamp(), $stored?->createdAt()->getTimestamp());

        self::assertNotNull($stored);
        $this->accounts()->remove($stored);
        $this->clear();
        self::assertFalse($this->accounts()->apply($held, static fn (Account $current): bool => true));
    }

    public function testATransactionHandsBackWhatItsWorkReturns(): void
    {
        self::assertSame(42, $this->transactions()->run(static fn (): int => 42));
    }

    public function testATransactionLetsTheExceptionOfItsWorkThrough(): void
    {
        $this->expectExceptionObject(new RuntimeException('failed'));

        $this->transactions()->run(static function (): never {
            throw new RuntimeException('failed');
        });
    }

    public function testSigningKeys(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->keys()->save(SigningKey::create('second', SigningAlgorithm::EdDSA, 'plain.b', ['kid' => 'second'], $now->modify('+1 hour')), SigningKey::create('first', SigningAlgorithm::ES256, 'plain.a', ['kid' => 'first'], $now));
        $this->clear();

        self::assertSame(['first', 'second'], array_map(static fn (SigningKey $key): string => $key->kid(), $this->keys()->all()));
        $first = $this->keys()->get('first');
        self::assertNotNull($first);
        self::assertSame([SigningAlgorithm::ES256, 'plain.a', ['kid' => 'first']], [$first->algorithm(), $first->privateMaterial(), $first->publicJwk()]);
        $first->activate($now);
        $this->keys()->save($first);
        $this->clear();
        self::assertTrue($this->keys()->get('first')?->isActive());
        self::assertNull($this->keys()->get('unknown'));

        $second = $this->keys()->get('second');
        self::assertNotNull($second);
        $this->keys()->remove($second);
        $this->clear();
        self::assertCount(1, $this->keys()->all());
    }

    public function testListsOrderTiesById(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->sessions()->save(AccountSession::open(str_repeat('b', 43), self::ADA, 'B', [], false, $now), AccountSession::open(str_repeat('a', 43), self::ADA, 'A', [], false, $now));
        $this->identities()->save(...$this->descending([LinkedIdentity::link(self::ADA, 'zeta', '1', $now), LinkedIdentity::link(self::ADA, 'alpha', '1', $now)]));
        $this->identities()->save(LinkedIdentity::link(self::BOB, 'alpha', '2', $now->modify('-1 hour')));
        $this->passkeys()->save(...$this->descending([
            Passkey::register(self::ADA, new RegisteredCredential('one', 'c', 0, str_repeat("\x01", 16), false, false, false), 'One', $now),
            Passkey::register(self::ADA, new RegisteredCredential('two', 'c', 0, str_repeat("\x02", 16), false, false, false), 'Two', $now),
        ]));
        $this->passkeys()->save(Passkey::register(self::BOB, new RegisteredCredential('three', 'c', 0, str_repeat("\x03", 16), false, false, false), 'Three', $now->modify('-1 hour')));
        $this->keys()->save(SigningKey::create('kid-b', SigningAlgorithm::ES256, 'p', ['kid' => 'kid-b'], $now), SigningKey::create('kid-a', SigningAlgorithm::ES256, 'p', ['kid' => 'kid-a'], $now));
        foreach ($this->descending([Consent::grant(self::ADA, 'one', ['openid'], $now), Consent::grant(self::ADA, 'two', ['openid'], $now)]) as $consent) {
            $this->clients()->saveConsent($consent);
        }

        $this->clients()->saveConsent(Consent::grant(self::BOB, 'one', ['openid'], $now->modify('+1 hour')));
        $this->grants()->saveToken(
            IssuedToken::issue('jti-b', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], str_repeat('s', 43), $now, [], $now, $now->modify('+1 hour')),
            IssuedToken::issue('jti-a', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], str_repeat('s', 43), $now, [], $now, $now->modify('+1 hour')),
        );
        $this->clear();

        self::assertSame([str_repeat('a', 43), str_repeat('b', 43)], $this->idsOf($this->sessions()->openFor(self::ADA)));
        $linked = $this->identities()->forAccount(self::ADA);
        self::assertCount(2, $linked);
        self::assertSame($this->idsOf($linked), $this->sortedIds($this->idsOf($linked)));
        $passkeys = $this->passkeys()->forAccount(self::ADA);
        self::assertCount(2, $passkeys);
        self::assertSame($this->idsOf($passkeys), $this->sortedIds($this->idsOf($passkeys)));
        self::assertSame(['kid-a', 'kid-b'], $this->idsOf($this->keys()->all()));
        $consents = $this->clients()->consentsOf(self::ADA);
        self::assertCount(2, $consents);
        self::assertSame($this->idsOf($consents), $this->sortedIds($this->idsOf($consents)));
        self::assertSame(['jti-a', 'jti-b'], $this->idsOf($this->grants()->family('FAMILY0000000000000000000A')));
        self::assertSame(['jti-a', 'jti-b'], $this->idsOf($this->grants()->activeFor(self::ADA)));
        self::assertSame(['jti-a', 'jti-b'], $this->idsOf($this->grants()->forSession(str_repeat('s', 43))));
    }

    public function testListsAreOrderedByTheirMomentBeforeTheirIdentifier(): void
    {
        $other = new DateTimeImmutable('2026-11-14 09:00:00');
        $signed = static fn (int $i): string => str_repeat(\chr(97 + $i % 26), 43);

        [$sessions, $expected] = $this->opposed(static fn (int $i, DateTimeImmutable $time): array => [AccountSession::open($signed($i), self::ADA, 'Device', [], false, $time), $signed($i)], true);
        $this->sessions()->save(...$sessions, ...[AccountSession::open($signed(9), self::BOB, 'Device', [], false, $other->modify('+9 hours'))]);
        [$consents, $expectedConsents] = $this->opposed(static fn (int $i, DateTimeImmutable $time): array => [Consent::grant(self::ADA, 'client'.$i, ['openid'], $time), Consent::idOf(self::ADA, 'client'.$i)], true);

        foreach ($consents as $consent) {
            $this->clients()->saveConsent($consent);
        }

        $this->clients()->saveConsent(Consent::grant(self::BOB, 'client0', ['openid'], $other->modify('+9 hours')));
        [$tokens, $expectedTokens] = $this->opposed(static fn (int $i, DateTimeImmutable $time): array => [IssuedToken::issue('jti'.$i, TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], str_repeat('s', 43), $time, [], $time, $time->modify('+1 day')), 'jti'.$i], false);
        $this->grants()->saveToken(...$tokens);
        $this->grants()->saveToken(IssuedToken::issue('foreign', TokenKind::Access, 'FAMILY0000000000000000000B', 'app', self::BOB, [], str_repeat('t', 43), $other, [], $other, $other->modify('+1 day')));
        [$linked, $expectedLinked] = $this->opposed(static fn (int $i, DateTimeImmutable $time): array => [LinkedIdentity::link(self::ADA, 'p'.\chr(97 + $i % 26), 'sub', $time), LinkedIdentity::idOf('p'.\chr(97 + $i % 26), 'sub')], false);
        $this->identities()->save(...$linked);
        $this->identities()->save(LinkedIdentity::link(self::BOB, 'pz', 'sub', $other));
        [$keys, $expectedKeys] = $this->opposed(static fn (int $i, DateTimeImmutable $time): array => [SigningKey::create('kid'.$i, SigningAlgorithm::ES256, 'p', ['kid' => 'kid'.$i], $time), 'kid'.$i], false);
        $this->keys()->save(...$keys);
        [$passkeys, $expectedPasskeys] = $this->opposed(static fn (int $i, DateTimeImmutable $time): array => [Passkey::register(self::ADA, new RegisteredCredential('cred'.$i, 'c', 0, str_repeat("\x01", 16), false, false, false), 'P'.$i, $time), Passkey::idOf('cred'.$i)], false);
        $this->passkeys()->save(...$passkeys);
        $this->clear();

        self::assertSame($expected, $this->idsOf($this->sessions()->openFor(self::ADA)));
        self::assertSame($expectedConsents, $this->idsOf($this->clients()->consentsOf(self::ADA)));
        self::assertSame($expectedTokens, $this->idsOf($this->grants()->forSession(str_repeat('s', 43))));
        self::assertSame($expectedTokens, $this->idsOf($this->grants()->family('FAMILY0000000000000000000A')));
        self::assertSame($expectedTokens, $this->idsOf($this->grants()->activeFor(self::ADA)));
        self::assertSame($expectedLinked, $this->idsOf($this->identities()->forAccount(self::ADA)));
        self::assertSame($expectedKeys, $this->idsOf($this->keys()->all()));
        self::assertSame($expectedPasskeys, $this->idsOf($this->passkeys()->forAccount(self::ADA)));
    }

    /**
     * Three items whose moments are the opposite of their identifiers' order, so that a list that forgets the moment, or
     * the account it belongs to, is out of order.
     *
     * @template T of object
     *
     * @param callable(int, DateTimeImmutable): array{T, string} $make the item of an index at a moment, and its identifier
     *
     * @return array{list<T>, list<string>} the items, and their identifiers in the order the list must give them
     */
    private function opposed(callable $make, bool $descending): array
    {
        $base = new DateTimeImmutable('2026-11-14 10:00:00');
        $identifiers = array_map(static fn (int $i): string => $make($i, $base)[1], [0, 1, 2]);
        $byIdentifier = [0, 1, 2];
        usort($byIdentifier, static fn (int $a, int $b): int => strcmp($identifiers[$a], $identifiers[$b]));
        $order = $descending ? [2, 1, 0] : [0, 1, 2];
        $rank = static fn (int $i): int => $i;

        if ($byIdentifier === $order) {
            $rank = static fn (int $i): int => 2 - $i;
            $order = array_reverse($order);
        }

        return [
            array_map(static fn (int $i): object => $make($i, $base->modify(\sprintf('+%d hours', $rank($i))))[0], [0, 1, 2]),
            array_map(static fn (int $i): string => $identifiers[$i], $order),
        ];
    }

    public function testPurgeKeepsWhatExpiresAtTheLimit(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->sessions()->save(AccountSession::open(str_repeat('a', 43), self::ADA, 'A', [], false, $now));
        $this->grants()->saveCode(AuthorizationCode::issue('code', 'app', self::ADA, 'https://a/cb', ['openid'], null, 'challenge', $now, [], null, 'FAMILY0000000000000000000A', $now->modify('+60 seconds')));
        $this->grants()->saveCode(AuthorizationCode::issue('late', 'app', self::ADA, 'https://a/cb', ['openid'], null, 'challenge', $now, [], null, 'FAMILY0000000000000000000A', $now->modify('+1 hour')));
        $this->grants()->saveToken(IssuedToken::issue('jti', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], null, $now, [], $now, $now->modify('+60 seconds')));
        $this->clear();

        self::assertSame(0, $this->sessions()->purge($now));
        self::assertSame(0, $this->grants()->purge($now->modify('+60 seconds')));
        self::assertSame(2, $this->grants()->purge($now->modify('+61 seconds')));
        self::assertNotNull($this->grants()->code(AuthorizationCode::idOf('late')));
    }

    public function testAnAccountIsFoundByItsCanonicalAddressAndKeepsItsState(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $uuid = '0192f5b4-7c1e-7a3b-9f00-123456789abc';
        $account = Account::register($uuid, 'ada+news@example.com', $now, null, 'ada@example.com');
        $account->changeState(AccountState::Suspended, $now->modify('+1 hour'));
        $this->accounts()->save($account, Account::register(self::BOB, 'bob@example.com', $now));
        $this->sessions()->save(AccountSession::open(str_repeat('u', 43), $uuid, 'Device', [], false, $now));
        $this->clear();

        $found = $this->accounts()->findByCanonicalEmail('ada@example.com');
        self::assertSame($uuid, $found?->id());
        self::assertSame('ada+news@example.com', $found->email());
        self::assertSame(AccountState::Suspended, $found->state());
        self::assertSame($now->modify('+1 hour')->getTimestamp(), $found->stateChangedAt()?->getTimestamp());
        self::assertSame(AccountState::Active, $this->accounts()->get(self::BOB)?->state());
        self::assertNull($this->accounts()->findByCanonicalEmail('ada+news@example.com'));
        self::assertSame($uuid, $this->sessions()->openFor($uuid)[0]->accountId());
    }

    public function testAccountAddresses(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $recovery = AccountAddress::add(self::ADA, AddressPurpose::Recovery, 'ada@example.org', 'ada@example.org', $now);
        $notices = AccountAddress::add(self::ADA, AddressPurpose::Notices, 'Ada+News@example.net', 'ada@example.net', $now);
        $this->addresses()->save($recovery, $notices, AccountAddress::add(self::BOB, AddressPurpose::Recovery, 'bob@example.org', 'bob@example.org', $now));
        $this->clear();

        $found = $this->addresses()->forAccount(self::ADA);
        self::assertSame([AddressPurpose::Notices, AddressPurpose::Recovery], array_map(static fn (AccountAddress $address): AddressPurpose => $address->purpose(), $found));
        self::assertSame(['ada+news@example.net', 'ada@example.net', false], [$found[0]->address(), $found[0]->canonical(), $found[0]->isVerified()]);
        self::assertSame(self::ADA, $this->addresses()->findByCanonical(AddressPurpose::Notices, 'ada@example.net')?->accountId());
        self::assertNull($this->addresses()->findByCanonical(AddressPurpose::Recovery, 'ada@example.net'));
        self::assertSame(self::BOB, $this->addresses()->findByCanonical(AddressPurpose::Recovery, 'bob@example.org')?->accountId());
        self::assertSame([], $this->addresses()->forAccount('nobody'));

        $found[1]->verify($now);
        $found[1]->change('ADA@example.org', 'ada@example.org');
        $this->addresses()->save($found[1]);
        $this->clear();
        self::assertSame($now->getTimestamp(), $this->addresses()->findByCanonical(AddressPurpose::Recovery, 'ada@example.org')?->verifiedAt()?->getTimestamp());

        $this->addresses()->remove($this->addresses()->forAccount(self::ADA)[0]);
        $this->clear();
        self::assertSame([AddressPurpose::Recovery], array_map(static fn (AccountAddress $address): AddressPurpose => $address->purpose(), $this->addresses()->forAccount(self::ADA)));
    }

    public function testALinkTokenIsConsumedOnceRevokedAndPurged(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $id = static fn (string $letter): string => str_repeat($letter, 64);
        $this->linkTokens()->save(LinkToken::issue($id('a'), self::ADA, 'reset', 'fingerprint-a', $now->modify('+1 hour'), $now));
        $this->linkTokens()->save(LinkToken::issue($id('b'), self::ADA, 'verify', 'fingerprint-b', $now->modify('+1 day'), $now));
        $this->linkTokens()->save(LinkToken::issue($id('c'), self::ADA, 'reset', 'fingerprint-c', $now->modify('+2 hours'), $now));
        $this->linkTokens()->save(LinkToken::issue($id('d'), self::BOB, 'reset', 'fingerprint-d', $now->modify('+1 hour'), $now));
        $this->clear();

        $token = $this->linkTokens()->get($id('a'));
        self::assertNotNull($token);
        self::assertSame([self::ADA, 'reset', 'fingerprint-a'], [$token->accountId(), $token->purpose(), $token->fingerprint()]);
        self::assertSame($now->modify('+1 hour')->getTimestamp(), $token->expiresAt()->getTimestamp());
        self::assertSame($now->getTimestamp(), $token->createdAt()->getTimestamp());
        self::assertNull($this->linkTokens()->get($id('e')));

        self::assertTrue($this->linkTokens()->consume($id('a')));
        self::assertFalse($this->linkTokens()->consume($id('a')));
        self::assertFalse($this->linkTokens()->consume($id('e')));
        self::assertNull($this->linkTokens()->get($id('a')));

        self::assertSame(1, $this->linkTokens()->revoke(self::ADA, 'reset'));
        self::assertSame(0, $this->linkTokens()->revoke(self::ADA, 'reset'));
        self::assertNotNull($this->linkTokens()->get($id('b')));
        self::assertSame(1, $this->linkTokens()->revoke(self::ADA));
        self::assertNull($this->linkTokens()->get($id('b')));

        self::assertSame(0, $this->linkTokens()->purge($now->modify('+1 hour')));
        self::assertSame(1, $this->linkTokens()->purge($now->modify('+1 hour +1 second')));
        self::assertNull($this->linkTokens()->get($id('d')));
    }

    public function testAnAddressIsFoldedAsText(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $account = Account::register(self::ADA, ' ÉMILE@Example.COM ', $now);
        $this->accounts()->save($account);
        $this->clear();

        self::assertSame('émile@example.com', $account->email());
        self::assertSame(self::ADA, $this->accounts()->findByEmail('ÉMILE@EXAMPLE.com')?->id());
    }

    /**
     * @template T of Consent|LinkedIdentity|Passkey
     *
     * @param list<T> $items
     *
     * @return list<T> the items by decreasing id, the order a list must not keep
     */
    private function descending(array $items): array
    {
        usort($items, static fn (Consent|LinkedIdentity|Passkey $a, Consent|LinkedIdentity|Passkey $b): int => strcmp($b->id(), $a->id()));

        return $items;
    }

    /**
     * @param list<AccountSession|Consent|IssuedToken|LinkedIdentity|Passkey|SigningKey> $items
     *
     * @return list<string>
     */
    private function idsOf(array $items): array
    {
        return array_map(static fn (AccountSession|Consent|IssuedToken|LinkedIdentity|Passkey|SigningKey $item): string => $item instanceof SigningKey ? $item->kid() : $item->id(), $items);
    }

    /**
     * The token endpoint over these stores, a client that asks refresh tokens, and a code issued to it.
     *
     * @return array{Tokens, InterleavedGrants, Client, string, Services}
     */
    private function provider(): array
    {
        $services = new Services();
        $settings = new ProviderSettings('https://id.example', SigningAlgorithm::ES256);
        $grants = new InterleavedGrants($this->grants());
        new ClientRegistry($this->clients(), $services->clock, $settings, $services->journal)->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $client = $this->clients()->get('app');
        self::assertInstanceOf(Client::class, $client);
        $keys = new SigningKeys($this->keys(), new PlainSecretCipher(), $settings, $services->clock, new LockFactory(new InMemoryStore()));
        $authorization = new Authorization($this->clients(), $grants, $settings, $services->clock, $services->journal);
        $request = $authorization->parse(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid offline_access', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);
        $code = Query::get($authorization->issueCode($request, $services->account(), $services->clock->now(), [], null), 'code');

        return [new Tokens($this->clients(), $grants, $services->accounts, $keys, $settings, $services->clock, $services->journal), $grants, $client, $code, $services];
    }

    /**
     * @return list<string> the tokens of the family still active, read again from the store
     */
    private function activeIn(string $family, DateTimeImmutable $now): array
    {
        return array_values(array_map(
            static fn (IssuedToken $token): string => $token->id(),
            array_filter($this->grants()->family($family), static fn (IssuedToken $token): bool => $token->isActive($now)),
        ));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function sortedIds(array $ids): array
    {
        sort($ids, \SORT_STRING);

        return $ids;
    }
}
