<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Internal\PrefixMask;
use Phil\IpAnonymizer\IpFamily;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrefixMask::class)]
final class PrefixMaskTest extends TestCase
{
    /**
     * @return iterable<string, array{IpFamily, int, string}>
     */
    public static function masks(): iterable
    {
        yield 'IPv4 /0' => [IpFamily::V4, 0, '00000000'];
        yield 'IPv4 /1' => [IpFamily::V4, 1, '80000000'];
        yield 'IPv4 /7' => [IpFamily::V4, 7, 'fe000000'];
        yield 'IPv4 /8' => [IpFamily::V4, 8, 'ff000000'];
        yield 'IPv4 /20' => [IpFamily::V4, 20, 'fffff000'];
        yield 'IPv4 /32' => [IpFamily::V4, 32, 'ffffffff'];
        yield 'IPv6 /0' => [IpFamily::V6, 0, str_repeat('00', 16)];
        yield 'IPv6 /48' => [IpFamily::V6, 48, str_repeat('ff', 6).str_repeat('00', 10)];
        yield 'IPv6 /61' => [IpFamily::V6, 61, str_repeat('ff', 7).'f8'.str_repeat('00', 8)];
        yield 'IPv6 /128' => [IpFamily::V6, 128, str_repeat('ff', 16)];
    }

    #[DataProvider('masks')]
    public function testSetsTheBitsOfThePrefix(IpFamily $family, int $length, string $hex): void
    {
        self::assertSame($hex, bin2hex(PrefixMask::of($family, $length)));
    }
}
