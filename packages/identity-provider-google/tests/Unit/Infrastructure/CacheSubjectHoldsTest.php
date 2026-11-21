<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderGoogle\Infrastructure\Cache\CacheSubjectHolds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CacheSubjectHolds::class)]
final class CacheSubjectHoldsTest extends TestCase
{
    public function testASubjectIsHeldUntilReleased(): void
    {
        $holds = new CacheSubjectHolds(new ArrayAdapter());

        self::assertFalse($holds->isHeld('google', 's'));
        $holds->hold('google', 's');
        self::assertTrue($holds->isHeld('google', 's'));
        self::assertFalse($holds->isHeld('google', 't'));
        self::assertFalse($holds->isHeld('googles', ''));
        $holds->release('google', 's');
        self::assertFalse($holds->isHeld('google', 's'));
    }

    public function testAHoldDoesNotExpire(): void
    {
        $cache = new ArrayAdapter(defaultLifetime: 0);
        new CacheSubjectHolds($cache)->hold('google', 's');

        $items = $cache->getValues();
        self::assertCount(1, $items);
        self::assertStringStartsWith('phil_idp_google.hold.', (string) array_key_first($items));
    }

    public function testTheProviderAndTheSubjectBothTellHoldsApart(): void
    {
        $holds = new CacheSubjectHolds(new ArrayAdapter());
        $holds->hold('google', 's');

        self::assertFalse($holds->isHeld('other', 's'));
        self::assertFalse($holds->isHeld('s', 'google'));
    }
}
