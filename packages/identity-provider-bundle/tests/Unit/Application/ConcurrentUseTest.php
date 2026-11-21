<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\CountingFactors;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\InterleavedFactors;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\InterleavedGrants;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\InterleavedKeys;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\WaitingLockStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;

/**
 * What a single use promises when two requests, or two instances, read the same row before either writes: one wins,
 * the other is refused as a reuse; and what introspection says of a disabled account.
 */
#[CoversClass(Tokens::class)]
#[CoversClass(SecondFactors::class)]
#[CoversClass(SigningKeys::class)]
final class ConcurrentUseTest extends TestCase
{
    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InterleavedGrants $grants;

    private InterleavedKeys $keyStore;

    private ProviderSettings $settings;

    private WaitingLockStore $lockStore;

    private LockFactory $locks;

    protected function setUp(): void
    {
        $this->lockStore = new WaitingLockStore();
        $this->locks = new LockFactory($this->lockStore);
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->grants = new InterleavedGrants();
        $this->keyStore = new InterleavedKeys();
        $this->settings = new ProviderSettings('https://id.example', SigningAlgorithm::ES256, keyActivationDelay: 100, retiredKeyRetention: 200);
    }

    public function testIntrospectionAnswersInactiveForTheTokensOfADisabledAccount(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $response = $this->tokens()->exchangeCode($client, $this->code($account), 'https://app.example/cb', self::VERIFIER);
        self::assertTrue($this->tokens()->introspect($client, (string) $response['access_token'])['active']);

        $account->disable($this->services->clock->now());
        $this->services->accounts->save($account);

        self::assertSame(['active' => false], $this->tokens()->introspect($client, (string) $response['access_token']));
        self::assertSame(['active' => false], $this->tokens()->introspect($client, (string) $response['refresh_token']));
    }

    public function testACodeExchangedByTwoRequestsAtOnceIssuesTokensOnce(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $code = $this->code($account);
        $tokens = $this->tokens();
        $first = [];
        $this->grants->meanwhile = static function () use ($tokens, $client, $code, &$first): void {
            $first = $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER);
        };

        $this->assertInvalidGrant(static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER), 'The code was already used.');
        self::assertArrayHasKey('access_token', $first);
        self::assertContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $first['access_token']));
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $first['refresh_token']));
    }

    public function testARefreshTokenPresentedByTwoRequestsAtOnceRotatesOnce(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account), 'https://app.example/cb', self::VERIFIER);
        $refresh = (string) $response['refresh_token'];
        $first = [];
        $this->grants->meanwhile = static function () use ($tokens, $client, $refresh, &$first): void {
            $first = $tokens->refresh($client, $refresh, null);
        };

        $this->assertInvalidGrant(static fn (): array => $tokens->refresh($client, $refresh, null), 'The refresh token was already used.');
        self::assertArrayHasKey('refresh_token', $first);
        self::assertContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $first['refresh_token']));
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $first['access_token']));
    }

    public function testARefreshReplayedBetweenTheRotationAndTheSaveRevokesTheTokensOfTheWinner(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account), 'https://app.example/cb', self::VERIFIER);
        $refresh = (string) $response['refresh_token'];
        $family = $this->familyOf($refresh);
        $this->grants->afterUse = function () use ($tokens, $client, $refresh): void {
            $this->assertInvalidGrant(static fn (): array => $tokens->refresh($client, $refresh, null), 'The refresh token was already used.');
        };

        $this->assertInvalidGrant(static fn (): array => $tokens->refresh($client, $refresh, null));
        self::assertContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
        self::assertSame([], $this->activeIn($family), 'No token of the family outlives the revocation.');
    }

    public function testACodeReplayedBetweenItsUseAndTheSaveRevokesTheTokensOfTheWinner(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $code = $this->code($account);
        $tokens = $this->tokens();
        $this->grants->afterUse = function () use ($tokens, $client, $code): void {
            $this->assertInvalidGrant(static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER), 'The code was already used.');
        };

        $this->assertInvalidGrant(static fn (): array => $tokens->exchangeCode($client, $code, 'https://app.example/cb', self::VERIFIER));
        self::assertContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
        self::assertSame([], $this->activeIn($this->familyOfCode($code)), 'No token of the family outlives the revocation.');
    }

    public function testAnAccessTokenRevokedByItsClientLeavesItsFamilyUnmarked(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account), 'https://app.example/cb', self::VERIFIER);
        $family = $this->familyOf((string) $response['refresh_token']);
        $tokens->revoke($client, (string) $response['access_token']);

        self::assertFalse($this->grants->isFamilyRevoked($family));
        $second = $tokens->refresh($client, (string) $response['refresh_token'], null);
        self::assertArrayHasKey('refresh_token', $second);

        $tokens->revoke($client, (string) $second['refresh_token']);
        self::assertTrue($this->grants->isFamilyRevoked($family), 'A refresh token revoked by its client takes its family with it.');
    }

    public function testATotpCodeVerifiedByTwoRequestsAtOnceIsAcceptedOnce(): void
    {
        $store = new InterleavedFactors();
        $factors = $this->factors($store);
        $account = $this->services->account();
        $totp = Totp::fromBase32($factors->enroll($account)['secret']);
        $now = $this->services->clock->now()->getTimestamp();
        $factors->confirm($account, $totp->at($now));
        $this->services->clock->modify('+30 seconds');
        $code = $totp->at($now + 30);
        $first = null;
        $store->meanwhile = static function () use ($factors, $account, $code, &$first): void {
            $first = $factors->verifyTotp($account, $code);
        };

        self::assertFalse($factors->verifyTotp($account, $code));
        self::assertTrue($first);
    }

    public function testAWrongCodeHoldsNothing(): void
    {
        $store = new CountingFactors();
        $factors = new SecondFactors($store, new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $this->services->journal, $this->services->clock, 'Example');
        $account = $this->services->account();
        $factors->enroll($account);

        try {
            $factors->confirm($account, '000000');
            self::fail('Confirmed with a wrong code.');
        } catch (Refused) {
            self::assertNotContains('apply', $store->operations);
        }
    }

    public function testARecoveryCodeUsedByTwoRequestsAtOnceIsAcceptedOnce(): void
    {
        $store = new InterleavedFactors();
        $factors = $this->factors($store);
        $account = $this->services->account();
        $codes = $factors->regenerateRecoveryCodes($account);
        $first = null;
        $store->meanwhile = static function () use ($factors, $account, $codes, &$first): void {
            $first = $factors->useRecoveryCode($account, $codes[3]);
        };

        self::assertFalse($factors->useRecoveryCode($account, $codes[3]));
        self::assertTrue($first);
        self::assertSame(9, $factors->remainingRecoveryCodes($account));
    }

    public function testTwoInstancesStartingAtOnceMakeOneKey(): void
    {
        $first = $this->keys();
        $second = $this->keys();
        $made = null;
        $this->keyStore->meanwhile = static function () use ($second, &$made): void {
            $made = $second->active()->kid;
        };

        $signing = $first->active()->kid;

        self::assertSame($made, $signing);
        self::assertCount(1, $this->keyStore->inner->all());
    }

    public function testTheFirstKeyAndEachRotationAreMadeUnderTheLockOfTheKeys(): void
    {
        $locks = $this->locks;
        $held = [];
        $watch = static function () use ($locks, &$held): void {
            $held[] = !$locks->createLock(SigningKeys::LOCK)->acquire(false);
        };
        $store = $this->keyStore;
        $store->meanwhile = static function () use ($store, $watch): void {
            $store->meanwhile = $watch;
        };
        $keys = $this->keys();

        $keys->active();

        $store->meanwhile = $watch;
        $keys->rotate();

        self::assertSame([true, true], $held);
        self::assertSame(['wait', 'try', 'wait', 'try'], $this->lockStore->acquisitions);
        self::assertTrue($locks->createLock(SigningKeys::LOCK)->acquire(false), 'The lock is released.');
    }

    public function testOfSeveralActiveKeysTheOneActivatedLastSignsAndTheOldestPendingOneStays(): void
    {
        $at = $this->services->clock->now();
        $this->keyStore->inner->save(
            $this->key('a', $at, $at->modify('+2 seconds')),
            $this->key('m', $at, $at->modify('+2 seconds')),
            $this->key('z', $at, $at->modify('+1 second')),
            $this->key('p1', $at->modify('+3 seconds')),
            $this->key('p2', $at->modify('+4 seconds')),
        );

        self::assertSame('m', $this->keys()->active()->kid);
        self::assertSame(['m'], $this->kids(static fn (SigningKey $key): bool => $key->isActive()));
        self::assertSame(['a', 'z'], $this->kids(static fn (SigningKey $key): bool => $key->retiredAt() instanceof DateTimeImmutable));
        self::assertSame(['p1'], $this->kids(static fn (SigningKey $key): bool => $key->isPending()));
        self::assertSame([IdentityEventType::SigningKeyRotated->value, null, ['retired' => 'a z', 'removed' => 1]], array_last($this->services->audit->records));
    }

    public function testOfTwoPendingKeysBesideOneActiveTheOldestStays(): void
    {
        $at = $this->services->clock->now();
        $this->keyStore->inner->save(
            $this->key('a', $at, $at),
            $this->key('p1', $at->modify('+3 seconds')),
            $this->key('p2', $at->modify('+4 seconds')),
        );

        self::assertSame('a', $this->keys()->active()->kid);
        self::assertSame(['p1'], $this->kids(static fn (SigningKey $key): bool => $key->isPending()));
        self::assertSame([IdentityEventType::SigningKeyRotated->value, null, ['retired' => '', 'removed' => 1]], array_last($this->services->audit->records));
    }

    public function testKeysAreSettledWithoutAJournalToo(): void
    {
        $at = $this->services->clock->now();
        $this->keyStore->inner->save($this->key('a', $at, $at), $this->key('b', $at, $at));

        self::assertSame('b', new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, $this->locks)->active()->kid);
    }

    public function testAnExpiredRefreshTokenIsRefusedWithoutBeingTakenForAReuse(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $tokens = $this->tokens();
        $response = $tokens->exchangeCode($client, $this->code($account), 'https://app.example/cb', self::VERIFIER);
        $this->services->clock->modify(\sprintf('+%d seconds', $this->settings->refreshTokenLifetime));

        $this->assertInvalidGrant(static fn (): array => $tokens->refresh($client, (string) $response['refresh_token'], null));
        self::assertNotContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
    }

    private function key(string $kid, DateTimeImmutable $createdAt, ?DateTimeImmutable $activatedAt = null): SigningKey
    {
        $private = PrivateKey::generate(SigningAlgorithm::ES256, $kid);
        $key = SigningKey::create($kid, SigningAlgorithm::ES256, new PlainSecretCipher()->seal($private->material(), 'signing-key:'.$kid), $private->publicJwk(), $createdAt);

        if ($activatedAt instanceof DateTimeImmutable) {
            $key->activate($activatedAt);
        }

        return $key;
    }

    private function client(): Client
    {
        new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal)->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $client = $this->clients->get('app');
        self::assertNotNull($client);

        return $client;
    }

    private function code(Account $account): string
    {
        $authorization = new Authorization($this->clients, $this->grants, $this->settings, $this->services->clock, $this->services->journal);
        $request = $authorization->parse(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid offline_access', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);

        return Query::get($authorization->issueCode($request, $account, $this->services->clock->now(), [], 'sid'), 'code');
    }

    private function keys(): SigningKeys
    {
        return new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, $this->locks, $this->services->journal);
    }

    private function tokens(): Tokens
    {
        return new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys(), $this->settings, $this->services->clock, $this->services->journal);
    }

    private function factors(InterleavedFactors $store): SecondFactors
    {
        return new SecondFactors($store, new PlainSecretCipher(), new RecoveryCodes(str_repeat('k', 32)), $this->services->journal, $this->services->clock, 'Example');
    }

    /**
     * @param callable(SigningKey): bool $filter
     *
     * @return list<string>
     */
    private function kids(callable $filter): array
    {
        return array_values(array_map(static fn (SigningKey $key): string => $key->kid(), array_filter($this->keyStore->inner->all(), $filter)));
    }

    private function familyOf(string $refresh): string
    {
        $token = $this->grants->inner->token(IssuedToken::idOfRefresh($refresh));
        self::assertNotNull($token);

        return $token->family();
    }

    private function familyOfCode(string $code): string
    {
        $grant = $this->grants->inner->code(AuthorizationCode::idOf($code));
        self::assertNotNull($grant);

        return $grant->family();
    }

    /**
     * @return list<string> the tokens of the family still active
     */
    private function activeIn(string $family): array
    {
        $now = $this->services->clock->now();

        return array_values(array_map(
            static fn (IssuedToken $token): string => $token->id(),
            array_filter($this->grants->inner->family($family), static fn (IssuedToken $token): bool => $token->isActive($now)),
        ));
    }

    private function assertInvalidGrant(callable $operation, ?string $description = null): void
    {
        try {
            $operation();
            self::fail('Accepted twice.');
        } catch (OAuthError $oAuthError) {
            self::assertSame('invalid_grant', $oAuthError->error);

            if (null !== $description) {
                self::assertSame($description, $oAuthError->description);
            }
        }
    }
}
