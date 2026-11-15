<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Service;

use DateTimeImmutable;
use Phil\Lti\Exception\AccessDenied;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Platform\Token\AccessToken;
use Phil\Lti\Platform\Token\InMemoryAccessTokens;
use Phil\Lti\Platform\Token\ServiceGuard;
use Phil\Lti\Platform\Token\TokenEndpoint;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Security\InMemoryNonces;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Scope;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\Lti;
use Phil\Lti\Tests\Fixture\RecordingNonces;
use Phil\Lti\Tests\Fixture\RecordingServiceClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/**
 * The names, the lifetimes and the depths the token exchange is kept to.
 */
#[CoversClass(AccessTokenProvider::class)]
#[CoversClass(TokenEndpoint::class)]
#[CoversClass(ServiceGuard::class)]
#[CoversClass(InMemoryNonces::class)]
#[CoversClass(PlatformRegistration::class)]
#[CoversClass(ToolRegistration::class)]
final class ServiceEdgesTest extends TestCase
{
    private Lti $lti;

    protected function setUp(): void
    {
        $this->lti = new Lti();
    }

    public function testATokenIsCachedUnderTheHashOfItsPlatformAndItsSortedScopes(): void
    {
        $cache = new ArrayAdapter();
        $client = new RecordingServiceClient(null, RecordingServiceClient::json(200, ['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 3600]));

        new AccessTokenProvider($client, $this->lti->toolKeys, $cache, $this->lti->clock)->token($this->lti->platform, [Scope::SCORE, Scope::LINE_ITEM, Scope::SCORE]);

        self::assertTrue($cache->hasItem('phil_lti.token.'.hash('xxh128', $this->lti->platform->key()."\n".Scope::LINE_ITEM.' '.Scope::SCORE)));
    }

    public function testATokenLivesItsLifetimeLessTheMarginAtLeastASecond(): void
    {
        foreach ([[31, 1], [30, 1], [3600, 3570]] as [$expiresIn, $lives]) {
            $body = ['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => $expiresIn];
            $client = new RecordingServiceClient(null, RecordingServiceClient::json(200, $body), RecordingServiceClient::json(200, $body));
            $clock = new MockClock('2026-11-14 12:00:00 UTC');
            $provider = new AccessTokenProvider($client, $this->lti->toolKeys, new ArrayAdapter(clock: $clock), $clock);

            $provider->token($this->lti->platform, []);
            $clock->sleep($lives - 1);
            $provider->token($this->lti->platform, []);
            self::assertSame(1, $this->sent($client), $expiresIn.' seconds, one second before the end');

            $clock->sleep(1);
            $provider->token($this->lti->platform, []);
            self::assertSame(2, $this->sent($client), $expiresIn.' seconds, at the end');
        }
    }

    public function testATokenWithoutALifetimeLivesAnHourLessTheMargin(): void
    {
        $body = ['access_token' => 'a', 'token_type' => 'Bearer'];
        $client = new RecordingServiceClient(null, RecordingServiceClient::json(200, $body), RecordingServiceClient::json(200, $body));
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $provider = new AccessTokenProvider($client, $this->lti->toolKeys, new ArrayAdapter(clock: $clock), $clock);

        $provider->token($this->lti->platform, []);

        $clock->sleep(3569);
        $provider->token($this->lti->platform, []);
        self::assertSame(1, $this->sent($client));

        $clock->sleep(1);
        $provider->token($this->lti->platform, []);
        self::assertSame(2, $this->sent($client));
    }

    public function testTheAnswerOfTheTokenEndpointIsReadAtFourLevels(): void
    {
        $answer = static fn (int $levels): string => json_encode(['access_token' => 'a', 'token_type' => 'Bearer', 'extra' => self::nested($levels)], \JSON_THROW_ON_ERROR);

        self::assertSame('a', new AccessTokenProvider(new RecordingServiceClient(null, RecordingServiceClient::json(200, $answer(2))), $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock)->token($this->lti->platform, []));

        $this->expectExceptionMessageIsOrContains('The token endpoint answered something that is not JSON.');
        new AccessTokenProvider(new RecordingServiceClient(null, RecordingServiceClient::json(200, $answer(3))), $this->lti->toolKeys, new ArrayAdapter(), $this->lti->clock)->token($this->lti->platform, []);
    }

    public function testTheNoncesOfTheEndpointAreKeptPerClient(): void
    {
        $nonces = new RecordingNonces($this->lti->nonces);
        $endpoint = new TokenEndpoint('https://lms.example.org/token', $this->lti->tools, new InMemoryAccessTokens($this->lti->clock), $nonces, $this->lti->verifier, $this->lti->clock);

        $endpoint->issue($this->request(['jti' => str_repeat('j', 255)]));

        self::assertSame([[TokenEndpoint::JTI_SCOPE, Lti::CLIENT."\n".str_repeat('j', 255)]], $nonces->used);
    }

    public function testTheScopesAreAListWhateverWasAskedFirst(): void
    {
        $endpoint = new TokenEndpoint('https://lms.example.org/token', $this->lti->tools, new InMemoryAccessTokens($this->lti->clock), $this->lti->nonces, $this->lti->verifier, $this->lti->clock);

        $issued = $endpoint->issue($this->request(['scope' => 'unknown '.Scope::SCORE]));

        self::assertSame([Scope::SCORE], $issued->scopes);
        self::assertTrue(array_is_list($issued->scopes));
    }

    public function testTheGuardReadsTheWholeHeader(): void
    {
        $tokens = new InMemoryAccessTokens($this->lti->clock);
        $tokens->save(new AccessToken(AccessToken::hash('abcdefghijklmnopqrstuvwxyz'), Lti::CLIENT, [Scope::SCORE], $this->lti->clock->now()->modify('+1 minute')));

        $guard = new ServiceGuard($tokens);

        self::assertSame(Lti::CLIENT, $guard->authorize('Bearer abcdefghijklmnopqrstuvwxyz', [Scope::SCORE])->clientId);

        foreach (['xBearer abcdefghijklmnopqrstuvwxyz', "Bearer abcdefghijklmnopqrstuvwxyz\n", 'Bearer abcdefghijklmnopqrstuvwxyz '] as $header) {
            try {
                $guard->authorize($header, [Scope::SCORE]);
                self::fail(var_export($header, true).' was accepted.');
            } catch (AccessDenied) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheAudienceOfARegistrationIsItsOwnWhenItHasOne(): void
    {
        $platform = new PlatformRegistration(Lti::ISSUER, Lti::CLIENT, $this->lti->platform->authorizationUrl, $this->lti->platform->tokenUrl, $this->lti->platform->keySetUrl, 'https://lms.example.org/oauth');

        self::assertSame('https://lms.example.org/oauth', $platform->audience());
        self::assertSame('https://lms.example.org/token', $this->lti->platform->audience());
    }

    public function testAToolOwnsTheOriginsOfItsLoginAndItsRedirects(): void
    {
        $tool = new ToolRegistration('c', Url::fromString('https://login.example.com/l'), [Url::fromString('https://launch.example.com/r')], Url::fromString('https://tool.example.com/j'));

        self::assertTrue($tool->owns(Url::fromString('https://login.example.com/other')));
        self::assertTrue($tool->owns(Url::fromString('https://launch.example.com/other')));
        self::assertFalse($tool->owns(Url::fromString('https://tool.example.com/other')));
        self::assertFalse($tool->owns(Url::fromString('https://launch.example.com:8443/other')));
    }

    public function testTheMemoryNoncesKeepScopeAndValueApart(): void
    {
        $nonces = new InMemoryNonces($this->lti->clock);
        $until = new DateTimeImmutable('2026-11-14 13:00:00 UTC');

        self::assertTrue($nonces->use('sa', 'b', $until));
        self::assertTrue($nonces->use('s', 'ab', $until));
        self::assertFalse($nonces->use('s', 'ab', $until));
    }

    /**
     * @param array<string, mixed> $claims
     *
     * @return array<string, mixed>
     */
    private function request(array $claims = []): array
    {
        $now = $this->lti->clock->now()->getTimestamp();
        $assertion = JwtSigner::sign($claims + ['iss' => Lti::CLIENT, 'sub' => Lti::CLIENT, 'aud' => 'https://lms.example.org/token', 'iat' => $now, 'exp' => $now + 60, 'jti' => bin2hex(random_bytes(8))], Keys::tool());

        return ['grant_type' => 'client_credentials', 'client_assertion_type' => AccessTokenProvider::ASSERTION_TYPE, 'client_assertion' => $assertion, 'scope' => $claims['scope'] ?? Scope::SCORE];
    }

    private function sent(RecordingServiceClient $client): int
    {
        return \count($client->requests);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function nested(int $levels): array
    {
        return 1 === $levels ? [] : [self::nested($levels - 1)];
    }
}
