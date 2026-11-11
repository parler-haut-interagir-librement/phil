<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Unit\Application;

use Phil\PhoneBundle\Application\Regions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Regions::class)]
final class RegionsTest extends TestCase
{
    public function testACountryWithAPlanIsARegion(): void
    {
        self::assertTrue(Regions::isCountry('CA'));
        self::assertFalse(Regions::isCountry('ca'));
        self::assertFalse(Regions::isCountry('001'));
        self::assertFalse(Regions::isCountry('QQ'));
        self::assertFalse(Regions::isCountry(null));
    }
}
