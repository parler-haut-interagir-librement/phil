<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Application\Contract\Transactions;
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
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use Phil\IdentityProviderBundle\Infrastructure\Memory\ImmediateTransactions;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountAddressRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkTokenRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySecondFactorRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(InMemoryAccountRepository::class)]
#[CoversClass(ImmediateTransactions::class)]
#[CoversClass(InMemoryPasskeyRepository::class)]
#[CoversClass(InMemorySecondFactorRepository::class)]
#[CoversClass(InMemoryAccountSessionRepository::class)]
#[CoversClass(InMemoryLinkedIdentityRepository::class)]
#[CoversClass(InMemoryClientRepository::class)]
#[CoversClass(InMemoryGrantRepository::class)]
#[CoversClass(InMemorySigningKeyRepository::class)]
#[CoversClass(InMemoryAccountAddressRepository::class)]
#[CoversClass(InMemoryLinkTokenRepository::class)]
final class MemoryRepositoryTest extends RepositoryTestCase
{
    /** @var array<class-string, object> */
    private array $stores = [];

    public function testEachStoreIsEmptiedBetweenRequests(): void
    {
        $this->testAccounts();
        $this->testPasskeys();
        $this->testSecondFactors();
        $this->testSessions();
        $this->testLinkedIdentities();
        $this->testClientsAndConsents();
        $this->testGrants();
        $this->testSigningKeys();

        foreach ($this->stores as $store) {
            self::assertInstanceOf(ResetInterface::class, $store);
            $store->reset();
        }

        self::assertNull($this->accounts()->get(self::ADA));
        self::assertSame([], $this->passkeys()->forAccount(self::ADA));
        self::assertSame([], $this->sessions()->openFor(self::ADA));
        self::assertSame([], $this->identities()->forAccount(self::ADA));
        self::assertSame([], $this->clients()->all());
        self::assertSame([], $this->clients()->consentsOf(self::ADA));
        self::assertNull($this->grants()->token(IssuedToken::idOfRefresh('r')));
        self::assertSame([], $this->keys()->all());
    }

    public function testStoresKeepAndHandOutCopies(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $later = $now->modify('+1 hour');

        $account = Account::register(self::ADA, 'ada@example.com', $now);
        $this->accounts()->save($account);
        $account->verifyEmail($now);
        self::assertFalse($this->accounts()->get(self::ADA)?->isEmailVerified());
        $this->accounts()->get(self::ADA)->verifyEmail($now);
        self::assertFalse($this->accounts()->get(self::ADA)->isEmailVerified());
        $this->accounts()->findByEmail('ada@example.com')?->verifyEmail($now);
        self::assertFalse($this->accounts()->findByEmail('ada@example.com')?->isEmailVerified());

        $session = AccountSession::open(str_repeat('a', 43), self::ADA, 'A', [], false, $now);
        $this->sessions()->save($session);
        $session->close($now);
        self::assertFalse($this->sessions()->get(str_repeat('a', 43))?->isClosed());
        $this->sessions()->get(str_repeat('a', 43))->close($now);
        $this->sessions()->openFor(self::ADA)[0]->close($now);
        self::assertCount(1, $this->sessions()->openFor(self::ADA));

        $client = Client::register('app', 'App', null, ['https://a/cb'], [], [], false, $now);
        $this->clients()->save($client);
        $client->rename('Renamed');
        self::assertSame('App', $this->clients()->get('app')?->name());
        $this->clients()->get('app')->rename('Renamed');
        $this->clients()->all()[0]->rename('Renamed');
        self::assertSame('App', $this->clients()->get('app')->name());
        $consent = Consent::grant(self::ADA, 'app', ['openid'], $now);
        $this->clients()->saveConsent($consent);
        $consent->extend(['email'], $later);
        self::assertSame(['openid'], $this->clients()->consent(self::ADA, 'app')?->scopes());
        $this->clients()->consent(self::ADA, 'app')->extend(['email'], $later);
        $this->clients()->consentsOf(self::ADA)[0]->extend(['email'], $later);
        self::assertSame(['openid'], $this->clients()->consent(self::ADA, 'app')->scopes());

        $code = AuthorizationCode::issue('code', 'app', self::ADA, 'https://a/cb', ['openid'], null, 'challenge', $now, [], null, 'FAMILY0000000000000000000A', $later);
        $this->grants()->saveCode($code);
        $code->use($now);
        self::assertFalse($this->grants()->code(AuthorizationCode::idOf('code'))?->isUsed());
        $this->grants()->code(AuthorizationCode::idOf('code'))->use($now);
        self::assertFalse($this->grants()->code(AuthorizationCode::idOf('code'))->isUsed());
        $token = IssuedToken::issue('jti', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', self::ADA, [], str_repeat('s', 43), $now, [], $now, $later);
        $this->grants()->saveToken($token);
        $token->revoke($now);
        self::assertFalse($this->grants()->token('jti')?->isRevoked());
        $this->grants()->token('jti')->revoke($now);
        $this->grants()->family('FAMILY0000000000000000000A')[0]->revoke($now);
        $this->grants()->activeFor(self::ADA)[0]->revoke($now);
        $this->grants()->forSession(str_repeat('s', 43))[0]->revoke($now);
        self::assertFalse($this->grants()->token('jti')->isRevoked());

        $linked = LinkedIdentity::link(self::ADA, 'google', 'g-1', $now);
        $this->identities()->save($linked);
        $linked->recordUse($later);
        self::assertNull($this->identities()->find('google', 'g-1')?->lastUsedAt());
        $this->identities()->find('google', 'g-1')?->recordUse($later);
        $this->identities()->forAccount(self::ADA)[0]->recordUse($later);
        self::assertNull($this->identities()->find('google', 'g-1')?->lastUsedAt());

        $passkey = Passkey::register(self::ADA, new RegisteredCredential('one', 'c', 0, str_repeat("\x01", 16), false, false, false), 'One', $now);
        $this->passkeys()->save($passkey);
        $passkey->rename('Renamed');
        self::assertSame('One', $this->passkeys()->findByCredentialId('one')?->name());
        $this->passkeys()->findByCredentialId('one')->rename('Renamed');
        $this->passkeys()->forAccount(self::ADA)[0]->rename('Renamed');
        self::assertSame('One', $this->passkeys()->findByCredentialId('one')->name());

        $key = SigningKey::create('kid', SigningAlgorithm::ES256, 'p', ['kid' => 'kid'], $now);
        $this->keys()->save($key);
        $key->activate($now);
        self::assertFalse($this->keys()->get('kid')?->isActive());
        $this->keys()->get('kid')->activate($now);
        $this->keys()->all()[0]->activate($now);
        self::assertFalse($this->keys()->get('kid')->isActive());

        $totp = TotpCredential::enroll(self::ADA, 'secret', $now);
        $codes = RecoveryCodeSet::generate(self::ADA, ['a', 'b'], $now);
        $this->factors()->save($totp, $codes);
        $totp->accept(7, $now);
        $codes->use(0);
        self::assertNull($this->factors()->totp(self::ADA)?->lastCounter());
        self::assertSame(['a', 'b'], $this->factors()->recoveryCodes(self::ADA)?->hashes());
        $this->factors()->totp(self::ADA)?->accept(7, $now);
        $this->factors()->recoveryCodes(self::ADA)->use(0);
        self::assertNull($this->factors()->totp(self::ADA)?->lastCounter());
        self::assertSame(['a', 'b'], $this->factors()->recoveryCodes(self::ADA)?->hashes());
    }

    public function testTheRecoveryCodesAndTheAuthenticatorAreKeptApart(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $this->factors()->save(RecoveryCodeSet::generate(self::ADA, ['a'], $now));
        self::assertNull($this->factors()->totp(self::ADA));
        $this->factors()->save(TotpCredential::enroll(self::BOB, 'secret', $now));
        self::assertNull($this->factors()->recoveryCodes(self::BOB));
        $this->factors()->remove(RecoveryCodeSet::generate(self::ADA, ['a'], $now));
        self::assertNull($this->factors()->recoveryCodes(self::ADA));
        $this->factors()->save(TotpCredential::enroll(self::ADA, 'secret', $now), RecoveryCodeSet::generate(self::ADA, ['a'], $now));
        $this->factors()->remove(TotpCredential::enroll(self::ADA, 'secret', $now));
        self::assertNull($this->factors()->totp(self::ADA));
        self::assertNotNull($this->factors()->recoveryCodes(self::ADA));
    }

    #[Override]
    protected function accounts(): AccountRepository
    {
        return $this->store(InMemoryAccountRepository::class);
    }

    #[Override]
    protected function transactions(): Transactions
    {
        return new ImmediateTransactions();
    }

    #[Override]
    protected function passkeys(): PasskeyRepository
    {
        return $this->store(InMemoryPasskeyRepository::class);
    }

    #[Override]
    protected function factors(): SecondFactorRepository
    {
        return $this->store(InMemorySecondFactorRepository::class);
    }

    #[Override]
    protected function sessions(): AccountSessionRepository
    {
        return $this->store(InMemoryAccountSessionRepository::class);
    }

    public function testASavedAddressIsStoredAsACopy(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $address = AccountAddress::add(self::ADA, AddressPurpose::Recovery, 'ada@example.org', 'ada@example.org', $now);
        $this->addresses()->save($address);
        $address->verify($now);

        self::assertFalse($this->addresses()->findByCanonical(AddressPurpose::Recovery, 'ada@example.org')?->isVerified());
    }

    #[Override]
    protected function identities(): LinkedIdentityRepository
    {
        return $this->store(InMemoryLinkedIdentityRepository::class);
    }

    #[Override]
    protected function clients(): ClientRepository
    {
        return $this->store(InMemoryClientRepository::class);
    }

    #[Override]
    protected function grants(): GrantRepository
    {
        return $this->store(InMemoryGrantRepository::class);
    }

    #[Override]
    protected function keys(): SigningKeyRepository
    {
        return $this->store(InMemorySigningKeyRepository::class);
    }

    #[Override]
    protected function addresses(): AccountAddressRepository
    {
        return $this->store(InMemoryAccountAddressRepository::class);
    }

    #[Override]
    protected function linkTokens(): LinkTokenRepository
    {
        return $this->store(InMemoryLinkTokenRepository::class);
    }

    #[Override]
    protected function clear(): void
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function store(string $class): object
    {
        $store = $this->stores[$class] ??= new $class();
        \assert($store instanceof $class);

        return $store;
    }
}
