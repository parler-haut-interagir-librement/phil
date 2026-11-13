<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit;

use Phil\ErrorTracking\Exception\InvalidOption;
use Phil\ErrorTracking\Sampler;
use Phil\ErrorTracking\Tests\Unit\Fixture\FixedEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(Sampler::class)]
final class SamplerTest extends TestCase
{
    public function testEverythingIsKeptByDefault(): void
    {
        $sampler = new Sampler();

        self::assertSame(1.0, $sampler->rate);

        for ($draw = 0; $draw < 100; ++$draw) {
            self::assertTrue($sampler->keep());
        }
    }

    public function testNothingIsKeptAtZero(): void
    {
        $sampler = new Sampler(0.0, new Randomizer(new Mt19937(7)));

        for ($draw = 0; $draw < 100; ++$draw) {
            self::assertFalse($sampler->keep());
        }
    }

    public function testAShareIsKept(): void
    {
        $sampler = new Sampler(0.25, new Randomizer(new Mt19937(42)));
        $kept = 0;

        for ($draw = 0; $draw < 1000; ++$draw) {
            $kept += (int) $sampler->keep();
        }

        self::assertGreaterThan(200, $kept);
        self::assertLessThan(300, $kept);
    }

    public function testADrawEqualToTheRateIsLeftOut(): void
    {
        self::assertFalse(new Sampler(0.25, new Randomizer(new FixedEngine(2 ** 62)))->keep());
        self::assertTrue(new Sampler(0.25, new Randomizer(new FixedEngine(2 ** 62 - 2 ** 11)))->keep());
    }

    #[TestWith([-0.01])]
    #[TestWith([1.01])]
    public function testARateOutsideItsRangeIsRefused(float $rate): void
    {
        $this->expectException(InvalidOption::class);
        $this->expectExceptionMessageIsOrContains('A sample rate lies between 0.0 and 1.0.');

        new Sampler($rate);
    }
}
