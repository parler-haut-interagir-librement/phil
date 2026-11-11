<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Application;

use Phil\AddressBundle\Application\KnownCountries;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnownCountries::class)]
final class KnownCountriesTest extends TestCase
{
    public function testACountryIsItsCodeInCapitals(): void
    {
        self::assertSame([true, false, false, false], [KnownCountries::has('CA'), KnownCountries::has('ca'), KnownCountries::has('ZZ'), KnownCountries::has(null)]);
    }
}
