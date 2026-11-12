<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidOption;
use Phil\IpAnonymizer\Exception\InvalidSalt;
use Phil\IpAnonymizer\Internal\SaltPeriod;
use Phil\IpAnonymizer\RotatingSalt;
use Phil\IpAnonymizer\Tests\Unit\Fixture\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RotatingSalt::class)]
#[CoversClass(SaltPeriod::class)]
final class RotatingSaltTest extends TestCase
{
    private const string MASTER_KEY = '0123456789abcdef0123456789abcdef';

    public function testDerivesTheSaltOfTheDay(): void
    {
        $clock = new FrozenClock();
        $clock->set('2025-11-08T00:00:00+00:00');

        $salt = new RotatingSalt(self::MASTER_KEY, $clock)->current();

        self::assertSame('86400-20400', $salt->id);
        self::assertSame(sodium_crypto_kdf_derive_from_key(32, 20400, 'ipanonym', self::MASTER_KEY), $salt->bytes);
    }

    public function testKeepsTheSaltForTheWholeDay(): void
    {
        $clock = new FrozenClock();
        $provider = new RotatingSalt(self::MASTER_KEY, $clock);
        $clock->set('2025-11-08T00:00:00+00:00');
        $morning = $provider->current();
        $clock->set('2025-11-08T23:59:59+00:00');

        self::assertEquals($morning, $provider->current());

        $clock->set('2025-11-09T00:00:00+00:00');
        $next = $provider->current();

        self::assertSame('86400-20401', $next->id);
        self::assertNotSame($morning->bytes, $next->bytes);
    }

    public function testCountsPeriodsInUtc(): void
    {
        $clock = new FrozenClock();
        $clock->set('2025-11-08T23:30:00-05:00');

        self::assertSame('86400-20401', new RotatingSalt(self::MASTER_KEY, $clock)->current()->id);
    }

    public function testUsesThePeriodItIsGiven(): void
    {
        $clock = new FrozenClock();
        $clock->set('@7199');

        self::assertSame('3600-1', new RotatingSalt(self::MASTER_KEY, $clock, 3600)->current()->id);

        $clock->set('@0');

        self::assertSame('1-0', new RotatingSalt(self::MASTER_KEY, $clock, 1)->current()->id);
    }

    public function testAnotherMasterKeyGivesAnotherSalt(): void
    {
        $clock = new FrozenClock();

        self::assertNotSame(
            new RotatingSalt(self::MASTER_KEY, $clock)->current()->bytes,
            new RotatingSalt(strrev(self::MASTER_KEY), $clock)->current()->bytes,
        );
    }

    public function testRefusesAMasterKeyOfAnotherLength(): void
    {
        $this->expectException(InvalidSalt::class);
        $this->expectExceptionMessageIsOrContains('The master key of a rotating salt is 32 bytes long.');

        new RotatingSalt(substr(self::MASTER_KEY, 1), new FrozenClock());
    }

    public function testRefusesAPeriodShorterThanASecond(): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('A salt period is at least one second.');

        new RotatingSalt(self::MASTER_KEY, new FrozenClock(), 0);
    }

    public function testRefusesAnInstantBeforeTheEpoch(): void
    {
        $clock = new FrozenClock();
        $clock->set('@-1');

        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('Salt periods count from the Unix epoch; the clock reads an earlier instant.');

        new RotatingSalt(self::MASTER_KEY, $clock)->current();
    }

    public function testHidesItsMasterKeyFromDumps(): void
    {
        self::assertSame(
            ['periodSeconds' => 86400, 'masterKey' => '[redacted]'],
            new RotatingSalt(self::MASTER_KEY, new FrozenClock())->__debugInfo(),
        );
    }
}
