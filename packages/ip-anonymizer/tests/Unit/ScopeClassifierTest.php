<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\AddressScope;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\ScopeClassifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScopeClassifier::class)]
#[CoversClass(AddressScope::class)]
final class ScopeClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string, AddressScope}>
     */
    public static function scopes(): iterable
    {
        yield '0.0.0.0/8' => ['0.255.0.1', AddressScope::Unspecified];
        yield '10.0.0.0/8' => ['10.255.255.255', AddressScope::Private];
        yield '100.64.0.0/10' => ['100.127.0.1', AddressScope::Shared];
        yield 'below 100.64.0.0/10' => ['100.63.255.255', AddressScope::Public];
        yield '127.0.0.0/8' => ['127.0.0.1', AddressScope::Loopback];
        yield '169.254.0.0/16' => ['169.254.1.1', AddressScope::LinkLocal];
        yield '172.16.0.0/12' => ['172.31.255.255', AddressScope::Private];
        yield 'past 172.16.0.0/12' => ['172.32.0.0', AddressScope::Public];
        yield '192.0.0.0/24' => ['192.0.0.9', AddressScope::Reserved];
        yield '192.0.2.0/24' => ['192.0.2.1', AddressScope::Documentation];
        yield '192.168.0.0/16' => ['192.168.1.1', AddressScope::Private];
        yield '198.18.0.0/15' => ['198.19.255.255', AddressScope::Benchmarking];
        yield '198.51.100.0/24' => ['198.51.100.7', AddressScope::Documentation];
        yield '203.0.113.0/24' => ['203.0.113.7', AddressScope::Documentation];
        yield '224.0.0.0/4' => ['239.255.255.255', AddressScope::Multicast];
        yield '240.0.0.0/4' => ['240.0.0.1', AddressScope::Reserved];
        yield 'broadcast' => ['255.255.255.255', AddressScope::Reserved];
        yield 'public IPv4' => ['8.8.8.8', AddressScope::Public];
        yield 'mapped private IPv4' => ['::ffff:10.0.0.1', AddressScope::Private];
        yield '::/128' => ['::', AddressScope::Unspecified];
        yield '::1/128' => ['::1', AddressScope::Loopback];
        yield 'IPv4-compatible' => ['::192.0.2.1', AddressScope::Reserved];
        yield '64:ff9b::/96' => ['64:ff9b::c000:201', AddressScope::Translation];
        yield '64:ff9b:1::/48' => ['64:ff9b:1::1', AddressScope::Translation];
        yield '2000::/3' => ['2606:4700::1111', AddressScope::Public];
        yield 'end of 2000::/3' => ['3fff:ffff::1', AddressScope::Public];
        yield '2001:2::/48' => ['2001:2::1', AddressScope::Benchmarking];
        yield '2001:db8::/32' => ['2001:db8::1', AddressScope::Documentation];
        yield '3fff::/20' => ['3fff:fff::1', AddressScope::Documentation];
        yield 'fc00::/7' => ['fd12:3456::1', AddressScope::Private];
        yield 'fe80::/10' => ['fe80::1', AddressScope::LinkLocal];
        yield 'fec0::/10' => ['fec0::1', AddressScope::Private];
        yield 'ff00::/8' => ['ff02::1', AddressScope::Multicast];
        yield 'unassigned IPv6' => ['4000::1', AddressScope::Reserved];
    }

    #[DataProvider('scopes')]
    public function testClassifiesAnAddress(string $address, AddressScope $scope): void
    {
        self::assertSame($scope, new ScopeClassifier()->scopeOf(IpAddress::parse($address)));
    }
}
