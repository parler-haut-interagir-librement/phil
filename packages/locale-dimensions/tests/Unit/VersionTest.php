<?php

declare(strict_types=1);

namespace Phil\LocaleDimensions\Tests\Unit;

use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version::class)]
#[CoversClass(InvalidLocaleDimension::class)]
final class VersionTest extends TestCase
{
    public function testTheFirstVersionIsOne(): void
    {
        self::assertSame(1, Version::first()->number);
    }

    public function testNextCountsUpByOne(): void
    {
        self::assertSame(2, Version::first()->next()->number);
        self::assertSame(8, Version::of(7)->next()->number);
    }

    public function testNextLeavesTheOriginalUntouched(): void
    {
        $version = Version::of(3);
        $version->next();

        self::assertSame(3, $version->number);
    }

    public function testAStoredVersionOfOneIsAccepted(): void
    {
        self::assertTrue(Version::of(1)->equals(Version::first()));
    }

    /**
     * Zero is the edge: it would read as "no version yet" in one place and as a real row in another.
     */
    public function testVersionZeroIsRefused(): void
    {
        $this->expectException(InvalidLocaleDimension::class);
        $this->expectExceptionMessageIsOrContains('A version is counted from 1; got 0.');

        Version::of(0);
    }

    public function testANegativeVersionIsRefused(): void
    {
        $this->expectException(InvalidLocaleDimension::class);

        Version::of(-1);
    }

    public function testOrdering(): void
    {
        self::assertTrue(Version::of(2)->isAfter(Version::of(1)));
        self::assertFalse(Version::of(1)->isAfter(Version::of(2)));
        self::assertFalse(Version::of(2)->isAfter(Version::of(2)));
    }

    public function testTwoDifferentVersionsAreNotEqual(): void
    {
        self::assertFalse(Version::of(1)->equals(Version::of(2)));
    }
}
