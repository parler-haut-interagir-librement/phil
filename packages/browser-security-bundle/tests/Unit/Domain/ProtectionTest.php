<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Domain;

use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Protection::class)]
final class ProtectionTest extends TestCase
{
    public function testANameIsFound(): void
    {
        self::assertSame(Protection::FrameOptions, Protection::named('X-Frame-Options'));
    }

    public function testAnUnknownNameListsTheOthers(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('"X-XSS-Protection" is not something a page can adjust: Permissions-Policy, Referrer-Policy,');

        Protection::named('X-XSS-Protection');
    }

    public function testTheKeywords(): void
    {
        self::assertSame([
            'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin', 'same-origin',
            'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url',
        ], Protection::ReferrerPolicy->keywords());
        self::assertSame(['none', 'master-only', 'by-content-type', 'by-ftp-filename', 'all', 'none-this-response'], Protection::PermittedCrossDomainPolicies->keywords());
        self::assertSame(['DENY', 'SAMEORIGIN'], Protection::FrameOptions->keywords());
        self::assertSame(['nosniff'], Protection::ContentTypeOptions->keywords());
        self::assertSame(['same-origin', 'same-origin-allow-popups', 'noopener-allow-popups', 'unsafe-none'], Protection::OpenerPolicy->keywords());
        self::assertSame(Protection::OpenerPolicy->keywords(), Protection::OpenerPolicyReportOnly->keywords());
        self::assertSame(['require-corp', 'credentialless', 'unsafe-none'], Protection::EmbedderPolicy->keywords());
        self::assertSame(Protection::EmbedderPolicy->keywords(), Protection::EmbedderPolicyReportOnly->keywords());
        self::assertSame(['same-site', 'same-origin', 'cross-origin'], Protection::ResourcePolicy->keywords());
        self::assertContains('none-this-response', Protection::PermittedCrossDomainPolicies->keywords());
        self::assertSame(['?1', '?0'], Protection::OriginAgentCluster->keywords());
        self::assertSame([], Protection::PermissionsPolicy->keywords());
    }

    public function testWhatIsAHeader(): void
    {
        self::assertTrue(Protection::FrameOptions->isHeader());
        self::assertFalse(Protection::SignedCookies->isHeader());
        self::assertFalse(Protection::ExternalRedirects->isHeader());
        self::assertFalse(Protection::HttpsRedirect->isHeader());
    }

    public function testWhatAPageCanAddTo(): void
    {
        self::assertTrue(Protection::PermissionsPolicy->isCollection());
        self::assertTrue(Protection::ClearSiteData->isCollection());
        self::assertTrue(Protection::SignedCookies->isCollection());
        self::assertFalse(Protection::FrameOptions->isCollection());
        self::assertFalse(Protection::StrictTransportSecurity->isCollection());
        self::assertFalse(Protection::HttpsRedirect->isCollection());
    }

    public function testWhatActsBeforeTheController(): void
    {
        self::assertTrue(Protection::SignedCookies->actsBeforeController());
        self::assertTrue(Protection::HttpsRedirect->actsBeforeController());
        self::assertFalse(Protection::ExternalRedirects->actsBeforeController());
    }

    public function testWhatReports(): void
    {
        self::assertSame(
            [Protection::OpenerPolicyReportOnly, Protection::EmbedderPolicyReportOnly, Protection::IntegrityPolicy, Protection::IntegrityPolicyReportOnly],
            array_values(array_filter(Protection::cases(), static fn (Protection $protection): bool => $protection->reports())),
        );
    }
}
