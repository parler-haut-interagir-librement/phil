<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EmailAddressBundle\Infrastructure\Cache\CacheBounceHistoryRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CacheBounceHistoryRepository::class)]
final class CacheBounceHistoryRepositoryTest extends TestCase
{
    public function testAKeyIsClaimedOnceWhileItsClaimLasts(): void
    {
        $pool = new ArrayAdapter();
        $history = new CacheBounceHistoryRepository($pool);
        $until = new DateTimeImmutable('+1 hour');

        self::assertTrue($history->claim('notice', $until));
        self::assertFalse($history->claim('notice', $until));
        self::assertTrue($history->claim('other', $until));
        self::assertTrue($history->claim('past', new DateTimeImmutable('-1 second')));
        self::assertTrue($history->claim('past', new DateTimeImmutable('+1 hour')));
        self::assertArrayHasKey('phil_email_address.claim.'.hash('xxh128', 'notice'), $pool->getValues());
    }

    public function testTheSoftBouncesWithinTheWindowAreCounted(): void
    {
        $pool = new ArrayAdapter();
        $history = new CacheBounceHistoryRepository($pool);
        $now = new DateTimeImmutable();

        self::assertSame(1, $history->recordSoftBounce('ada', $now->modify('-10 days'), $now->modify('-40 days')));
        self::assertSame(2, $history->recordSoftBounce('ada', $now->modify('-5 days'), $now->modify('-35 days')));
        self::assertSame(1, $history->recordSoftBounce('grace', $now, $now->modify('-30 days')));
        self::assertSame(2, $history->recordSoftBounce('ada', $now, $now->modify('-7 days')));
        self::assertSame(3, $history->recordSoftBounce('ada', $now, $now->modify('-5 days')));

        $key = 'phil_email_address.soft.'.hash('xxh128', 'ada');
        self::assertArrayHasKey($key, $pool->getValues());
        self::assertSame([$now->modify('-5 days')->getTimestamp(), $now->getTimestamp(), $now->getTimestamp()], $pool->getItem($key)->get());

        $history->forgetSoftBounces('ada');
        self::assertSame(1, $history->recordSoftBounce('ada', $now, $now->modify('-30 days')));
        self::assertSame(2, $history->recordSoftBounce('grace', $now, $now->modify('-30 days')));
    }

    public function testTheSoftBouncesExpireWithTheWindow(): void
    {
        $pool = new ArrayAdapter();
        $history = new CacheBounceHistoryRepository($pool);
        $now = new DateTimeImmutable();

        $history->recordSoftBounce('ada', $now->modify('-31 days'), $now->modify('-61 days'));

        self::assertFalse($pool->getItem('phil_email_address.soft.'.hash('xxh128', 'ada'))->isHit());
        self::assertSame(1, $history->recordSoftBounce('ada', $now, $now->modify('-1 day')));
    }

    public function testWhatThePoolHoldsThatIsNoTimeIsDropped(): void
    {
        $pool = new ArrayAdapter();
        $key = 'phil_email_address.soft.'.hash('xxh128', 'ada');
        $now = new DateTimeImmutable();
        $pool->save($pool->getItem($key)->set(['x', $now->getTimestamp(), 1.5]));
        $pool->save($pool->getItem('phil_email_address.soft.'.hash('xxh128', 'grace'))->set('spoiled'));

        $history = new CacheBounceHistoryRepository($pool);

        self::assertSame(2, $history->recordSoftBounce('ada', $now, $now->modify('-1 day')));
        self::assertSame(1, $history->recordSoftBounce('grace', $now, $now->modify('-1 day')));
    }
}
