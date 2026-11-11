<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\Bucket;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bucket::class)]
final class BucketTest extends TestCase
{
    /**
     * Pinned values. If one of these changes, every running rollout has just been reshuffled: the change
     * is a one-way door and must be deliberate.
     *
     * @return iterable<string, array{string, string, int}>
     */
    public static function pinned(): iterable
    {
        yield 'murmur3a("new-checkout:tenant-1") = ae1cd475' => ['new-checkout', 'tenant-1', 81];
        yield 'murmur3a("new-checkout:tenant-2") = f7cd9dee' => ['new-checkout', 'tenant-2', 62];
        yield 'murmur3a("other-flag:tenant-1") = 1d1158f5' => ['other-flag', 'tenant-1', 49];
        yield 'murmur3a("new-checkout:42") = f40b6da9' => ['new-checkout', '42', 73];
    }

    #[DataProvider('pinned')]
    public function testTheBucketIsPinned(string $flag, string $key, int $expected): void
    {
        self::assertSame($expected, Bucket::of(FlagName::of($flag), $key)->value);
    }

    public function testTheSameKeyGetsTheSameBucketEveryTime(): void
    {
        $flag = FlagName::of('stable');

        self::assertSame(Bucket::of($flag, 'tenant-7')->value, Bucket::of($flag, 'tenant-7')->value);
    }

    public function testEveryBucketIsBetweenZeroAndNinetyNine(): void
    {
        $flag = FlagName::of('range');

        for ($i = 0; $i < 2000; ++$i) {
            $value = Bucket::of($flag, 'k'.$i)->value;
            self::assertGreaterThanOrEqual(0, $value);
            self::assertLessThan(100, $value);
        }
    }

    /**
     * Ten thousand keys into ten deciles: each decile within 20 % of its expected thousand.
     */
    public function testTheBucketsAreRoughlyUniform(): void
    {
        $flag = FlagName::of('uniform');
        $deciles = array_fill(0, 10, 0);

        for ($i = 0; $i < 10_000; ++$i) {
            ++$deciles[intdiv(Bucket::of($flag, 'tenant-'.$i)->value, 10)];
        }

        foreach ($deciles as $count) {
            self::assertGreaterThan(800, $count);
            self::assertLessThan(1200, $count);
        }
    }

    /**
     * The flag name salts the hash: the tenants first in one rollout are not the ones first in every other.
     */
    public function testTwoFlagsPlaceTheSameKeysIndependently(): void
    {
        $first = FlagName::of('first');
        $second = FlagName::of('second');
        $twenty = Percentage::of(20);
        $inFirst = 0;
        $inBoth = 0;

        for ($i = 0; $i < 10_000; ++$i) {
            $key = 'tenant-'.$i;
            if ($twenty->admits(Bucket::of($first, $key))) {
                ++$inFirst;
                if ($twenty->admits(Bucket::of($second, $key))) {
                    ++$inBoth;
                }
            }
        }

        // Independent: about 20 % of the first rollout is also in the second, not 100 %.
        self::assertGreaterThan(0.1 * $inFirst, $inBoth);
        self::assertLessThan(0.3 * $inFirst, $inBoth);
    }

    public function testRaisingThePercentageOnlyAddsKeys(): void
    {
        $flag = FlagName::of('monotonic');

        for ($i = 0; $i < 1000; ++$i) {
            $bucket = Bucket::of($flag, 'k'.$i);
            if (Percentage::of(10)->admits($bucket)) {
                self::assertTrue(Percentage::of(50)->admits($bucket));
            }
        }
    }

    public function testAnEmptyKeyIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        Bucket::of(FlagName::of('any'), '');
    }
}
