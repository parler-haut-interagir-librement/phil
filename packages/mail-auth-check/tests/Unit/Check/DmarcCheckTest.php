<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Check;

use Phil\MailAuthCheck\Check\DmarcCheck;
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\SenderProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DmarcCheck::class)]
final class DmarcCheckTest extends TestCase
{
    private const string REPORTS = '; rua=mailto:dmarc@example.org';

    public function testACompliantRecordPasses(): void
    {
        self::assertSame(
            [
                ['pass', 'dmarc.policy', 'The DMARC policy for example.org is "reject".'],
                ['pass', 'dmarc.return-path-aligned', 'The return path example.org aligns with example.org under relaxed SPF alignment.'],
                ['pass', 'dmarc.dkim-alignment', 'DKIM aligns when a signature uses d=example.org or another domain of its organisational domain (relaxed).'],
            ],
            $this->dmarc(new SenderProfile('example.org', minimumPolicy: DmarcPolicy::Quarantine), ['_dmarc.example.org' => ['v=DMARC1; p=reject'.self::REPORTS]]),
        );
    }

    public function testNoRecordFails(): void
    {
        self::assertSame(
            [['fail', 'dmarc.missing', 'No DMARC record applies to example.org: publish one at _dmarc.example.org, at least "v=DMARC1; p=none".']],
            $this->dmarc(new SenderProfile('example.org'), []),
        );
    }

    public function testAFailedLookupFails(): void
    {
        self::assertSame(
            [['fail', 'dmarc.lookup-failed', 'The DMARC record of example.org could not be read: a DNS lookup failed.']],
            $this->dmarc(new SenderProfile('example.org'), [], ['_dmarc.example.org']),
        );
    }

    public function testAnInvalidRecordOfTheDomainFailsAndOneOfAParentWarns(): void
    {
        self::assertSame(
            [
                ['fail', 'dmarc.invalid', 'The DMARC record at _dmarc.mail.example.org is skipped: the tag "p" takes none, quarantine or reject.'],
                ['warning', 'dmarc.parent-invalid', 'The DMARC record at _dmarc.example.org is skipped: it holds more than one DMARC record.'],
                ['fail', 'dmarc.missing', 'No DMARC record applies to mail.example.org: publish one at _dmarc.mail.example.org, at least "v=DMARC1; p=none".'],
            ],
            $this->dmarc(new SenderProfile('mail.example.org'), [
                '_dmarc.mail.example.org' => ['v=DMARC1; p=x'],
                '_dmarc.example.org' => ['v=DMARC1; p=none', 'v=DMARC1; p=reject'],
            ]),
        );
    }

    public function testASubdomainUsesTheSubdomainPolicyOfItsOrganisationalDomain(): void
    {
        self::assertSame(
            [
                ['pass', 'dmarc.inherited', 'news.example.org has no DMARC record of its own: the one of example.org applies.'],
                ['fail', 'dmarc.policy-too-weak', 'The DMARC policy for news.example.org is "none"; this sender expects at least "quarantine".'],
                ['pass', 'dmarc.return-path-aligned', 'The return path bounce.example.org aligns with news.example.org under relaxed SPF alignment.'],
                ['pass', 'dmarc.dkim-alignment', 'DKIM aligns when a signature uses d=news.example.org or another domain of its organisational domain (relaxed).'],
            ],
            $this->dmarc(
                new SenderProfile('news.example.org', 'bounce.example.org', ['s1'], minimumPolicy: DmarcPolicy::Quarantine),
                ['_dmarc.example.org' => ['v=DMARC1; p=reject; sp=none'.self::REPORTS]],
            ),
        );
    }

    public function testPolicyNoneIsAcceptedWithAWarningAndTheOtherWarnings(): void
    {
        self::assertSame(
            [
                ['warning', 'dmarc.no-policy', 'The DMARC record of example.org has no "p" tag: receivers read it as "p=none".'],
                ['warning', 'dmarc.policy-none', 'The DMARC policy for example.org is "none": it only monitors, and mail spoofing the domain is still delivered.'],
                ['warning', 'dmarc.testing', 'The DMARC record of example.org says it is being tested ("t=y"): receivers apply its policy one step weaker.'],
                ['warning', 'dmarc.no-reports', 'The DMARC record of example.org asks for no aggregate report ("rua"): nobody sees who sends as the domain.'],
                ['pass', 'dmarc.return-path-aligned', 'The return path example.org aligns with example.org under strict SPF alignment.'],
                ['pass', 'dmarc.dkim-alignment', 'DKIM aligns when a signature uses d=example.org exactly (strict).'],
            ],
            $this->dmarc(new SenderProfile('example.org'), ['_dmarc.example.org' => ['v=DMARC1; t=y; aspf=s; adkim=s']]),
        );
    }

    public function testQuarantineMeetsQuarantine(): void
    {
        self::assertSame(
            ['pass', 'dmarc.policy', 'The DMARC policy for example.org is "quarantine".'],
            $this->dmarc(new SenderProfile('example.org', minimumPolicy: DmarcPolicy::Quarantine), ['_dmarc.example.org' => ['v=DMARC1; p=quarantine'.self::REPORTS]])[0],
        );
    }

    public function testAStrictAlignmentRefusesAReturnPathOnASubdomain(): void
    {
        $records = ['_dmarc.example.org' => ['v=DMARC1; p=reject; aspf=s'.self::REPORTS]];

        self::assertSame(
            ['warning', 'dmarc.return-path-unaligned', 'The return path bounce.example.org does not align with example.org under strict SPF alignment: DMARC rests on DKIM alone.'],
            $this->dmarc(new SenderProfile('example.org', 'bounce.example.org', ['s1']), $records)[1],
        );
        self::assertSame(
            ['fail', 'dmarc.return-path-unaligned', 'The return path bounce.example.org does not align with example.org under strict SPF alignment: DMARC rests on DKIM alone. No DKIM selector is expected, so DMARC fails.'],
            $this->dmarc(new SenderProfile('example.org', 'bounce.example.org'), $records)[1],
        );
    }

    public function testAReturnPathOfAnotherOrganisationDoesNotAlign(): void
    {
        self::assertSame(
            ['warning', 'dmarc.return-path-unaligned', 'The return path bounces.provider.example does not align with example.org under relaxed SPF alignment: DMARC rests on DKIM alone.'],
            $this->dmarc(new SenderProfile('example.org', 'bounces.provider.example', ['s1']), ['_dmarc.example.org' => ['v=DMARC1; p=reject'.self::REPORTS]])[1],
        );
    }

    public function testAFailedLookupOfTheOrganisationalDomainFails(): void
    {
        self::assertSame(
            ['fail', 'dmarc.lookup-failed', 'The DMARC record of example.org could not be read: a DNS lookup failed.'],
            $this->dmarc(
                new SenderProfile('example.org', 'bounce.example.org'),
                ['_dmarc.example.org' => ['v=DMARC1; p=reject'.self::REPORTS]],
                ['_dmarc.bounce.example.org'],
            )[1],
        );
    }

    /**
     * @param array<string, list<string>> $records
     * @param list<string>                $failing
     *
     * @return list<array{string, string, string}>
     */
    private function dmarc(SenderProfile $profile, array $records, array $failing = []): array
    {
        $findings = new DmarcCheck()->run($profile, new StaticTxtResolver($records, $failing));

        foreach ($findings as $finding) {
            self::assertSame('dmarc', $finding->check);
        }

        return array_map(static fn (Finding $f): array => [$f->status->value, $f->code, $f->message], $findings);
    }
}
