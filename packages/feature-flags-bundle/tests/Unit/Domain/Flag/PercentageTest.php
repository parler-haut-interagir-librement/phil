<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\Bucket;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Percentage::class)]
final class PercentageTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([100])]
    public function testTheBoundsAreAccepted(int $value): void
    {
        self::assertSame($value, Percentage::of($value)->value);
    }

    #[TestWith([-1])]
    #[TestWith([101])]
    public function testOutOfBoundsIsRefused(int $value): void
    {
        $this->expectException(InvalidValue::class);

        Percentage::of($value);
    }

    public function testZeroAdmitsNoBucketAndAHundredAdmitsAll(): void
    {
        $flag = FlagName::of('rollout');
        $none = Percentage::of(0);
        $all = Percentage::of(100);

        for ($i = 0; $i < 500; ++$i) {
            $bucket = Bucket::of($flag, 'key-'.$i);
            self::assertFalse($none->admits($bucket));
            self::assertTrue($all->admits($bucket));
        }
    }
}
