<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer\Tests\Unit;

use Phil\IpAnonymizer\FixedSalt;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\NetworkGeneralisation;
use Phil\IpAnonymizer\PerFamily;
use Phil\IpAnonymizer\Pipeline;
use Phil\IpAnonymizer\Salt;
use Phil\IpAnonymizer\ScopeGeneralisation;
use Phil\IpAnonymizer\Truncation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pipeline::class)]
#[CoversClass(PerFamily::class)]
final class CompositionTest extends TestCase
{
    public function testRunsTheStepsInTheirOrderBeforeTheAnonymizer(): void
    {
        $hash = new KeyedHash(new FixedSalt(Salt::of('s', str_repeat('k', 32))));
        $pipeline = new Pipeline($hash, new Truncation(24, 64));

        self::assertSame($pipeline->anonymize(IpAddress::parse('192.0.2.1')), $pipeline->anonymize(IpAddress::parse('192.0.2.200')));
        self::assertSame($hash->anonymize(IpAddress::parse('192.0.2.0')), $pipeline->anonymize(IpAddress::parse('192.0.2.1')));
        self::assertNotSame($pipeline->anonymize(IpAddress::parse('192.0.2.1')), $pipeline->anonymize(IpAddress::parse('192.0.3.1')));

        $ordered = new Pipeline(new NetworkGeneralisation(32, 128), new Truncation(8, 16), new Truncation(16, 32));

        self::assertSame('10.0.0.0/32', $ordered->anonymize(IpAddress::parse('10.1.2.3')));
        self::assertSame('10.1.2.3', new Pipeline(new Truncation(32, 128))->anonymize(IpAddress::parse('10.1.2.3')));
    }

    public function testKeepsTheStepsOfAnAssociativeList(): void
    {
        $pipeline = new Pipeline(new NetworkGeneralisation(32, 128), ...['first' => new Truncation(16, 32)]);

        self::assertSame('10.1.0.0/32', $pipeline->anonymize(IpAddress::parse('10.1.2.3')));
    }

    public function testGivesEachFamilyItsAnonymizer(): void
    {
        $perFamily = new PerFamily(new ScopeGeneralisation(), new Truncation());

        self::assertSame('private', $perFamily->anonymize(IpAddress::parse('10.1.2.3')));
        self::assertSame('2001:db8:1::', $perFamily->anonymize(IpAddress::parse('2001:db8:1:2::3')));
    }
}
