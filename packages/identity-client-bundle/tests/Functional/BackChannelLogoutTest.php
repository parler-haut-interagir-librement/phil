<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Phil\IdentityClientBundle\Application\Event\LocalSessionEnded;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEndReason;
use Phil\IdentityClientBundle\Application\Event\ProviderSessionEnded;
use Phil\IdentityClientBundle\Infrastructure\Security\EndedSessionListener;
use Phil\IdentityClientBundle\Infrastructure\Security\ReplayGuardedBackChannelLogout;
use Phil\IdentityClientBundle\Infrastructure\Store\CacheReplayStore;
use Phil\IdentityClientBundle\Tests\Functional\App\FakeOidcProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ReplayGuardedBackChannelLogout::class)]
#[CoversClass(EndedSessionListener::class)]
#[CoversClass(CacheReplayStore::class)]
final class BackChannelLogoutTest extends FunctionalTestCase
{
    public function testALogoutTokenEndsTheLocalSessionItNames(): void
    {
        $this->boot();
        $this->signIn();
        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());

        self::assertSame(Response::HTTP_OK, $this->postLogoutToken($this->provider()->logoutToken())->getStatusCode());

        self::assertTrue($this->get('/private')->isRedirection());
        self::assertNull($this->securityToken());

        $ended = $this->events()->of(ProviderSessionEnded::class);
        self::assertCount(1, $ended);
        self::assertSame(FakeOidcProvider::SESSION_ID, $ended[0]->providerSessionId);
        $local = $this->events()->of(LocalSessionEnded::class);
        self::assertCount(1, $local);
        self::assertSame('subject-1', $local[0]->subject);
        self::assertSame(FakeOidcProvider::SESSION_ID, $local[0]->providerSessionId);
        self::assertSame(LocalSessionEndReason::ProviderLogout, $local[0]->reason);
    }

    public function testALogoutTokenForAnotherSessionLeavesThisOneAlone(): void
    {
        $this->boot();
        $this->signIn();

        $this->postLogoutToken($this->provider()->logoutToken(['sid' => 'another-session']));

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
    }

    public function testAReplayedTokenIsRefused(): void
    {
        $this->boot();
        $token = $this->provider()->logoutToken();

        self::assertSame(Response::HTTP_OK, $this->postLogoutToken($token)->getStatusCode());
        $replay = $this->postLogoutToken($token);

        self::assertSame(Response::HTTP_BAD_REQUEST, $replay->getStatusCode());
        self::assertStringContainsString('already used', (string) $replay->getContent());
        self::assertCount(1, $this->events()->of(ProviderSessionEnded::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidClaims(): iterable
    {
        yield 'a nonce' => [['nonce' => 'an-id-token']];
        yield 'no session' => [['sid' => null]];
        yield 'no jti' => [['jti' => null]];
        yield 'an empty jti' => [['jti' => '']];
        yield 'a numeric jti' => [['jti' => 7]];
        yield 'no events' => [['events' => null]];
        yield 'another audience' => [['aud' => 'another-client']];
        yield 'another issuer' => [['iss' => 'https://evil.example.test']];
        yield 'expired' => [['exp' => 1]];
    }

    /**
     * @param array<string, mixed> $claims
     */
    #[DataProvider('invalidClaims')]
    public function testAnInvalidTokenIsRefusedAndEndsNothing(array $claims): void
    {
        $this->boot();
        $this->signIn();

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->postLogoutToken($this->provider()->logoutToken($claims))->getStatusCode());

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
        self::assertSame([], $this->events()->of(ProviderSessionEnded::class));
    }

    public function testAnUnsignedTokenIsRefused(): void
    {
        $this->assertForgeryRefused(FakeOidcProvider::encode(['alg' => 'none'], $this->claims()));
    }

    public function testAKeyAsASecretIsRefused(): void
    {
        $input = FakeOidcProvider::b64((string) json_encode(['alg' => 'HS256', 'kid' => FakeOidcProvider::key()->get('kid')])).'.'.FakeOidcProvider::b64((string) json_encode($this->claims()));

        $this->assertForgeryRefused($input.'.'.FakeOidcProvider::b64(hash_hmac('sha256', $input, (string) json_encode(FakeOidcProvider::key()->toPublic()), true)));
    }

    public function testAnotherAlgorithmThanThePinnedOneIsRefused(): void
    {
        $this->assertForgeryRefused(FakeOidcProvider::sign($this->claims(), 'RS512'));
    }

    public function testTheEndpointCanBeTurnedOff(): void
    {
        $this->boot(['back_channel_logout' => ['enabled' => false]]);

        $this->browser()->request('POST', '/oidc/back-channel-logout', ['logout_token' => 'x']);

        self::assertSame(Response::HTTP_NOT_FOUND, $this->browser()->getResponse()->getStatusCode());
        self::assertFalse($this->container()->has(CacheReplayStore::class));
    }

    public function testTheEndpointPathIsConfigurable(): void
    {
        $this->boot(['back_channel_logout' => ['path' => '/hooks/logout']]);
        $token = $this->provider()->logoutToken();

        $this->browser()->request('POST', '/hooks/logout', ['logout_token' => $token]);

        self::assertSame(Response::HTTP_OK, $this->browser()->getResponse()->getStatusCode());
    }

    private function assertForgeryRefused(string $token): void
    {
        $this->boot();

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->postLogoutToken($token)->getStatusCode());
        self::assertSame([], $this->events()->of(ProviderSessionEnded::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(): array
    {
        return [
            'iss' => FakeOidcProvider::ISSUER,
            'aud' => FakeOidcProvider::CLIENT_ID,
            'iat' => 1_793_000_000,
            'exp' => 1_894_000_000,
            'jti' => 'forged',
            'sid' => FakeOidcProvider::SESSION_ID,
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new stdClass()],
        ];
    }
}
