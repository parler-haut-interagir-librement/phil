<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

/**
 * The `AddressScope` of an address, by the longest special-purpose prefix that holds it.
 */
final readonly class ScopeClassifier
{
    private const array RANGES = [
        '0.0.0.0/8' => 'unspecified',
        '10.0.0.0/8' => 'private',
        '100.64.0.0/10' => 'shared',
        '127.0.0.0/8' => 'loopback',
        '169.254.0.0/16' => 'link-local',
        '172.16.0.0/12' => 'private',
        '192.0.0.0/24' => 'reserved',
        '192.0.2.0/24' => 'documentation',
        '192.168.0.0/16' => 'private',
        '198.18.0.0/15' => 'benchmarking',
        '198.51.100.0/24' => 'documentation',
        '203.0.113.0/24' => 'documentation',
        '224.0.0.0/4' => 'multicast',
        '240.0.0.0/4' => 'reserved',
        '::/128' => 'unspecified',
        '::1/128' => 'loopback',
        '64:ff9b::/96' => 'translation',
        '64:ff9b:1::/48' => 'translation',
        '2000::/3' => 'public',
        '2001:2::/48' => 'benchmarking',
        '2001:db8::/32' => 'documentation',
        '3fff::/20' => 'documentation',
        'fc00::/7' => 'private',
        'fe80::/10' => 'link-local',
        'fec0::/10' => 'private',
        'ff00::/8' => 'multicast',
    ];

    private NetworkLabels $ranges;

    public function __construct()
    {
        $this->ranges = new NetworkLabels(self::RANGES);
    }

    public function scopeOf(IpAddress $address): AddressScope
    {
        $scope = $this->ranges->labelOf($address);

        if (null !== $scope) {
            return AddressScope::from($scope);
        }

        return IpFamily::V4 === $address->family ? AddressScope::Public : AddressScope::Reserved;
    }
}
