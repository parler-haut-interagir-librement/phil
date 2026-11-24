<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Dmarc;

use Phil\MailAuthCheck\Alignment;
use Phil\MailAuthCheck\Dmarc\DmarcParser;
use Phil\MailAuthCheck\Dmarc\DmarcRecord;
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DmarcParser::class)]
#[CoversClass(DmarcRecord::class)]
#[CoversClass(DmarcPolicy::class)]
#[CoversClass(Alignment::class)]
final class DmarcParserTest extends TestCase
{
    public function testADmarcRecordIsToldFromOtherTxtRecords(): void
    {
        self::assertTrue(DmarcParser::isDmarc('v=DMARC1'));
        self::assertTrue(DmarcParser::isDmarc('v=DMARC1; p=none'));
        self::assertTrue(DmarcParser::isDmarc("v \t= DMARC1 ;p=none"));
        self::assertFalse(DmarcParser::isDmarc('v=dmarc1; p=none'));
        self::assertFalse(DmarcParser::isDmarc('v=DMARC10; p=none'));
        self::assertFalse(DmarcParser::isDmarc(' v=DMARC1'));
        self::assertFalse(DmarcParser::isDmarc("v=DMARC1\n"));
        self::assertFalse(DmarcParser::isDmarc('p=none; v=DMARC1'));
    }

    public function testEveryTagIsRead(): void
    {
        $record = new DmarcParser()->parse('v=DMARC1; p=Reject; sp=quarantine; np=none; aspf=s; adkim=S; t=y; psd=n; pct=50; rua=mailto:a@example.org, mailto:b@example.org');

        self::assertSame(DmarcPolicy::Reject, $record->policy);
        self::assertSame(DmarcPolicy::Quarantine, $record->subdomainPolicy);
        self::assertSame(DmarcPolicy::None, $record->nonExistentPolicy);
        self::assertSame(Alignment::Strict, $record->spfAlignment);
        self::assertSame(Alignment::Strict, $record->dkimAlignment);
        self::assertTrue($record->testing);
        self::assertFalse($record->publicSuffix);
        self::assertSame(['mailto:a@example.org', 'mailto:b@example.org'], $record->aggregateReports);
    }

    public function testTheDefaults(): void
    {
        $record = new DmarcParser()->parse('v=DMARC1');

        self::assertNull($record->policy);
        self::assertNull($record->subdomainPolicy);
        self::assertNull($record->nonExistentPolicy);
        self::assertSame(Alignment::Relaxed, $record->spfAlignment);
        self::assertSame(Alignment::Relaxed, $record->dkimAlignment);
        self::assertFalse($record->testing);
        self::assertNull($record->publicSuffix);
        self::assertSame([], $record->aggregateReports);
    }

    public function testTheFlags(): void
    {
        $parser = new DmarcParser();

        self::assertFalse($parser->parse('v=DMARC1; t=n')->testing);
        self::assertTrue($parser->parse('v=DMARC1; psd=Y')->publicSuffix);
        self::assertNull($parser->parse('v=DMARC1; psd=u')->publicSuffix);
        self::assertNull($parser->parse('v=DMARC1; psd=')->publicSuffix);
        self::assertSame(Alignment::Relaxed, $parser->parse('v=DMARC1; aspf=r')->spfAlignment);
        self::assertSame([], $parser->parse('v=DMARC1; rua=')->aggregateReports);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRecords(): iterable
    {
        yield 'not dmarc' => ['v=spf1 -all', 'the record does not start with "v=DMARC1".'];
        yield 'a bad policy' => ['v=DMARC1; p=block', 'the tag "p" takes none, quarantine or reject.'];
        yield 'a bad subdomain policy' => ['v=DMARC1; p=none; sp=', 'the tag "sp" takes none, quarantine or reject.'];
        yield 'a bad np' => ['v=DMARC1; np=x', 'the tag "np" takes none, quarantine or reject.'];
        yield 'a bad aspf' => ['v=DMARC1; aspf=x', 'the tag "aspf" takes r or s.'];
        yield 'a bad adkim' => ['v=DMARC1; adkim=', 'the tag "adkim" takes r or s.'];
        yield 'a bad t' => ['v=DMARC1; t=1', 'the tag "t" takes y or n.'];
        yield 'a bad psd' => ['v=DMARC1; psd=x', 'the tag "psd" takes y or n or u.'];
        yield 'a broken list' => ['v=DMARC1; p', 'The DMARC record has a part that is not "tag=value".'];
    }

    #[DataProvider('invalidRecords')]
    public function testAnInvalidRecordIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DmarcParser()->parse($text);
    }

    public function testThePolicyForTheDomainAndItsSubdomains(): void
    {
        $record = new DmarcRecord(DmarcPolicy::Reject, DmarcPolicy::None);

        self::assertSame(DmarcPolicy::Reject, $record->policyFor(false));
        self::assertSame(DmarcPolicy::None, $record->policyFor(true));
        self::assertSame(DmarcPolicy::Quarantine, new DmarcRecord(DmarcPolicy::Quarantine)->policyFor(true));
        self::assertSame(DmarcPolicy::None, new DmarcRecord(null)->policyFor(false));
        self::assertSame(DmarcPolicy::None, new DmarcRecord(null)->policyFor(true));
        self::assertSame(DmarcPolicy::Reject, new DmarcRecord(null, DmarcPolicy::Reject)->policyFor(true));
    }

    public function testPoliciesCompareByStrictness(): void
    {
        $matrix = [];

        foreach (DmarcPolicy::cases() as $policy) {
            foreach (DmarcPolicy::cases() as $other) {
                $matrix[] = $policy->isAtLeast($other);
            }
        }

        self::assertSame([true, false, false, true, true, false, true, true, true], $matrix);
    }

    public function testARecordIsNotTestedByDefault(): void
    {
        self::assertFalse(new DmarcRecord(DmarcPolicy::Reject)->testing);
    }
}
