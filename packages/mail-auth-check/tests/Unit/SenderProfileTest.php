<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit;

use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Exception\InvalidDomain;
use Phil\MailAuthCheck\Exception\InvalidProfile;
use Phil\MailAuthCheck\SenderProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SenderProfile::class)]
#[CoversClass(InvalidProfile::class)]
final class SenderProfileTest extends TestCase
{
    public function testTheNamesAreNormalisedAndDeduplicated(): void
    {
        $profile = new SenderProfile('Example.ORG.', 'Bounce.Example.org', ['S1', 's1', 'mail.2026'], [' _SPF.Provider.example', '_spf.provider.example', 'b.example'], DmarcPolicy::Reject);

        self::assertSame('example.org', $profile->sendingDomain);
        self::assertSame('bounce.example.org', $profile->returnPathDomain);
        self::assertSame(['s1', 'mail.2026'], $profile->dkimSelectors);
        self::assertSame(['_spf.provider.example', 'b.example'], $profile->spfIncludes);
        self::assertSame(DmarcPolicy::Reject, $profile->minimumPolicy);
    }

    public function testTheDefaults(): void
    {
        $profile = new SenderProfile('example.org');

        self::assertSame('example.org', $profile->returnPathDomain);
        self::assertSame([], $profile->dkimSelectors);
        self::assertSame([], $profile->spfIncludes);
        self::assertSame(DmarcPolicy::None, $profile->minimumPolicy);
    }

    public function testSixteenSelectorsAndIncludesAreAccepted(): void
    {
        $selectors = array_map(static fn (int $i): string => 's'.$i, range(1, 16));
        $includes = array_map(static fn (int $i): string => 'i'.$i.'.example', range(1, 16));

        $profile = new SenderProfile('example.org', null, $selectors, $includes);

        self::assertCount(16, $profile->dkimSelectors);
        self::assertCount(16, $profile->spfIncludes);
    }

    public function testSeventeenSelectorsAreRefused(): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('A sender profile expects at most 16 DKIM selectors.');

        new SenderProfile('example.org', dkimSelectors: array_map(static fn (int $i): string => 's'.$i, range(1, 17)));
    }

    public function testSeventeenIncludesAreRefused(): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('A sender profile expects at most 16 SPF includes.');

        new SenderProfile('example.org', spfIncludes: array_map(static fn (int $i): string => 'i'.$i.'.example', range(1, 17)));
    }

    public function testAnInvalidReturnPathIsRefused(): void
    {
        $this->expectException(InvalidDomain::class);

        new SenderProfile('example.org', 'bounce');
    }

    public function testAnInvalidIncludeIsRefused(): void
    {
        $this->expectException(InvalidDomain::class);
        $this->expectExceptionMessageIsOrContains('"a..b" is not a valid domain name: it is not a DNS name of letters, digits, hyphens and underscores.');

        new SenderProfile('example.org', spfIncludes: ['a..b']);
    }
}
