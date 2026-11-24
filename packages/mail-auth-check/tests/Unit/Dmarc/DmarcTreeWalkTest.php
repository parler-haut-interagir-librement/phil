<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Dmarc;

use Phil\MailAuthCheck\Dmarc\DmarcDiscovery;
use Phil\MailAuthCheck\Dmarc\DmarcTreeWalk;
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Tests\Support\CountingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DmarcTreeWalk::class)]
#[CoversClass(DmarcDiscovery::class)]
final class DmarcTreeWalkTest extends TestCase
{
    public function testTheWalkGoesUpOneLabelAtATime(): void
    {
        self::assertSame(['a.b.example.org', 'b.example.org', 'example.org', 'org'], DmarcTreeWalk::names('a.b.example.org'));
        self::assertSame(['example.org', 'org'], DmarcTreeWalk::names('example.org'));
        self::assertSame(['org'], DmarcTreeWalk::names('org'));
    }

    public function testALongDomainJumpsToItsSevenRightmostLabels(): void
    {
        self::assertSame(
            ['a.b.c.d.e.f.g.h.example', 'c.d.e.f.g.h.example', 'd.e.f.g.h.example', 'e.f.g.h.example', 'f.g.h.example', 'g.h.example', 'h.example', 'example'],
            DmarcTreeWalk::names('a.b.c.d.e.f.g.h.example'),
        );
        self::assertSame(
            ['b.c.d.e.f.g.h.example', 'c.d.e.f.g.h.example', 'd.e.f.g.h.example', 'e.f.g.h.example', 'f.g.h.example', 'g.h.example', 'h.example', 'example'],
            DmarcTreeWalk::names('b.c.d.e.f.g.h.example'),
        );
        self::assertCount(7, DmarcTreeWalk::names('c.d.e.f.g.h.example'));
    }

    public function testTheRecordOfTheDomainItselfComesFirst(): void
    {
        $discovery = new DmarcTreeWalk()->policy('example.org', new StaticTxtResolver([
            '_dmarc.example.org' => ['hello', 'v=DMARC1; p=reject'],
        ]));

        self::assertSame('example.org', $discovery->domain);
        self::assertSame('example.org', $discovery->foundAt);
        self::assertSame(DmarcPolicy::Reject, $discovery->record?->policy);
        self::assertFalse($discovery->isInherited());
        self::assertFalse($discovery->lookupFailed);
    }

    public function testASubdomainInheritsTheRecordOfAParent(): void
    {
        $resolver = new CountingResolver(['_dmarc.example.org' => ['v=DMARC1; p=quarantine']]);
        $discovery = new DmarcTreeWalk()->policy('mail.news.example.org', $resolver);

        self::assertSame('example.org', $discovery->foundAt);
        self::assertTrue($discovery->isInherited());
        self::assertSame(['_dmarc.mail.news.example.org', '_dmarc.news.example.org', '_dmarc.example.org'], $resolver->asked);
    }

    public function testInvalidAndDuplicateRecordsAreSkipped(): void
    {
        $discovery = new DmarcTreeWalk()->policy('a.b.example.org', new StaticTxtResolver([
            '_dmarc.a.b.example.org' => ['v=DMARC1; p=block'],
            '_dmarc.b.example.org' => ['v=DMARC1; p=none', 'v=DMARC1; p=reject'],
            '_dmarc.example.org' => ['v=DMARC1; p=reject'],
        ]));

        self::assertSame('example.org', $discovery->foundAt);
        self::assertSame([
            'a.b.example.org' => 'the tag "p" takes none, quarantine or reject.',
            'b.example.org' => 'it holds more than one DMARC record.',
        ], $discovery->ignored);
    }

    public function testNoRecordAnywhere(): void
    {
        $discovery = new DmarcTreeWalk()->policy('example.org', new StaticTxtResolver(['_dmarc.org' => ['v=DMARC1; p=x']]));

        self::assertNull($discovery->record);
        self::assertNull($discovery->foundAt);
        self::assertFalse($discovery->isInherited());
        self::assertSame(['org' => 'the tag "p" takes none, quarantine or reject.'], $discovery->ignored);
    }

    public function testAFailedLookupEndsTheWalk(): void
    {
        $discovery = new DmarcTreeWalk()->policy('a.example.org', new StaticTxtResolver([
            '_dmarc.a.example.org' => ['v=DMARC1; p=x'],
            '_dmarc.org' => ['v=DMARC1; p=reject'],
        ], ['_dmarc.example.org']));

        self::assertTrue($discovery->lookupFailed);
        self::assertNull($discovery->record);
        self::assertSame(['a.example.org'], array_keys($discovery->ignored));
    }

    public function testTheOrganisationalDomainIsTheRecordOfFewestLabels(): void
    {
        $walk = new DmarcTreeWalk();
        $resolver = new StaticTxtResolver([
            '_dmarc.example.org' => ['v=DMARC1; p=reject'],
            '_dmarc.news.example.org' => ['v=DMARC1; p=none'],
            '_dmarc.bad.example.org' => ['v=DMARC1; p=x'],
        ]);

        self::assertSame('example.org', $walk->organisationalDomain('mail.news.example.org', $resolver));
        self::assertSame('example.org', $walk->organisationalDomain('example.org', $resolver));
        self::assertSame('example.org', $walk->organisationalDomain('a.bad.example.org', $resolver));
        self::assertSame('other.example', $walk->organisationalDomain('other.example', $resolver));
    }

    public function testAnOrganisationalDomainSaysSo(): void
    {
        $resolver = new StaticTxtResolver([
            '_dmarc.b.example.org' => ['v=DMARC1; p=reject; psd=n'],
            '_dmarc.example.org' => ['v=DMARC1; p=none'],
        ]);

        self::assertSame('b.example.org', new DmarcTreeWalk()->organisationalDomain('a.b.example.org', $resolver));
    }

    public function testAPublicSuffixGivesTheDomainOneLabelBelowIt(): void
    {
        $walk = new DmarcTreeWalk();
        $resolver = new StaticTxtResolver([
            '_dmarc.example' => ['v=DMARC1; p=reject; psd=y'],
            '_dmarc.org' => ['v=DMARC1; p=reject; psd=u'],
        ]);

        self::assertSame('shop.example', $walk->organisationalDomain('a.b.shop.example', $resolver));
        self::assertSame('example', $walk->organisationalDomain('example', $resolver));
        self::assertSame('org', $walk->organisationalDomain('mail.example.org', $resolver));
    }

    public function testTheOrganisationalDomainPropagatesAFailedLookup(): void
    {
        $this->expectException(DnsLookupFailed::class);

        new DmarcTreeWalk()->organisationalDomain('a.example.org', new StaticTxtResolver([], ['_dmarc.example.org']));
    }
}
