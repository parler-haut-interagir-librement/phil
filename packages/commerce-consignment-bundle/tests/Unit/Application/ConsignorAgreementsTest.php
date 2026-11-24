<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Application;

use Phil\CommerceConsignmentBundle\Application\ConsignorAgreements;
use Phil\CommerceConsignmentBundle\Domain\Consignor\StatementFrequency;
use Phil\CommerceConsignmentBundle\Domain\Consignor\UnsoldPolicy;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Infrastructure\Memory\InMemoryConsignorRepository;
use Phil\CommerceConsignmentBundle\Tests\Unit\Support\Fixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConsignorAgreements::class)]
#[CoversClass(ConsignmentRefused::class)]
final class ConsignorAgreementsTest extends TestCase
{
    private ConsignorAgreements $agreements;

    protected function setUp(): void
    {
        $this->agreements = new ConsignorAgreements(new InMemoryConsignorRepository());
    }

    public function testTheFirstVersionMayCoverTheUnitsAlreadyOnHand(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $first = $this->agreements->agree($author, Fixtures::utc('2026-01-01'), 60, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-11-20'));

        self::assertNull($this->agreements->agreementAt($author, Fixtures::utc('2025-12-31 23:59:59')));
        self::assertSame($first, $this->agreements->agreementAt($author, Fixtures::utc('2026-01-01')));
        self::assertSame($first, $this->agreements->agreementAt($author, Fixtures::utc('2030-01-01')));
        self::assertNull($this->agreements->agreementAt(Owner::consignor('someone-else'), Fixtures::utc('2030-01-01')));
    }

    public function testAnExtendedConsignmentIsExtendedOnceByDefault(): void
    {
        $agreement = $this->agreements->agree(Owner::consignor(Fixtures::AUTHOR), Fixtures::utc('2026-01-01'), 30, UnsoldPolicy::Extend, StatementFrequency::Monthly, Fixtures::utc('2026-11-20'), 10);

        self::assertSame(1, $agreement->extensions);
    }

    public function testTheVersionInEffectIsTheLastStartedAtTheInstant(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $first = ConsignmentAgreement::version($author, Fixtures::utc('2026-01-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-01-01'));
        $second = ConsignmentAgreement::version($author, Fixtures::utc('2026-06-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-06-01'));
        $third = ConsignmentAgreement::version($author, Fixtures::utc('2026-09-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-09-01'));

        self::assertNull(ConsignorAgreements::versionAt([$first, $second, $third], Fixtures::utc('2025-12-31')));
        self::assertSame($first, ConsignorAgreements::versionAt([$first, $second, $third], Fixtures::utc('2026-05-31 23:59:59')));
        self::assertSame($second, ConsignorAgreements::versionAt([$first, $second, $third], Fixtures::utc('2026-06-01')));
        self::assertSame($third, ConsignorAgreements::versionAt([$first, $second, $third], Fixtures::utc('2027-01-01')));
        self::assertNull(ConsignorAgreements::versionAt([], Fixtures::utc('2027-01-01')));
    }

    public function testANewVersionNeverChangesThePast(): void
    {
        $author = Owner::consignor(Fixtures::AUTHOR);
        $first = $this->agreements->agree($author, Fixtures::utc('2026-01-01'), 60, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-11-20'));

        foreach (['2026-11-19 23:59:59' => '2026-11-20T00:00:00+00:00', '2026-01-01' => '2026-11-20T00:00:00+00:00'] as $from => $since) {
            try {
                $this->agreements->agree($author, Fixtures::utc($from), 90, UnsoldPolicy::BuyOut, StatementFrequency::Weekly, Fixtures::utc('2026-11-20'));
                self::fail('An agreement changed the past.');
            } catch (ConsignmentRefused $refused) {
                self::assertSame(\sprintf('A new agreement with author-ann cannot apply from %s: it starts at %s or later, so that it never changes the past.', Fixtures::utc($from)->format(\DATE_ATOM), $since), $refused->getMessage());
            }
        }

        $second = $this->agreements->agree($author, Fixtures::utc('2026-11-20'), 90, UnsoldPolicy::BuyOut, StatementFrequency::Weekly, Fixtures::utc('2026-11-20'));
        self::assertSame($first, $this->agreements->agreementAt($author, Fixtures::utc('2026-11-19 23:59:59')));
        self::assertSame($second, $this->agreements->agreementAt($author, Fixtures::utc('2026-11-20')));

        // A version written ahead: the next one starts after it, even when written before it applies.
        $third = $this->agreements->agree($author, Fixtures::utc('2027-01-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-11-21'));
        self::assertSame($third, $this->agreements->agreementAt($author, Fixtures::utc('2027-06-01')));

        try {
            $this->agreements->agree($author, Fixtures::utc('2027-01-01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-11-22'));
            self::fail('Two versions start at the same instant.');
        } catch (ConsignmentRefused $consignmentRefused) {
            self::assertStringContainsString('cannot apply from 2027-01-01T00:00:00+00:00: it starts at 2027-01-01T00:00:01+00:00 or later', $consignmentRefused->getMessage());
        }

        self::assertSame('2027-01-01 00:00:01', $this->agreements->agree($author, Fixtures::utc('2027-01-01 00:00:01'), 30, UnsoldPolicy::ReturnToConsignor, StatementFrequency::Monthly, Fixtures::utc('2026-11-22'))->effectiveFrom()->format('Y-m-d H:i:s'));
    }
}
