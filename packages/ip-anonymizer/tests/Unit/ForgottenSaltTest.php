<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidOption;
use Phil\IpAnonymizer\ForgottenSalt;
use Phil\IpAnonymizer\Internal\SaltPeriod;
use Phil\IpAnonymizer\Salt;
use Phil\IpAnonymizer\Tests\Unit\Fixture\FrozenClock;
use Phil\IpAnonymizer\Tests\Unit\Fixture\InMemorySaltStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ForgottenSalt::class)]
#[CoversClass(SaltPeriod::class)]
final class ForgottenSaltTest extends TestCase
{
    public function testTheFirstDigestOfAPeriodClaimsRandomBytesUntilItsEnd(): void
    {
        $clock = new FrozenClock();
        $clock->set('2025-11-08T23:59:00+00:00');

        $store = new InMemorySaltStore();

        $salt = new ForgottenSalt($store, $clock)->current();

        self::assertSame('86400-20400', $salt->id);
        self::assertSame(Salt::RECOMMENDED_BYTES, \strlen($salt->bytes));
        self::assertSame(['86400-20400' => ['bytes' => $salt->bytes, 'ttl' => 60]], $store->entries);
    }

    public function testTheSaltLastsToTheLastSecondOfThePeriod(): void
    {
        $clock = new FrozenClock();
        $store = new InMemorySaltStore();
        $provider = new ForgottenSalt($store, $clock);
        $clock->set('2025-11-08T00:00:00+00:00');
        $first = $provider->current();

        self::assertSame(86400, $store->entries['86400-20400']['ttl']);

        $clock->set('2025-11-08T23:59:59+00:00');

        self::assertSame($first->bytes, $provider->current()->bytes);
        self::assertSame($first->id, $provider->current()->id);
    }

    public function testTwoServersOverOneStoreHoldTheSameSalt(): void
    {
        $clock = new FrozenClock();
        $store = new InMemorySaltStore();

        self::assertSame(
            new ForgottenSalt($store, $clock)->current()->bytes,
            new ForgottenSalt($store, $clock)->current()->bytes,
        );
    }

    public function testTheNextPeriodDrawsAnUnrelatedSalt(): void
    {
        $clock = new FrozenClock();
        $store = new InMemorySaltStore();
        $provider = new ForgottenSalt($store, $clock);
        $clock->set('2025-11-08T12:00:00+00:00');
        $today = $provider->current();
        $clock->set('2025-11-09T00:00:00+00:00');
        $tomorrow = $provider->current();

        self::assertSame('86400-20401', $tomorrow->id);
        self::assertNotSame($today->bytes, $tomorrow->bytes);
    }

    public function testCountsPeriodsInUtc(): void
    {
        $clock = new FrozenClock();
        $clock->set('2025-11-08T23:30:00-05:00');

        self::assertSame('86400-20401', new ForgottenSalt(new InMemorySaltStore(), $clock)->current()->id);
    }

    public function testUsesThePeriodItIsGiven(): void
    {
        $clock = new FrozenClock();
        $clock->set('@7199');

        $store = new InMemorySaltStore();

        self::assertSame('3600-1', new ForgottenSalt($store, $clock, 3600)->current()->id);
        self::assertSame(1, $store->entries['3600-1']['ttl']);

        $clock->set('@0');

        self::assertSame('1-0', new ForgottenSalt($store, $clock, 1)->current()->id);
        self::assertSame(1, $store->entries['1-0']['ttl']);
    }

    public function testRefusesAPeriodShorterThanASecond(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('A salt period is at least one second.');

        new ForgottenSalt(new InMemorySaltStore(), new FrozenClock(), 0);
    }

    public function testRefusesAnInstantBeforeTheEpoch(): void
    {
        $clock = new FrozenClock();
        $clock->set('@-1');

        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('Salt periods count from the Unix epoch; the clock reads an earlier instant.');

        new ForgottenSalt(new InMemorySaltStore(), $clock)->current();
    }
}
