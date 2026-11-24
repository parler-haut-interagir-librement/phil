<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit;

use ArrayIterator;
use Override;
use Phil\MailAuthCheck\Check\DkimCheck;
use Phil\MailAuthCheck\Check\DmarcCheck;
use Phil\MailAuthCheck\Check\ReturnPathCheck;
use Phil\MailAuthCheck\Check\SpfCheck;
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\Report;
use Phil\MailAuthCheck\SenderProfile;
use Phil\MailAuthCheck\Status;
use Phil\MailAuthCheck\Tests\Support\CountingResolver;
use Phil\MailAuthCheck\Tests\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailAuthCheck::class)]
#[CoversClass(Report::class)]
#[CoversClass(Finding::class)]
#[CoversClass(Status::class)]
final class MailAuthCheckTest extends TestCase
{
    public function testACompliantProfileCanSend(): void
    {
        $resolver = new CountingResolver([
            'example.org' => ['v=spf1 include:spf.provider.example -all'],
            'spf.provider.example' => ['v=spf1 ip4:192.0.2.0/24 -all'],
            'bounce.example.org' => ['v=spf1 include:spf.provider.example -all'],
            's1._domainkey.example.org' => [Keys::dkimRsa(2048)],
            '_dmarc.example.org' => ['v=DMARC1; p=reject; rua=mailto:dmarc@example.org'],
        ]);
        $profile = new SenderProfile('example.org', 'bounce.example.org', ['s1'], ['spf.provider.example'], DmarcPolicy::Reject);
        $report = new MailAuthCheck($resolver)->check($profile);

        self::assertTrue($report->canSend());
        self::assertSame($profile, $report->profile);
        self::assertSame([], $report->failures());
        self::assertSame([], $report->warnings());
        self::assertSame(['spf', 'dkim', 'dmarc', 'return-path'], array_values(array_unique(array_map(static fn (Finding $f): string => $f->check, $report->findings))));
        self::assertCount(3, $report->forCheck('dmarc'));
        self::assertTrue(array_is_list($report->forCheck('dmarc')));
        self::assertSame([], $report->forCheck('other'));
        // Each name is asked once, though SPF, DMARC and the return path share some.
        self::assertSame($resolver->asked, array_values(array_unique($resolver->asked)));
    }

    public function testAProfileWithAFailureCannotSend(): void
    {
        $report = new MailAuthCheck(new CountingResolver([
            'example.org' => ['v=spf1 ?all'],
        ]))->check(new SenderProfile('example.org'));

        self::assertFalse($report->canSend());
        self::assertSame(['dmarc.missing'], array_map(static fn (Finding $f): string => $f->code, $report->failures()));
        self::assertSame(['spf.all-neutral', 'dkim.no-selector'], array_map(static fn (Finding $f): string => $f->code, $report->warnings()));
    }

    public function testTheChecksAreTheOnesGivenInTheirOrder(): void
    {
        $check = new class implements Check {
            #[Override]
            public function run(SenderProfile $profile, TxtResolver $dns): array
            {
                return [Finding::warning('mta-sts', 'mta-sts.missing', $profile->sendingDomain.' '.implode(',', $dns->txt('_mta-sts.'.$profile->sendingDomain)))];
            }
        };
        $report = new MailAuthCheck(new CountingResolver(['_mta-sts.example.org' => ['v=STSv1; id=1']]), new ArrayIterator(['a' => $check]))
            ->check(new SenderProfile('example.org'));

        self::assertEquals([Finding::warning('mta-sts', 'mta-sts.missing', 'example.org v=STSv1; id=1')], $report->findings);
        self::assertTrue($report->canSend());
    }

    public function testTheDefaultChecks(): void
    {
        self::assertEquals([new SpfCheck(), new DkimCheck(), new DmarcCheck(), new ReturnPathCheck()], MailAuthCheck::defaultChecks());
    }

    public function testFindingsCarryTheirStatus(): void
    {
        self::assertSame(Status::Pass, Finding::pass('a', 'b', 'c')->status);
        self::assertSame(Status::Warning, Finding::warning('a', 'b', 'c')->status);
        self::assertSame(Status::Fail, Finding::fail('a', 'b', 'c')->status);
        self::assertSame(['a', 'b', 'c'], [Finding::fail('a', 'b', 'c')->check, Finding::fail('a', 'b', 'c')->code, Finding::fail('a', 'b', 'c')->message]);
    }
}
