<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Application\ContributionLicence;
use Phil\TermsBundle\Application\ContributionLicences;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\Identifiers;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TermsBundle\Tests\Unit\Fixture\InMemoryAcceptances;
use Phil\TermsBundle\Tests\Unit\Fixture\InMemoryVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ContributionLicences::class)]
#[CoversClass(ContributionLicence::class)]
#[CoversClass(Policies::class)]
#[CoversClass(Terms::class)]
#[CoversClass(Identifiers::class)]
#[CoversClass(Policy::class)]
#[CoversClass(PolicyVersion::class)]
final class ContributionLicencesTest extends TestCase
{
    private InMemoryVersions $versions;

    private InMemoryAcceptances $acceptances;

    private MockClock $clock;

    private Policies $policies;

    private VersionCatalog $catalog;

    private Terms $terms;

    private ContributionLicences $licences;

    protected function setUp(): void
    {
        $this->versions = new InMemoryVersions();
        $this->acceptances = new InMemoryAcceptances();
        $this->clock = new MockClock('2026-05-01 10:00:00');
        $this->policies = new Policies(new Policy('terms'), new Policy('contributions', contributionLicence: true));
        $this->catalog = new VersionCatalog($this->versions);
        $this->terms = new Terms($this->policies, $this->catalog, $this->versions, new PolicyTextRenderer(), $this->acceptances, $this->clock);
        $this->licences = new ContributionLicences($this->policies, $this->catalog, $this->acceptances);
    }

    public function testALicenceIsAnSpdxIdentifier(): void
    {
        self::assertSame('CC-BY-SA-4.0', Identifiers::licence('CC-BY-SA-4.0'));
        self::assertSame('LicenseRef-site', Identifiers::licence('LicenseRef-site'));
        self::assertSame('GPL-2.0+', Identifiers::licence('GPL-2.0+'));
        self::assertSame('0BSD', Identifiers::licence('0BSD'));
        self::assertSame('A'.str_repeat('b', 63), Identifiers::licence('A'.str_repeat('b', 63)));
        self::assertSame(64, Identifiers::MAX_LICENCE_LENGTH);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badLicences(): iterable
    {
        yield 'empty' => [''];
        yield 'an expression' => ['MIT OR Apache-2.0'];
        yield 'a dash first' => ['-MIT'];
        yield 'a dot first' => ['.MIT'];
        yield 'too long' => ['A'.str_repeat('b', 64)];
        yield 'a slash' => ['CC/BY'];
        yield 'a new line at the end' => ["MIT\n"];
    }

    #[DataProvider('badLicences')]
    public function testABadLicenceIsRefused(string $licence): void
    {
        try {
            Identifiers::licence($licence);
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('licence', $invalidTerms->reason);
            self::assertSame(['%licence%' => $licence], $invalidTerms->parameters);
            self::assertStringContainsString('"'.$licence.'" is not', $invalidTerms->getMessage());
        }
    }

    public function testTheLicenceInTheMessageIsCutTo80Characters(): void
    {
        try {
            Identifiers::licence(str_repeat('é', 90));
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame(str_repeat('é', 80), $invalidTerms->parameters['%licence%']);
        }
    }

    public function testAVersionKeepsItsLicenceAndRefusesABadOne(): void
    {
        $at = new DateTimeImmutable('2026-05-01');

        self::assertNull(new PolicyVersion('terms', 1, ['en' => 'x'], TextFormat::Text, $at, $at, true)->licence);
        self::assertSame('CC-BY-4.0', new PolicyVersion('terms', 1, ['en' => 'x'], TextFormat::Text, $at, $at, true, 'CC-BY-4.0')->licence);

        $this->expectExceptionObject(InvalidTerms::because('licence', 'A licence is an SPDX identifier such as "CC-BY-SA-4.0": "%licence%" is not.', ['%licence%' => 'CC BY']));

        new PolicyVersion('terms', 1, ['en' => 'x'], TextFormat::Text, $at, $at, true, 'CC BY');
    }

    public function testThePolicyThatNamesTheLicence(): void
    {
        self::assertFalse(new Policy('terms')->contributionLicence);
        self::assertSame('contributions', $this->policies->contributionLicence()?->key);
        self::assertNull(new Policies(new Policy('terms'))->contributionLicence());
    }

    public function testAVersionOfTheLicensingPolicyNamesALicenceAndNoOtherDoes(): void
    {
        self::assertSame('CC-BY-SA-4.0', $this->terms->publish('contributions', ['en' => 'x'], licence: 'CC-BY-SA-4.0')->licence);
        self::assertNull($this->terms->publish('terms', ['en' => 'x'])->licence);

        try {
            $this->terms->publish('contributions', ['en' => 'x']);
            self::fail('Published.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('licence_required', $invalidTerms->reason);
            self::assertSame(['%policy%' => 'contributions'], $invalidTerms->parameters);
        }

        try {
            $this->terms->publish('terms', ['en' => 'x'], licence: 'MIT');
            self::fail('Published.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('licence_not_expected', $invalidTerms->reason);
            self::assertSame(['%policy%' => 'terms'], $invalidTerms->parameters);
        }

        self::assertCount(1, $this->catalog->versions('contributions'));
        self::assertCount(1, $this->catalog->versions('terms'));
    }

    public function testTheLicenceIsTheOneOfTheVersionAcceptedAtTheMoment(): void
    {
        $this->terms->publish('contributions', ['en' => 'v1'], licence: 'CC-BY-4.0');
        $this->terms->publish('terms', ['en' => 'terms']);
        $this->terms->accept('m', 'terms', 1, AcceptanceChannel::Form);

        $before = new DateTimeImmutable('2026-05-01 09:59:59');

        self::assertNull($this->licences->at('m', $this->clock->now()));

        $this->terms->accept('m', 'contributions', 1, AcceptanceChannel::Signup);
        $first = $this->clock->now();

        $this->clock->modify('+1 day');
        $this->terms->publish('contributions', ['en' => 'v2'], licence: 'CC-BY-SA-4.0');
        $this->clock->modify('+1 day');
        $this->terms->accept('m', 'contributions', 2, AcceptanceChannel::Form);
        $second = $this->clock->now();

        self::assertNull($this->licences->at('m', $before));
        self::assertEquals(new ContributionLicence('CC-BY-4.0', 'contributions', 1, $first), $this->licences->at('m', $first));
        self::assertEquals(new ContributionLicence('CC-BY-4.0', 'contributions', 1, $first), $this->licences->at('m', $second->modify('-1 second')));
        self::assertEquals(new ContributionLicence('CC-BY-SA-4.0', 'contributions', 2, $second), $this->licences->at('m', $second));
        self::assertNull($this->licences->at('other', $second));
    }

    public function testAVersionAcceptedBeforeItTakesEffectLicensesNothingUntilThen(): void
    {
        $this->terms->publish('contributions', ['en' => 'v1'], licence: 'CC-BY-4.0');
        $this->terms->accept('m', 'contributions', 1, AcceptanceChannel::Form);
        $this->terms->publish('contributions', ['en' => 'v2'], effectiveAt: new DateTimeImmutable('2026-06-01'), licence: 'CC0-1.0');
        $this->terms->accept('m', 'contributions', 2, AcceptanceChannel::Form);

        self::assertSame('CC-BY-4.0', $this->licences->at('m', new DateTimeImmutable('2026-05-31 23:59:59'))?->licence);
        self::assertSame('CC0-1.0', $this->licences->at('m', new DateTimeImmutable('2026-06-01'))?->licence);
    }

    public function testTheSameVersionAcceptedAgainIsDatedByTheLatestAcceptance(): void
    {
        $this->terms->publish('contributions', ['en' => 'v1'], licence: 'CC-BY-4.0');
        $this->terms->accept('m', 'contributions', 1, AcceptanceChannel::Signup);

        $this->clock->modify('+1 hour');
        $this->terms->accept('m', 'contributions', 1, AcceptanceChannel::Form);

        self::assertEquals($this->clock->now(), $this->licences->at('m', $this->clock->now())?->acceptedAt);
    }

    public function testAnEarlierVersionAcceptedLaterNeverReplacesAHigherOne(): void
    {
        $at = new DateTimeImmutable('2026-05-01 10:00');
        $this->versions->add(new PolicyVersion('contributions', 1, ['en' => 'v1'], TextFormat::Text, $at, $at, true, 'CC-BY-4.0'));
        $this->versions->add(new PolicyVersion('contributions', 2, ['en' => 'v2'], TextFormat::Text, $at, $at, true, 'CC0-1.0'));

        $this->acceptances->append(new Acceptance('m', 'contributions', 2, $at, AcceptanceChannel::Import));
        $this->acceptances->append(new Acceptance('m', 'contributions', 1, $at->modify('+1 hour'), AcceptanceChannel::Import));

        self::assertEquals(new ContributionLicence('CC0-1.0', 'contributions', 2, $at), $this->licences->at('m', $at->modify('+2 hours')));
    }

    public function testAVersionWithoutLicenceOrOfAnotherPolicyLicensesNothing(): void
    {
        $at = new DateTimeImmutable('2026-05-01 10:00');
        $this->versions->add(new PolicyVersion('contributions', 1, ['en' => 'v1'], TextFormat::Text, $at, $at, true));
        $this->versions->add(new PolicyVersion('terms', 1, ['en' => 'v1'], TextFormat::Text, $at, $at, true, 'MIT'));

        $this->acceptances->append(new Acceptance('m', 'contributions', 1, $at, AcceptanceChannel::Import));
        $this->acceptances->append(new Acceptance('m', 'terms', 1, $at, AcceptanceChannel::Import));
        $this->acceptances->append(new Acceptance('m', 'contributions', 7, $at, AcceptanceChannel::Import));

        self::assertNull($this->licences->at('m', $at));
    }

    public function testNothingIsLicensedWithoutALicensingPolicy(): void
    {
        $policies = new Policies(new Policy('terms'));
        $at = new DateTimeImmutable('2026-05-01 10:00');
        $this->versions->add(new PolicyVersion('contributions', 1, ['en' => 'v1'], TextFormat::Text, $at, $at, true, 'MIT'));
        $this->acceptances->append(new Acceptance('m', 'contributions', 1, $at, AcceptanceChannel::Import));

        self::assertNull(new ContributionLicences($policies, $this->catalog, $this->acceptances)->at('m', $at));
    }
}
