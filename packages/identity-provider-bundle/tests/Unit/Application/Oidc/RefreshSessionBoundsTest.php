<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A refresh token bound to its session under the session's own bounds, `sessions.idle_timeout` and `sessions.max_age`:
 * a refresh touches the session, closes it past either bound, and counts as its activity within them.
 */
#[CoversClass(Tokens::class)]
#[CoversClass(Sessions::class)]
final class RefreshSessionBoundsTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private const string CALLBACK = 'https://app.example/cb';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InMemoryGrantRepository $grants;

    private InMemorySigningKeyRepository $keyStore;

    private InMemoryAccountSessionRepository $store;

    private ProviderSettings $settings;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->grants = new InMemoryGrantRepository();
        $this->keyStore = new InMemorySigningKeyRepository();
        $this->store = new InMemoryAccountSessionRepository();
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256);
    }

    public function testNoRefreshTokenOutlivesTheMaximumAgeOfItsSession(): void
    {
        $sessions = $this->sessions(null, 43_200);
        $account = $this->services->account();
        $client = $this->client();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $tokens = $this->tokens($sessions);
        $response = $tokens->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER);
        $end = $session->createdAt()->getTimestamp() + 43_200;
        self::assertSame($end, $tokens->introspect($client, (string) $response['refresh_token'])['exp'], 'No refresh token outlives the maximum age of its session.');

        $this->services->clock->modify('+43199 seconds');
        $refreshed = $tokens->refresh($client, (string) $response['refresh_token'], null);
        self::assertSame($end, $tokens->introspect($client, (string) $refreshed['refresh_token'])['exp']);
        self::assertFalse($this->store->get($session->id())?->isClosed());

        $this->services->clock->modify('+1 second');
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $refreshed['refresh_token'], null));
    }

    public function testARefreshPastTheMaximumAgeClosesTheSessionNeverTouchedAgain(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $session = new Sessions($this->store, $this->services->journal, $this->services->clock, idleTimeout: null, maxAge: null)->open($account, 'Firefox on Linux', ['pwd']);
        $response = $this->tokens(null)->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER);
        $tokens = $this->tokens($this->sessions(null, 3600));
        $this->services->clock->modify('+3599 seconds');
        $refreshed = $tokens->refresh($client, (string) $response['refresh_token'], null);
        $issuedBefore = $this->tokens(null)->refresh($client, (string) $refreshed['refresh_token'], null);

        $this->services->clock->modify('+1 second');

        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $issuedBefore['refresh_token'], null));
        self::assertTrue($this->store->get($session->id())?->isClosed(), 'A session never touched again is closed by the refresh.');
        self::assertContains([IdentityEventType::SessionClosed->value, $account->id(), ['session' => $session->id(), 'reason' => 'max_age']], $this->services->audit->records);
        self::assertSame(['active' => false], $tokens->introspect($client, (string) $issuedBefore['access_token']), 'The tokens of the session are revoked.');
        self::assertNotContains(IdentityEventType::TokenReuseDetected->value, $this->services->audit->actions());
    }

    public function testARefreshCountsAsTheActivityOfItsSessionUntilTheIdleTimeout(): void
    {
        $sessions = $this->sessions(7200, null);
        $account = $this->services->account();
        $client = $this->client();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $tokens = $this->tokens($sessions);
        $refresh = (string) $tokens->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER)['refresh_token'];

        for ($round = 0; $round < 3; ++$round) {
            $this->services->clock->modify('+7199 seconds');
            $refresh = (string) $tokens->refresh($client, $refresh, null)['refresh_token'];
            self::assertSame($this->services->clock->now()->getTimestamp(), $this->store->get($session->id())?->lastSeenAt()->getTimestamp(), 'The refresh is seen as activity.');
        }

        $this->services->clock->modify('+7200 seconds');
        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, $refresh, null));
        self::assertTrue($this->store->get($session->id())?->isClosed());
        self::assertSame([IdentityEventType::SessionClosed->value, $account->id(), ['session' => $session->id(), 'reason' => 'idle_timeout']], array_find($this->services->audit->records, static fn (array $record): bool => IdentityEventType::SessionClosed->value === $record[0]));
    }

    public function testTheEarlierOfTheTwoSessionBoundsCapsTheRefreshToken(): void
    {
        $account = $this->services->account();
        $client = $this->client();
        $session = $this->sessions(null, 7200)->open($account, 'Firefox on Linux', ['pwd']);
        $start = $session->createdAt()->getTimestamp();

        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, sessionMaxAge: 3600);
        $tokens = $this->tokens($this->sessions(null, 7200));
        $early = $tokens->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER);
        self::assertSame($start + 3600, $tokens->introspect($client, (string) $early['refresh_token'])['exp']);

        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, sessionMaxAge: 10_800);
        $tokens = $this->tokens($this->sessions(null, 7200));
        $late = $tokens->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER);
        self::assertSame($start + 7200, $tokens->introspect($client, (string) $late['refresh_token'])['exp']);

        $tokens = $this->tokens($this->sessions(null, null));
        $unbounded = $tokens->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER);
        self::assertSame($start + 10_800, $tokens->introspect($client, (string) $unbounded['refresh_token'])['exp'], 'Without a maximum age, the provider bound alone caps the token.');
    }

    public function testARefreshTokenNamingASessionOfAnotherAccountIsRefused(): void
    {
        $sessions = $this->sessions(null, null);
        $account = $this->services->account();
        $other = $sessions->open($this->services->account('grace@example.com'), 'Safari on macOS', ['pwd']);
        $client = $this->client();
        $tokens = $this->tokens($sessions);
        $response = $tokens->exchangeCode($client, $this->code($account, $other->id()), self::CALLBACK, self::VERIFIER);

        $this->assertOAuthError('invalid_grant', static fn (): array => $tokens->refresh($client, (string) $response['refresh_token'], null));
        self::assertFalse($this->store->get($other->id())?->isClosed(), 'The session of the other account stays open.');
    }

    public function testAnUnboundRefreshTokenLeavesTheSessionAlone(): void
    {
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, refreshBoundToSession: false);
        $sessions = $this->sessions(7200, 3600);
        $account = $this->services->account();
        $client = $this->client();
        $session = $sessions->open($account, 'Firefox on Linux', ['pwd']);
        $tokens = $this->tokens($sessions);
        $response = $tokens->exchangeCode($client, $this->code($account, $session->id()), self::CALLBACK, self::VERIFIER);

        $this->services->clock->modify('+2 hours');

        self::assertArrayHasKey('refresh_token', $tokens->refresh($client, (string) $response['refresh_token'], null));
        $stored = $this->store->get($session->id());
        self::assertInstanceOf(AccountSession::class, $stored);
        self::assertFalse($stored->isClosed());
        self::assertSame($session->createdAt()->getTimestamp(), $stored->lastSeenAt()->getTimestamp(), 'An unbound refresh is not the activity of the session.');
    }

    public function testTheEndOfASessionIsItsMaximumAgeAfterItOpened(): void
    {
        $session = AccountSession::open('s', 'a', 'Firefox', [], false, $this->services->clock->now());

        self::assertSame($session->createdAt()->getTimestamp() + 600, $this->sessions(null, 600)->endsAt($session)?->getTimestamp());
        self::assertNull($this->sessions(600, null)->endsAt($session));
    }

    private function sessions(?int $idleTimeout, ?int $maxAge): Sessions
    {
        return new Sessions($this->store, $this->services->journal, $this->services->clock, null, $this->grants, 300, null, null, $idleTimeout, $maxAge);
    }

    private function tokens(?Sessions $sessions): Tokens
    {
        $keys = new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, new LockFactory(new InMemoryStore()), $this->services->journal);

        return new Tokens($this->clients, $this->grants, $this->services->accounts, $keys, $this->settings, $this->services->clock, $this->services->journal, $this->store, $sessions);
    }

    private function client(): Client
    {
        [$client] = new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal)->register('app', 'App', [self::CALLBACK], [], ['offline_access']);

        return $client;
    }

    private function code(Account $account, string $sessionId): string
    {
        $authorization = new Authorization($this->clients, $this->grants, $this->settings, $this->services->clock, $this->services->journal);
        $request = $authorization->parse(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => self::CALLBACK, 'scope' => 'openid offline_access', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);

        return Query::get($authorization->issueCode($request, $account, $this->services->clock->now(), [], $sessionId), 'code');
    }

    private function assertOAuthError(string $error, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$error);
        } catch (OAuthError $oAuthError) {
            self::assertSame($error, $oAuthError->error);
        }
    }
}
