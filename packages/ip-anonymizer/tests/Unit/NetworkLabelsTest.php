<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\NetworkLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NetworkLabels::class)]
final class NetworkLabelsTest extends TestCase
{
    public function testGivesTheLabelOfTheLongestPrefix(): void
    {
        $labels = new NetworkLabels([
            '10.0.0.0/8' => 'company',
            '10.1.0.0/16' => 'north',
            '10.1.2.0/24' => 'office',
            '10.1.2.3/32' => 'printer',
            '0.0.0.0/0' => 'world',
            '2001:db8::/32' => 'v6-company',
            '2001:db8:1::/48' => 'v6-north',
        ]);

        self::assertSame('printer', $labels->labelOf(IpAddress::parse('10.1.2.3')));
        self::assertSame('office', $labels->labelOf(IpAddress::parse('10.1.2.4')));
        self::assertSame('north', $labels->labelOf(IpAddress::parse('10.1.3.4')));
        self::assertSame('company', $labels->labelOf(IpAddress::parse('10.2.3.4')));
        self::assertSame('world', $labels->labelOf(IpAddress::parse('11.2.3.4')));
        self::assertSame('v6-north', $labels->labelOf(IpAddress::parse('2001:db8:1::5')));
        self::assertSame('v6-company', $labels->labelOf(IpAddress::parse('2001:db8:2::5')));
        self::assertNull($labels->labelOf(IpAddress::parse('2001:db9::5')));
    }

    public function testKnowsTheFamilyOfEachNetwork(): void
    {
        $labels = new NetworkLabels(['::/0' => 'any-v6']);

        self::assertNull($labels->labelOf(IpAddress::parse('192.0.2.1')));
        self::assertSame('any-v6', $labels->labelOf(IpAddress::parse('2001:db8::1')));
        self::assertNull(new NetworkLabels([])->labelOf(IpAddress::parse('2001:db8::1')));
    }

    public function testOrdersPrefixLengthsWhateverTheirOrderInTheTable(): void
    {
        $labels = new NetworkLabels(['10.1.2.0/24' => 'office', '10.0.0.0/8' => 'company', '10.1.0.0/16' => 'north']);

        self::assertSame('office', $labels->labelOf(IpAddress::parse('10.1.2.4')));
        self::assertSame('north', $labels->labelOf(IpAddress::parse('10.1.3.4')));
    }

    public function testAcceptsALabelOfSixtyFourBytes(): void
    {
        self::assertSame(str_repeat('a', 64), new NetworkLabels(['192.0.2.0/24' => str_repeat('a', 64)])->labelOf(IpAddress::parse('192.0.2.1')));
        self::assertSame('a', new NetworkLabels(['192.0.2.0/24' => 'a'])->labelOf(IpAddress::parse('192.0.2.1')));
    }

    public function testRefusesAnEmptyLabel(): void
    {
        $this->expectException(InvalidNetwork::class);
        $this->expectExceptionMessageIsOrContains('The label of 192.0.2.0/24 is 1 to 64 bytes long.');

        new NetworkLabels(['192.0.2.0/24' => '']);
    }

    public function testRefusesALabelTooLong(): void
    {
        $this->expectException(InvalidNetwork::class);
        $this->expectExceptionMessageIsOrContains('The label of 192.0.2.0/24 is 1 to 64 bytes long.');

        new NetworkLabels(['192.0.2.0/24' => str_repeat('a', 65)]);
    }

    public function testRefusesANetworkListedTwice(): void
    {
        $this->expectException(InvalidNetwork::class);
        $this->expectExceptionMessageIsOrContains('The network 2001:db8::/32 is listed twice.');

        new NetworkLabels(['2001:db8::/32' => 'a', '2001:0db8::/32' => 'b']);
    }

    public function testRefusesAnInvalidNetwork(): void
    {
        $this->expectException(InvalidNetwork::class);

        new NetworkLabels(['192.0.2.1/24' => 'a']);
    }
}
