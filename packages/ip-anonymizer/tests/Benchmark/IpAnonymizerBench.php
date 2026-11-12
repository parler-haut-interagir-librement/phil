<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Benchmark;

use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\ForgottenSalt;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\NetworkGeneralisation;
use Phil\IpAnonymizer\NetworkLabels;
use Phil\IpAnonymizer\Pipeline;
use Phil\IpAnonymizer\RotatingSalt;
use Phil\IpAnonymizer\Salt;
use Phil\IpAnonymizer\ScopeGeneralisation;
use Phil\IpAnonymizer\Tests\Unit\Fixture\FrozenClock;
use Phil\IpAnonymizer\Tests\Unit\Fixture\InMemorySaltStore;
use Phil\IpAnonymizer\Truncation;

/**
 * An address is anonymized on every request that logs or counts it: the reading of the text, each strategy, and a
 * lookup in a table of a thousand labelled networks.
 */
final readonly class IpAnonymizerBench
{
    private IpAddress $ipv4;

    private IpAddress $ipv6;

    private Truncation $truncation;

    private KeyedHash $fixedHash;

    private KeyedHash $rotatingHash;

    private KeyedHash $forgottenHash;

    private Pipeline $truncatedHash;

    private NetworkGeneralisation $network;

    private ScopeGeneralisation $scope;

    private NetworkLabels $labels;

    public function __construct()
    {
        $this->ipv4 = IpAddress::parse('203.0.113.45');
        $this->ipv6 = IpAddress::parse('2001:db8:1234:5678:9abc:def0:1234:5678');
        $this->truncation = new Truncation();
        $this->fixedHash = new KeyedHash(new FixedSalt(Salt::of('bench', str_repeat('k', 32))));
        $this->rotatingHash = new KeyedHash(new RotatingSalt(str_repeat('m', 32), new FrozenClock()));
        $this->forgottenHash = new KeyedHash(new ForgottenSalt(new InMemorySaltStore(), new FrozenClock()));
        $this->truncatedHash = new Pipeline($this->fixedHash, new Truncation(32, 64));
        $this->network = new NetworkGeneralisation();
        $this->scope = new ScopeGeneralisation();

        $table = [];

        for ($i = 0; $i < 1000; ++$i) {
            $table[\sprintf('%d.%d.0.0/16', 1 + intdiv($i, 256), $i % 256)] = 'region-'.($i % 12);
        }

        $this->labels = new NetworkLabels($table);
    }

    public function benchParseIpv4(): void
    {
        IpAddress::parse('203.0.113.45');
    }

    public function benchParseIpv6(): void
    {
        IpAddress::parse('2001:db8:1234:5678:9abc:def0:1234:5678');
    }

    public function benchFormatIpv6(): void
    {
        $this->ipv6->toString();
    }

    public function benchTruncateIpv4(): void
    {
        $this->truncation->anonymize($this->ipv4);
    }

    public function benchTruncateIpv6(): void
    {
        $this->truncation->anonymize($this->ipv6);
    }

    public function benchKeyedHashFixedSalt(): void
    {
        $this->fixedHash->anonymize($this->ipv4);
    }

    public function benchKeyedHashRotatingSalt(): void
    {
        $this->rotatingHash->anonymize($this->ipv4);
    }

    public function benchKeyedHashForgottenSalt(): void
    {
        $this->forgottenHash->anonymize($this->ipv4);
    }

    public function benchKeyedHashOfATruncatedIpv6(): void
    {
        $this->truncatedHash->anonymize($this->ipv6);
    }

    public function benchNetwork(): void
    {
        $this->network->anonymize($this->ipv6);
    }

    public function benchScope(): void
    {
        $this->scope->anonymize($this->ipv6);
    }

    public function benchLabelLookupInAThousandNetworks(): ?string
    {
        return $this->labels->labelOf($this->ipv4);
    }
}
