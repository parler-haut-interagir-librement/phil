<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\Exception\InvalidNetwork;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\LabelGeneralisation;
use Phil\IpAnonymizer\NetworkGeneralisation;
use Phil\IpAnonymizer\NetworkLabels;
use Phil\IpAnonymizer\ScopeGeneralisation;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NetworkGeneralisation::class)]
#[CoversClass(LabelGeneralisation::class)]
#[CoversClass(ScopeGeneralisation::class)]
final class GeneralisationTest extends TestCase
{
    public function testGivesTheNetworkOfAnAddress(): void
    {
        $generalisation = new NetworkGeneralisation();

        self::assertSame(24, $generalisation->ipv4Prefix);
        self::assertSame(48, $generalisation->ipv6Prefix);
        self::assertSame('192.0.2.0/24', $generalisation->anonymize(IpAddress::parse('192.0.2.123')));
        self::assertSame('2001:db8:1234::/48', $generalisation->anonymize(IpAddress::parse('2001:db8:1234:5678::1')));
        self::assertSame('10.0.0.0/8', new NetworkGeneralisation(8, 32)->network(IpAddress::parse('10.1.2.3'))->toString());
        self::assertSame('2001:db8::/32', new NetworkGeneralisation(8, 32)->anonymize(IpAddress::parse('2001:db8:1::1')));
    }

    public function testRefusesAnIpv4PrefixOutOfBounds(): void
    {
        $this->expectException(InvalidNetwork::class);

        new NetworkGeneralisation(33);
    }

    public function testRefusesAnIpv6PrefixOutOfBounds(): void
    {
        $this->expectException(InvalidNetwork::class);

        new NetworkGeneralisation(24, 129);
    }

    public function testGivesTheScopeOfAnAddress(): void
    {
        $generalisation = new ScopeGeneralisation();

        self::assertSame('private', $generalisation->anonymize(IpAddress::parse('10.1.2.3')));
        self::assertSame('public', $generalisation->anonymize(IpAddress::parse('8.8.8.8')));
    }

    public function testGivesTheLabelOfANetworkElseTheFallback(): void
    {
        $labels = new NetworkLabels(['192.0.2.0/24' => 'office']);
        $generalisation = new LabelGeneralisation($labels, new Truncation());

        self::assertSame('office', $generalisation->anonymize(IpAddress::parse('192.0.2.9')));
        self::assertSame('198.51.100.0', $generalisation->anonymize(IpAddress::parse('198.51.100.9')));
        self::assertSame('documentation', new LabelGeneralisation($labels)->anonymize(IpAddress::parse('198.51.100.9')));
    }
}
