<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\OAuthError;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
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
 * The tokens down to their shape: the sizes of the secrets, the headers, the claims and what is journaled, and the
 * tokens that fail in each of the ways a token can.
 */
#[CoversClass(Authorization::class)]
#[CoversClass(Tokens::class)]
final class ProviderDetailsTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InMemoryGrantRepository $grants;

    private SigningKeys $keys;

    private ProviderSettings $settings;

    private Client $client;

    private Account $account;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->grants = new InMemoryGrantRepository();
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256);
        $this->keys = new SigningKeys(new InMemorySigningKeyRepository(), new PlainSecretCipher(), $this->settings, $this->services->clock, new LockFactory(new InMemoryStore()), $this->services->journal);
        $this->account = $this->services->account();
        [$this->client] = new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal)->register('app', 'App', ['https://app.example/cb'], [], ['email', 'offline_access']);
    }

    public function testTheSecretsAndTheClaimsOfTheTokens(): void
    {
        $response = $this->flow('openid email offline_access');
        $now = $this->services->clock->now()->getTimestamp();

        $access = Jws::decode((string) $response['access_token'], $this->keys->publicKey(...));
        self::assertIsString($access['jti']);
        self::assertSame(32, \strlen($access['jti']));
        self::assertSame($now, $access['iat']);
        self::assertSame(['alg' => 'ES256', 'kid' => $this->keys->active()->kid, 'typ' => 'at+jwt'], $this->header((string) $response['access_token']));

        $idToken = Jws::decode((string) $response['id_token'], $this->keys->publicKey(...));
        self::assertSame($now, $idToken['iat']);
        self::assertSame('JWT', $this->header((string) $response['id_token'])['typ']);
        self::assertSame(43, \strlen((string) $response['refresh_token']));

        $issued = array_values(array_filter($this->services->audit->records, static fn (array $record): bool => IdentityEventType::TokensIssued->value === $record[0]));
        self::assertCount(1, $issued);
        self::assertSame($this->account->id(), $issued[0][1]);
        self::assertSame(['client', 'family'], array_keys($issued[0][2]));
        self::assertSame('app', $issued[0][2]['client']);
    }

    public function testARefreshTokenIsRefusedForEachWayItCanBeWrong(): void
    {
        $response = $this->flow('openid email offline_access');
        $tokens = $this->tokens();
        [$other] = new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal)->register('other', 'Other', ['https://app.example/cb'], [], ['offline_access']);
        $now = $this->services->clock->now();
        $this->grants->saveToken(IssuedToken::issue(IssuedToken::idOfRefresh('an-access-token'), TokenKind::Access, 'FAMILY0000000000000000000A', 'app', $this->account->id(), ['openid'], null, $now, [], $now, $now->modify('+1 hour')));

        $this->assertRefresh('The refresh token is unknown.', $this->client, 'unknown');
        $this->assertRefresh('The refresh token is unknown.', $this->client, 'an-access-token');
        $this->assertRefresh('The refresh token is unknown.', $other, (string) $response['refresh_token']);

        $refreshed = $tokens->refresh($this->client, (string) $response['refresh_token'], null);
        self::assertArrayHasKey('refresh_token', $refreshed);
        $this->assertRefresh('The refresh token was already used.', $this->client, (string) $response['refresh_token']);

        $reuse = array_values(array_filter($this->services->audit->records, static fn (array $record): bool => IdentityEventType::TokenReuseDetected->value === $record[0]));
        self::assertCount(1, $reuse);
        self::assertSame($this->account->id(), $reuse[0][1]);
        self::assertSame(['family'], array_keys($reuse[0][2]));
    }

    public function testARefreshKeepsTheScopesAskedOnceEach(): void
    {
        $response = $this->flow('openid email offline_access');
        $tokens = $this->tokens();

        $narrow = $tokens->refresh($this->client, (string) $response['refresh_token'], 'openid offline_access');
        self::assertSame('openid offline_access', $narrow['scope']);

        $other = $this->flow('openid email offline_access');
        $same = $tokens->refresh($this->client, (string) $other['refresh_token'], 'openid email');
        self::assertSame('openid email offline_access', $same['scope']);
    }

    public function testATokenWhoseSignatureIsWrongIsRefusedAsAnyOtherInvalidOne(): void
    {
        $response = $this->flow('openid email offline_access');
        $tokens = $this->tokens();
        $forge = static function (string $token): string {
            $parts = explode('.', $token);
            $parts[2] = Base64Url::encode(str_repeat("\x01", 64));

            return implode('.', $parts);
        };

        $this->assertInvalidToken(static fn (): array => $tokens->bearer($forge((string) $response['access_token'])));
        self::assertNull($tokens->idTokenHint($forge((string) $response['id_token'])));
        $tokens->revoke($this->client, $forge((string) $response['access_token']));
        self::assertSame(['active' => false], $tokens->introspect($this->client, $forge((string) $response['access_token'])));
        self::assertSame($this->account->id(), $tokens->bearer((string) $response['access_token'])[0]->id());
    }

    public function testTheConsentIsJournaledWithItsClient(): void
    {
        $authorization = $this->authorization();
        $request = $authorization->parse($this->query());

        $authorization->grantConsent($this->account, $request);
        self::assertSame([IdentityEventType::ConsentGranted->value, $this->account->id(), ['client' => 'app']], $this->services->audit->last());

        $authorization->withdrawConsent($this->account, 'app');
        self::assertSame([IdentityEventType::ConsentWithdrawn->value, $this->account->id(), ['client' => 'app']], $this->services->audit->last());
    }

    /**
     * @return array<string, int|string>
     */
    private function flow(string $scope): array
    {
        $authorization = $this->authorization();
        $code = Query::get($authorization->issueCode($authorization->parse($this->query(['scope' => $scope])), $this->account, $this->services->clock->now(), [], 'sid'), 'code');

        return $this->tokens()->exchangeCode($this->client, $code, 'https://app.example/cb', self::VERIFIER);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function header(string $token): array
    {
        $header = json_decode(Base64Url::decode(explode('.', $token)[0]), true);
        \assert(\is_array($header));

        return $header;
    }

    private function assertRefresh(string $description, Client $client, string $token): void
    {
        try {
            $this->tokens()->refresh($client, $token, null);
            self::fail('Accepted, expected '.$description);
        } catch (OAuthError $oAuthError) {
            self::assertSame('invalid_grant', $oAuthError->error);
            self::assertSame($description, $oAuthError->description);
        }
    }

    private function assertInvalidToken(callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted.');
        } catch (OAuthError $oAuthError) {
            self::assertSame('invalid_token', $oAuthError->error);
        }
    }

    private function authorization(): Authorization
    {
        return new Authorization($this->clients, $this->grants, $this->settings, $this->services->clock, $this->services->journal);
    }

    private function tokens(): Tokens
    {
        return new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys, $this->settings, $this->services->clock, $this->services->journal);
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, string>
     */
    private function query(array $query = []): array
    {
        return $query + ['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256'];
    }
}
