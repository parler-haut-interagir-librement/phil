<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Domain;

use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\ProtectionSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProtectionSyntax::class)]
final class ProtectionSyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{Protection, mixed, mixed}>
     */
    public static function values(): iterable
    {
        yield 'a keyword' => [Protection::FrameOptions, 'DENY', 'DENY'];
        yield 'transport' => [Protection::StrictTransportSecurity, 'max-age=60; includeSubDomains; preload', 'max-age=60; includeSubDomains; preload'];
        yield 'transport without option' => [Protection::StrictTransportSecurity, 'max-age=0', 'max-age=0'];
        yield 'transport with one option' => [Protection::StrictTransportSecurity, 'max-age=1; preload', 'max-age=1; preload'];
        yield 'the longest age' => [Protection::StrictTransportSecurity, 'max-age=1234567890', 'max-age=1234567890'];
        yield 'permissions' => [Protection::PermissionsPolicy, ['camera' => ['self']], ['camera' => ['self']]];
        yield 'two permissions' => [Protection::PermissionsPolicy, ['camera' => ['self'], 'usb' => []], ['camera' => ['self'], 'usb' => []]];
        yield 'the first referrer policy' => [Protection::ReferrerPolicy, 'no-referrer', 'no-referrer'];
        yield 'the first cross-domain policy' => [Protection::PermittedCrossDomainPolicies, 'none', 'none'];
        yield 'no permission' => [Protection::PermissionsPolicy, [], []];
        yield 'site data' => [Protection::ClearSiteData, ['cookies', 'cookies', 'storage'], ['cookies', 'storage']];
        yield 'integrity' => [Protection::IntegrityPolicy, ['script'], ['script']];
        yield 'integrity report-only' => [Protection::IntegrityPolicyReportOnly, ['style'], ['style']];
        yield 'cookies' => [Protection::SignedCookies, ['theme', 'theme', 'a_b-c'], ['theme', 'a_b-c']];
        yield 'hosts in lower case' => [Protection::ExternalRedirects, ['PAY.example.com', '.example.net', 'pay.example.com'], ['pay.example.com', '.example.net']];
        yield 'a redirect turned on' => [Protection::HttpsRedirect, true, true];
    }

    #[DataProvider('values')]
    public function testAValueIsNormalized(Protection $protection, mixed $value, mixed $expected): void
    {
        self::assertSame($expected, ProtectionSyntax::normalize($protection, $value));
    }

    /**
     * @return iterable<string, array{Protection, mixed, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an unknown keyword' => [Protection::FrameOptions, 'ALLOW', '"ALLOW" is not a value of X-Frame-Options: DENY, SAMEORIGIN.'];
        yield 'not a keyword' => [Protection::FrameOptions, 3, '"int" is not a value of X-Frame-Options: DENY, SAMEORIGIN.'];
        yield 'transport, no age' => [Protection::StrictTransportSecurity, 'preload', 'The value of Strict-Transport-Security is'];
        yield 'transport, prefix' => [Protection::StrictTransportSecurity, ' max-age=1', 'The value of Strict-Transport-Security is'];
        yield 'transport, tail' => [Protection::StrictTransportSecurity, "max-age=1\n", 'The value of Strict-Transport-Security is'];
        yield 'transport, too long' => [Protection::StrictTransportSecurity, 'max-age=12345678901', 'The value of Strict-Transport-Security is'];
        yield 'transport, not text' => [Protection::StrictTransportSecurity, 1, 'The value of Strict-Transport-Security is'];
        yield 'permissions, a list' => [Protection::PermissionsPolicy, ['camera'], 'The value of Permissions-Policy is a map'];
        yield 'permissions, text' => [Protection::PermissionsPolicy, 'camera', 'The value of Permissions-Policy is a map'];
        yield 'permissions, not a list' => [Protection::PermissionsPolicy, ['camera' => 'self'], 'The value of Permissions-Policy is a list of strings.'];
        yield 'permissions, bad origin' => [Protection::PermissionsPolicy, ['camera' => ['nope']], 'is not an origin'];
        yield 'site data, unknown' => [Protection::ClearSiteData, ['session'], '"session" is not a value of Clear-Site-Data:'];
        yield 'site data, text' => [Protection::ClearSiteData, 'cookies', 'The value of Clear-Site-Data is a list of strings.'];
        yield 'site data, a map' => [Protection::ClearSiteData, ['a' => 'cookies'], 'The value of Clear-Site-Data is a list of strings.'];
        yield 'site data, not text' => [Protection::ClearSiteData, [3], 'The value of Clear-Site-Data is a list of strings.'];
        yield 'integrity, unknown' => [Protection::IntegrityPolicy, ['font'], '"font" is not a value of Integrity-Policy: script, style.'];
        yield 'integrity report-only, unknown' => [Protection::IntegrityPolicyReportOnly, ['font'], '"font" is not a value of Integrity-Policy-Report-Only'];
        yield 'a cookie with a space' => [Protection::SignedCookies, ['a b'], '"a b" is not a value of signed-cookies.'];
        yield 'an empty cookie' => [Protection::SignedCookies, [''], 'is not a value of signed-cookies.'];
        yield 'a cookie, newline' => [Protection::SignedCookies, ["a\n"], 'is not a value of signed-cookies.'];
        yield 'a host with a scheme' => [Protection::ExternalRedirects, ['https://a.example.com'], 'is not a value of external-redirects.'];
        yield 'a host, newline' => [Protection::ExternalRedirects, ["a.example.com\n"], 'is not a value of external-redirects.'];
        yield 'a redirect turned off' => [Protection::HttpsRedirect, false, 'The value of "https-redirect" is true'];
    }

    #[DataProvider('invalid')]
    public function testAnInvalidValueIsRefused(Protection $protection, mixed $value, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        ProtectionSyntax::normalize($protection, $value);
    }

    public function testAListJoinsWithoutRepeats(): void
    {
        self::assertSame(['a', 'b', 'c'], ProtectionSyntax::merge(['a', 'b'], ['b', 'c']));
        self::assertSame(['a'], ProtectionSyntax::merge(['a'], []));
    }

    public function testAMapJoinsByKey(): void
    {
        self::assertSame(
            ['camera' => ['self', 'https://a.example.com'], 'usb' => []],
            ProtectionSyntax::merge(['camera' => ['self']], ['camera' => ['self', 'https://a.example.com'], 'usb' => []]),
        );
        self::assertSame(['camera' => []], ProtectionSyntax::merge(['camera' => []], []));
        self::assertSame(['a', 'b'], ProtectionSyntax::merge(['a', 'b'], []));
        self::assertSame(['a', 'b', 'c'], ProtectionSyntax::merge(['a', 'b'], ['c']));
        self::assertSame(['camera' => ['self', 'https://a.example.com']], ProtectionSyntax::merge(['camera' => ['self']], ['camera' => ['https://a.example.com']]));
    }

    public function testACollection(): void
    {
        self::assertSame(['cookies'], ProtectionSyntax::collection(Protection::ClearSiteData, ['cookies']));
    }

    public function testOneValueIsNoCollection(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('A page cannot add to X-Frame-Options, which holds one value: replace it.');

        ProtectionSyntax::collection(Protection::FrameOptions, 'DENY');
    }

    public function testTheItemsAndTheGroupsOfAValue(): void
    {
        self::assertSame(['a', 'b'], ProtectionSyntax::items(['a', 'b']));
        self::assertSame([], ProtectionSyntax::items('DENY'));
        self::assertSame([], ProtectionSyntax::items(null));
        self::assertSame(['camera' => ['self']], ProtectionSyntax::groups(['camera' => ['self']]));
        self::assertSame([], ProtectionSyntax::groups(['a']));
        self::assertSame([], ProtectionSyntax::groups(true));
    }

    /**
     * @return iterable<string, array{Protection, string|true|list<string>|array<string, list<string>>, string|null, string}>
     */
    public static function headers(): iterable
    {
        yield 'a keyword' => [Protection::FrameOptions, 'DENY', null, 'DENY'];
        yield 'a keyword that does not report' => [Protection::FrameOptions, 'DENY', 'csp', 'DENY'];
        yield 'report-only' => [Protection::OpenerPolicyReportOnly, 'same-origin', 'csp', 'same-origin; report-to="csp"'];
        yield 'report-only without an endpoint' => [Protection::EmbedderPolicyReportOnly, 'credentialless', null, 'credentialless'];
        yield 'permissions' => [Protection::PermissionsPolicy, ['camera' => []], 'csp', 'camera=()'];
        yield 'site data' => [Protection::ClearSiteData, ['cookies', 'storage'], 'csp', '"cookies", "storage"'];
        yield 'integrity' => [Protection::IntegrityPolicy, ['script', 'style'], 'csp', 'blocked-destinations=(script style), sources=(inline), endpoints=(csp)'];
        yield 'integrity without an endpoint' => [Protection::IntegrityPolicyReportOnly, ['script'], null, 'blocked-destinations=(script), sources=(inline)'];
        yield 'nothing' => [Protection::ClearSiteData, [], 'csp', ''];
        yield 'a flag' => [Protection::HttpsRedirect, true, 'csp', ''];
    }

    /**
     * @param string|true|list<string>|array<string, list<string>> $value
     */
    #[DataProvider('headers')]
    public function testAHeaderIsWritten(Protection $protection, string|bool|array $value, ?string $group, string $expected): void
    {
        self::assertSame($expected, ProtectionSyntax::render($protection, $value, $group));
    }
}
