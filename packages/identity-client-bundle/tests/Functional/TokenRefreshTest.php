<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use Phil\IdentityClientBundle\Application\Event\RefreshTokenRejected;
use Phil\IdentityClientBundle\Application\Event\TokensRefreshed;
use Phil\IdentityClientBundle\Infrastructure\Security\SerializedTokenRefreshListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use Phil\IdentityClientBundle\Infrastructure\Store\EncryptedRefreshOutcomes;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SerializedTokenRefreshListener::class)]
#[CoversClass(EncryptedRefreshOutcomes::class)]
final class TokenRefreshTest extends FunctionalTestCase
{
    public function testAnExpiringAccessTokenIsRenewedOnce(): void
    {
        $this->boot(['refresh' => ['enabled' => true]]);
        $this->signIn();
        $before = $this->securityToken()?->getAttribute(TokenAttribute::ACCESS_TOKEN);

        $this->clock()->modify('+280 seconds');
        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());

        self::assertNotSame($before, $this->securityToken()?->getAttribute(TokenAttribute::ACCESS_TOKEN));
        self::assertSame(['authorization_code', 'refresh_token'], array_column($this->provider()->tokenRequests, 'grant'));
        self::assertCount(1, $this->events()->of(TokensRefreshed::class));
    }

    public function testATokenFarFromExpiryIsLeftAlone(): void
    {
        $this->boot(['refresh' => ['enabled' => true]]);
        $this->signIn();

        $this->clock()->modify('+100 seconds');
        $this->get('/private');

        self::assertSame(['authorization_code'], array_column($this->provider()->tokenRequests, 'grant'));
    }

    public function testARevokedFamilyEndsTheSession(): void
    {
        $this->boot(['refresh' => ['enabled' => true]]);
        $this->signIn();
        $this->provider()->revokeFamily();

        $this->clock()->modify('+280 seconds');

        self::assertTrue($this->get('/private')->isRedirection());
        self::assertCount(1, $this->events()->of(RefreshTokenRejected::class));
    }

    public function testAnUnreachableProviderKeepsTheSession(): void
    {
        $this->boot(['refresh' => ['enabled' => true]]);
        $this->signIn();
        $this->provider()->tokenEndpointDown = true;

        $this->clock()->modify('+280 seconds');

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
        self::assertSame([], $this->events()->of(RefreshTokenRejected::class));
    }

    public function testRefreshIsOffByDefault(): void
    {
        $this->boot();

        self::assertFalse($this->container()->has(EncryptedRefreshOutcomes::class));
    }
}
