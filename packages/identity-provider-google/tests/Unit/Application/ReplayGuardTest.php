<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Application;

use Phil\IdentityProviderGoogle\Application\ReplayGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ReplayGuard::class)]
final class ReplayGuardTest extends TestCase
{
    public function testATokenIsKnownOnceRemembered(): void
    {
        $guard = new ReplayGuard(new ArrayAdapter());

        self::assertFalse($guard->isKnown('issuer', 'id'));
        $guard->remember('issuer', 'id');
        self::assertTrue($guard->isKnown('issuer', 'id'));
        self::assertFalse($guard->isKnown('issuer', 'other'));
        self::assertFalse($guard->isKnown('other', 'id'));
        self::assertFalse($guard->isKnown('issuerid', ''));
    }

    public function testATokenIsForgottenAfterTheLifetime(): void
    {
        $clock = new MockClock();
        $cache = new ArrayAdapter(clock: $clock);
        $guard = new ReplayGuard($cache, 60);
        $guard->remember('issuer', 'id');

        $clock->sleep(59);
        self::assertTrue($guard->isKnown('issuer', 'id'));
        $clock->sleep(2);
        self::assertFalse($guard->isKnown('issuer', 'id'));
    }

    public function testTheDefaultLifetimeIsAWeek(): void
    {
        $clock = new MockClock();
        $guard = new ReplayGuard(new ArrayAdapter(clock: $clock));
        $guard->remember('issuer', 'id');

        $clock->sleep(604_799);
        self::assertTrue($guard->isKnown('issuer', 'id'));
        $clock->sleep(1);
        self::assertFalse($guard->isKnown('issuer', 'id'));
    }

    public function testTheKeyOfATokenStartsWithThePrefix(): void
    {
        $cache = new ArrayAdapter();
        new ReplayGuard($cache)->remember('a', 'b');

        self::assertStringStartsWith('phil_idp_google.set.', (string) array_key_first($cache->getValues()));
    }

    public function testTheIssuerAndTheIdentifierBothTellTokensApart(): void
    {
        $guard = new ReplayGuard(new ArrayAdapter());
        $guard->remember('a', 'b');

        self::assertTrue($guard->isKnown('a', 'b'));
        self::assertFalse($guard->isKnown('b', 'a'));
        self::assertFalse($guard->isKnown('c', 'b'));
        self::assertFalse($guard->isKnown('a', 'c'));
    }
}
