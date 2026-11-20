<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Functional;

use DateTimeImmutable;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEnded;
use Phil\IdentityClientBundle\Application\Event\LocalSessionEndReason;
use Phil\IdentityClientBundle\Infrastructure\Security\ReplayGuardedBackChannelLogout;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionRequirementsListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SignInListener;
use Phil\IdentityClientBundle\Infrastructure\Store\CacheReplayFloor;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(CacheReplayFloor::class)]
#[CoversClass(ReplayGuardedBackChannelLogout::class)]
#[CoversClass(SessionRequirementsListener::class)]
#[CoversClass(SignInListener::class)]
final class ReplayFloorTest extends FunctionalTestCase
{
    private const array FLOOR = ['back_channel_logout' => ['cold_start_floor' => true]];

    public function testASignInRecordsTheFloorOfAColdPool(): void
    {
        $this->boot(self::FLOOR);
        $this->signIn();

        self::assertTrue($this->pool()->getItem(CacheReplayFloor::KEY)->isHit());
        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
    }

    public function testASessionBegunBeforeTheStoreWasEmptiedEnds(): void
    {
        $this->boot(self::FLOOR);
        $this->signIn();
        $this->clock()->modify('+1 minute');
        $this->pool()->clear();

        self::assertTrue($this->get('/private')->isRedirection());
        self::assertNull($this->securityToken());

        $ended = $this->events()->of(LocalSessionEnded::class);
        self::assertCount(1, $ended);
        self::assertSame(LocalSessionEndReason::ReplayFloor, $ended[0]->reason);
    }

    public function testASignInAfterTheStoreWasEmptiedStays(): void
    {
        $this->boot(self::FLOOR);
        $this->pool()->clear();
        $this->signIn();
        $this->clock()->modify('+1 minute');

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
    }

    public function testALogoutTokenIssuedBeforeTheStoreWasEmptiedIsRefused(): void
    {
        $this->boot(self::FLOOR);
        $issuedAt = $this->clock()->now()->getTimestamp();
        $this->clock()->modify('+30 seconds');

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->postLogoutToken($this->provider()->logoutToken(['iat' => $issuedAt]))->getStatusCode());
        self::assertSame(Response::HTTP_OK, $this->postLogoutToken($this->provider()->logoutToken())->getStatusCode());
    }

    public function testAConfiguredFloorEndsTheSessionsBegunBeforeIt(): void
    {
        $this->boot(['back_channel_logout' => ['cold_start_floor' => ['not_before' => new DateTimeImmutable('2026-11-09 12:00:30')->getTimestamp()]]]);
        $this->signIn();

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());

        $this->clock()->modify('+30 seconds');
        self::assertTrue($this->get('/private')->isRedirection());
    }

    public function testTheFloorIsOffByDefault(): void
    {
        $this->boot();
        $this->signIn();
        $this->pool()->clear();

        self::assertSame(Response::HTTP_OK, $this->get('/private')->getStatusCode());
        self::assertFalse($this->container()->has(CacheReplayFloor::class));
    }

    private function pool(): CacheItemPoolInterface
    {
        $pool = $this->container()->get('test.cache');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);

        return $pool;
    }
}
