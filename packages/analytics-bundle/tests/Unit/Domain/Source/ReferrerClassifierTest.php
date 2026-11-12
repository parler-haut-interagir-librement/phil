<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Source;

use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReferrerClassifier::class)]
#[CoversClass(ReferralKind::class)]
final class ReferrerClassifierTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, ReferralKind}>
     */
    public static function referrers(): iterable
    {
        yield 'none' => [null, ReferralKind::Direct];
        yield 'empty' => ['', ReferralKind::Direct];
        yield 'not a web origin' => ['android-app://com.example.mail', ReferralKind::Direct];
        yield 'the site itself' => ['https://example.com/events?x=1', ReferralKind::Internal];
        yield 'another origin of the site' => ['https://shop.example.com/', ReferralKind::Internal];
        yield 'an origin the site owns elsewhere' => ['https://login.example.net/callback?code=abc', ReferralKind::Internal];
        yield 'search' => ['https://www.google.co.uk/search?q=x', ReferralKind::Search];
        yield 'social' => ['https://m.facebook.com/', ReferralKind::Social];
        yield 'spam' => ['https://free-traffic.test/', ReferralKind::Spam];
        yield 'another site' => ['https://blog.other.test/post', ReferralKind::Website];
        yield 'an undeclared subdomain of the site is another site' => ['https://blog.example.com/', ReferralKind::Website];
    }

    #[DataProvider('referrers')]
    public function testAReferrerIsClassified(?string $referer, ReferralKind $expected): void
    {
        $classifier = new ReferrerClassifier(
            HostList::of(['google.{}', 'duckduckgo.com']),
            HostList::of(['facebook.com']),
            HostList::of(['free-traffic.test']),
        );
        $own = [
            Origin::fromString('https://example.com'),
            Origin::fromString('https://shop.example.com'),
            Origin::fromString('https://login.example.net'),
        ];

        self::assertSame($expected, $classifier->classify($referer, $own));
    }

    /**
     * A visit is a page view whose source is not the site itself; spam is not counted at all.
     */
    public function testOnlyASourceOutsideTheSiteIsAVisit(): void
    {
        self::assertTrue(ReferralKind::Direct->isVisit());
        self::assertTrue(ReferralKind::Search->isVisit());
        self::assertTrue(ReferralKind::Social->isVisit());
        self::assertTrue(ReferralKind::Website->isVisit());
        self::assertFalse(ReferralKind::Internal->isVisit());
        self::assertFalse(ReferralKind::Spam->isVisit());
    }
}
