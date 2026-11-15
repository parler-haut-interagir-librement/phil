<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Model\Totp\RecoveryCodeSet;
use Phil\IdentityProviderBundle\Domain\Model\Totp\TotpCredential;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Account::class)]
#[CoversClass(Passkey::class)]
#[CoversClass(TotpCredential::class)]
#[CoversClass(RecoveryCodeSet::class)]
#[CoversClass(AccountSession::class)]
#[CoversClass(LinkedIdentity::class)]
#[CoversClass(Client::class)]
#[CoversClass(Consent::class)]
#[CoversClass(AuthorizationCode::class)]
#[CoversClass(IssuedToken::class)]
#[CoversClass(SigningKey::class)]
final class ModelsTest extends TestCase
{
    private const string ID = '01JCZ6Q7Y3W0000000000000AB';

    public function testAnAccount(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $account = Account::register(self::ID, ' Ada@Example.COM ', $now, '  Ada  ');

        self::assertSame(self::ID, $account->id());
        self::assertSame('ada@example.com', $account->email());
        self::assertSame('Ada', $account->displayName());
        self::assertSame($now, $account->createdAt());
        self::assertSame(['ROLE_USER'], $account->roles());
        self::assertFalse($account->isEmailVerified());
        self::assertFalse($account->hasPassword());
        self::assertFalse($account->isDisabled());

        $account->verifyEmail($now);
        $account->verifyEmail($now->modify('+1 day'));
        self::assertSame($now, $account->emailVerifiedAt());
        $account->changeEmail('ADA@example.com');
        self::assertTrue($account->isEmailVerified());
        $account->changeEmail('other@example.com');
        self::assertSame('other@example.com', $account->email());
        self::assertFalse($account->isEmailVerified());

        $account->rename('');
        self::assertNull($account->displayName());
        $account->rename(null);
        self::assertNull($account->displayName());
        $account->rename(str_repeat('é', 100));
        self::assertSame(100, mb_strlen((string) $account->displayName()));

        $account->grantRoles(['ROLE_ADMIN', 'ROLE_USER', 'ROLE_ADMIN']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $account->roles());

        $account->disable($now);
        $account->disable($now->modify('+1 day'));
        self::assertSame($now, $account->disabledAt());
        $account->enable();
        self::assertNull($account->disabledAt());
    }

    public function testThePasswordAndTheLock(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $account = Account::register(self::ID, 'ada@example.com', $now);
        $policy = new LockoutPolicy(2, 60, 120);

        $account->recordFailedSignIn($now, $policy);
        self::assertSame(1, $account->failedSignIns());
        self::assertFalse($account->isLocked($now));
        $account->recordFailedSignIn($now, $policy);
        self::assertTrue($account->isLocked($now->modify('+59 seconds')));
        self::assertFalse($account->isLocked($now->modify('+60 seconds')));
        self::assertSame('10:01:00', $account->lockedUntil()?->format('H:i:s'));

        $account->recordSignIn();
        self::assertSame(0, $account->failedSignIns());
        self::assertNull($account->lockedUntil());

        $account->recordFailedSignIn($now, $policy);
        $account->changePassword('hash', $now);
        self::assertSame('hash', $account->passwordHash());
        self::assertTrue($account->hasPassword());
        self::assertSame($now, $account->passwordChangedAt());
        self::assertSame(0, $account->failedSignIns());
        $account->rehashPassword('stronger');
        self::assertSame('stronger', $account->passwordHash());
        self::assertSame($now, $account->passwordChangedAt());
    }

    public function testThePhone(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $account = Account::register(self::ID, 'ada@example.com', $now);
        $account->verifyPhone('+15145550123', $now);

        self::assertSame('+15145550123', $account->phone());
        self::assertSame($now, $account->phoneVerifiedAt());
        $account->removePhone();
        self::assertNull($account->phone());
        self::assertNull($account->phoneVerifiedAt());

        foreach (['5145550123', '+05145550123', '+1514', '+1234567890123456'] as $phone) {
            try {
                $account->verifyPhone($phone, $now);
                self::fail($phone);
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }
    }

    public function testAnAccountRefusesValuesOutOfShape(): void
    {
        $now = new DateTimeImmutable();

        foreach ([
            static fn (): Account => Account::register('short', 'ada@example.com', $now),
            static fn (): Account => Account::register(self::ID, 'no-at', $now),
            static fn (): Account => Account::register(self::ID, 'a@b@c', $now),
            static fn (): Account => Account::register(self::ID, 'a b@c', $now),
            static fn (): Account => Account::register(self::ID, '', $now),
            static fn (): Account => Account::register(self::ID, str_repeat('a', 176).'@b.cd', $now),
            static fn (): Account => Account::register(self::ID, 'ada@example.com', $now, str_repeat('a', 101)),
        ] as $case) {
            try {
                $case();
                self::fail('Accepted.');
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }

        self::assertSame(180, mb_strlen(Account::normalizeEmail(str_repeat('a', 174).'@b.cde')));
    }

    public function testAPasskey(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $passkey = Passkey::register(self::ID, new RegisteredCredential('cred', 'cose', 3, "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xAA\xBB\xCC\xDD\xEE\xFF", true, true, false), ' Phone ', $now);

        self::assertSame(hash('sha256', 'cred'), $passkey->id());
        self::assertSame(Passkey::idOf('cred'), $passkey->id());
        self::assertSame(self::ID, $passkey->accountId());
        self::assertSame('Y3JlZA', $passkey->credentialId());
        self::assertSame('cose', $passkey->coseKey());
        self::assertSame('00112233-4455-6677-8899-aabbccddeeff', $passkey->aaguid());
        self::assertSame('Phone', $passkey->name());
        self::assertSame(3, $passkey->signCount());
        self::assertTrue($passkey->isBackupEligible());
        self::assertFalse($passkey->isBackedUp());
        self::assertSame($now, $passkey->createdAt());
        self::assertNull($passkey->lastUsedAt());

        $passkey->recordUse(4, true, $now);
        $passkey->rename('Laptop');
        self::assertSame([4, true, $now, 'Laptop'], [$passkey->signCount(), $passkey->isBackedUp(), $passkey->lastUsedAt(), $passkey->name()]);

        $this->expectException(InvalidValue::class);
        $passkey->rename(' ');
    }

    public function testAPasskeyNameHoldsAtMost100Characters(): void
    {
        $passkey = Passkey::register(self::ID, new RegisteredCredential('cred', 'cose', 0, str_repeat("\0", 16), false, false, false), str_repeat('n', 100), new DateTimeImmutable());

        $this->expectException(InvalidValue::class);
        $passkey->rename(str_repeat('n', 101));
    }

    public function testTheSecondFactors(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $totp = TotpCredential::enroll(self::ID, 'sealed', $now);

        self::assertSame([self::ID, 'sealed', $now, null, null, false], [$totp->accountId(), $totp->storedSecret(), $totp->createdAt(), $totp->confirmedAt(), $totp->lastCounter(), $totp->isConfirmed()]);
        $totp->accept(7, $now);
        $totp->accept(8, $now->modify('+1 hour'));
        self::assertSame([8, $now, true], [$totp->lastCounter(), $totp->confirmedAt(), $totp->isConfirmed()]);

        $codes = RecoveryCodeSet::generate(self::ID, ['a', 'b', 'c'], $now);
        self::assertSame([self::ID, ['a', 'b', 'c'], 3, $now], [$codes->accountId(), $codes->hashes(), $codes->remaining(), $codes->createdAt()]);
        $codes->use(1);
        $codes->use(9);
        self::assertSame([['a', null, 'c'], 2], [$codes->hashes(), $codes->remaining()]);
        $later = $now->modify('+1 day');
        $codes->replace(['d'], $later);
        self::assertSame([['d'], 1, $later], [$codes->hashes(), $codes->remaining(), $codes->createdAt()]);
    }

    public function testASession(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $session = AccountSession::open('id', self::ID, '  Firefox on Linux ', ['pwd', 'pwd'], true, $now);

        self::assertSame(['id', self::ID, 'Firefox on Linux', ['pwd'], true, $now, $now, null, false], [$session->id(), $session->accountId(), $session->device(), $session->methods(), $session->isRisky(), $session->createdAt(), $session->lastSeenAt(), $session->closedAt(), $session->isClosed()]);
        self::assertSame('?', AccountSession::open('id', self::ID, ' ', [], false, $now)->device());
        self::assertSame(100, mb_strlen(AccountSession::open('id', self::ID, str_repeat('é', 120), [], false, $now)->device()));

        $session->addMethods(['otp', 'pwd']);
        self::assertSame(['pwd', 'otp'], $session->methods());
        $later = $now->modify('+1 hour');
        $session->see($later);
        $session->close($later);
        $session->close($later->modify('+1 hour'));
        self::assertSame([$later, $later, true], [$session->lastSeenAt(), $session->closedAt(), $session->isClosed()]);
    }

    public function testALinkedIdentity(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $identity = LinkedIdentity::link(self::ID, 'google', 'sub-1', $now);

        self::assertSame([LinkedIdentity::idOf('google', 'sub-1'), self::ID, 'google', 'sub-1', $now, null], [$identity->id(), $identity->accountId(), $identity->provider(), $identity->subject(), $identity->linkedAt(), $identity->lastUsedAt()]);
        self::assertNotSame(LinkedIdentity::idOf('goo', 'gle'), LinkedIdentity::idOf('go', 'ogle'));
        $identity->recordUse($now);
        self::assertSame($now, $identity->lastUsedAt());
        LinkedIdentity::link(self::ID, 'a', str_repeat('s', 255), $now);

        foreach ([['Google', 's'], ['google', ''], ['google', str_repeat('s', 256)], ['1google', 's']] as [$provider, $subject]) {
            try {
                LinkedIdentity::link(self::ID, $provider, $subject, $now);
                self::fail($provider);
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }
    }

    public function testAClientAndItsConsent(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $client = Client::register('app', ' App ', 'secret', ['https://a/cb', 'https://a/cb'], ['https://a/out'], ['email', 'openid'], true, $now);

        self::assertSame(['app', 'App', false, true, ['https://a/cb'], ['https://a/out'], ['openid', 'email'], $now, false], [$client->id(), $client->name(), $client->isPublic(), $client->isFirstParty(), $client->redirectUris(), $client->postLogoutRedirectUris(), $client->scopes(), $client->createdAt(), $client->isDisabled()]);
        self::assertTrue($client->verifySecret('secret'));
        self::assertFalse($client->verifySecret('other'));
        self::assertTrue($client->allowsRedirect('https://a/cb'));
        self::assertFalse($client->allowsRedirect('https://a/cb/'));
        self::assertTrue($client->allowsPostLogoutRedirect('https://a/out'));
        self::assertFalse($client->allowsPostLogoutRedirect('https://a/cb'));
        $client->rotateSecret('new');
        self::assertTrue($client->verifySecret('new'));
        $client->disable($now);
        self::assertTrue($client->isDisabled());

        $public = Client::register('spa', 'SPA', null, ['https://a/cb'], [], [], false, $now);
        self::assertTrue($public->isPublic());
        self::assertFalse($public->verifySecret(''));
        self::assertSame(['openid'], $public->scopes());
        self::assertTrue($public->requiresPkce());
        self::assertTrue($client->requiresPkce());
        self::assertFalse(Client::register('server', 'Server', 'secret', ['https://a/cb'], [], [], false, $now, false)->requiresPkce());

        foreach ([['a', 'App', ['https://a']], ['app id', 'App', ['https://a']], ['app', '', ['https://a']], ['app', str_repeat('n', 101), ['https://a']], ['app', 'App', []]] as [$id, $name, $uris]) {
            try {
                Client::register($id, $name, null, $uris, [], [], false, $now);
                self::fail($id);
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }

        $consent = Consent::grant(self::ID, 'app', ['openid', 'email', 'openid'], $now);
        self::assertSame([Consent::idOf(self::ID, 'app'), self::ID, 'app', ['openid', 'email'], $now], [$consent->id(), $consent->accountId(), $consent->clientId(), $consent->scopes(), $consent->grantedAt()]);
        self::assertTrue($consent->covers(['email']));
        self::assertFalse($consent->covers(['email', 'phone']));
        $later = $now->modify('+1 day');
        $consent->extend(['phone', 'email'], $later);
        self::assertSame([['openid', 'email', 'phone'], $later], [$consent->scopes(), $consent->grantedAt()]);
    }

    public function testACodeAndATokenAndAKey(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $code = AuthorizationCode::issue('code', 'app', self::ID, 'https://a/cb', ['openid'], 'n', 'chal', $now, ['pwd'], 'sid', 'family', $now->modify('+60 seconds'));

        self::assertSame([AuthorizationCode::idOf('code'), 'app', self::ID, 'https://a/cb', ['openid'], 'n', 'chal', $now, ['pwd'], 'sid', 'family'], [$code->id(), $code->clientId(), $code->accountId(), $code->redirectUri(), $code->scopes(), $code->nonce(), $code->codeChallenge(), $code->authTime(), $code->methods(), $code->sessionId(), $code->family()]);
        self::assertFalse($code->isExpired($now->modify('+59 seconds')));
        self::assertTrue($code->isExpired($now->modify('+60 seconds')));
        self::assertFalse($code->isUsed());
        $code->use($now);
        self::assertTrue($code->isUsed());
        self::assertSame('+60 seconds', '+'.($code->expiresAt()->getTimestamp() - $now->getTimestamp()).' seconds');

        $token = IssuedToken::issue('jti', TokenKind::Access, 'family', 'app', self::ID, ['openid'], 'sid', $now, ['pwd'], $now, $now->modify('+600 seconds'));
        self::assertSame(['jti', TokenKind::Access, 'family', 'app', self::ID, ['openid'], 'sid', $now, ['pwd'], $now], [$token->id(), $token->kind(), $token->family(), $token->clientId(), $token->accountId(), $token->scopes(), $token->sessionId(), $token->authTime(), $token->methods(), $token->issuedAt()]);
        self::assertTrue($token->isActive($now->modify('+599 seconds')));
        self::assertFalse($token->isActive($now->modify('+600 seconds')));
        $token->rotate($now);
        self::assertTrue($token->isRotated());
        self::assertFalse($token->isActive($now));
        $other = IssuedToken::issue('jti2', TokenKind::Refresh, 'family', 'app', self::ID, [], null, $now, [], $now, $now->modify('+1 day'));
        $other->revoke($now);
        self::assertTrue($other->isRevoked());
        self::assertFalse($other->isActive($now));
        self::assertSame(hash('sha256', 'value'), IssuedToken::idOfRefresh('value'));
        self::assertSame($now->getTimestamp() + 86_400, $other->expiresAt()->getTimestamp());

        $key = SigningKey::create('kid', SigningAlgorithm::ES256, 'sealed', ['kid' => 'kid'], $now);
        self::assertSame(['kid', SigningAlgorithm::ES256, 'sealed', ['kid' => 'kid'], $now, null, null, true, false], [$key->kid(), $key->algorithm(), $key->privateMaterial(), $key->publicJwk(), $key->createdAt(), $key->activatedAt(), $key->retiredAt(), $key->isPending(), $key->isActive()]);
        $key->activate($now);
        $key->activate($now->modify('+1 day'));
        self::assertSame([$now, false, true], [$key->activatedAt(), $key->isPending(), $key->isActive()]);
        $key->retire($now);
        $key->retire($now->modify('+1 day'));
        self::assertSame([$now, false, false], [$key->retiredAt(), $key->isPending(), $key->isActive()]);
    }
}
