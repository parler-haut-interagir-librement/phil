<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Application\ResolvedHeaders;
use Phil\BrowserSecurityBundle\Application\SecurityHeaders;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityHeaders::class)]
#[CoversClass(ResolvedHeaders::class)]
final class SecurityHeadersTest extends TestCase
{
    public function testTheHeadersOfTheConfiguration(): void
    {
        self::assertSame([], new SecurityHeaders()->headers);
        self::assertSame([
            'Permissions-Policy' => 'camera=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
        ], new SecurityHeaders(['Permissions-Policy' => ['camera' => []], 'Cross-Origin-Opener-Policy' => 'same-origin', 'Referrer-Policy' => 'no-referrer'])->headers);
    }

    public function testAHeaderWithNothingToSayIsLeftOut(): void
    {
        self::assertSame([], new SecurityHeaders(['Permissions-Policy' => []])->headers);
    }

    public function testAnInvalidValueIsRefused(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('"same-site" is not a value of Cross-Origin-Opener-Policy');

        new SecurityHeaders(['Cross-Origin-Opener-Policy' => 'same-site']);
    }

    public function testAnUnknownHeaderIsRefused(): void
    {
        $this->expectException(InvalidPolicy::class);

        new SecurityHeaders(['X-XSS-Protection' => '0']);
    }

    public function testAnInvalidFrameOptionOfAPathIsRefused(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('"ALLOW" is not a value of X-Frame-Options');

        new SecurityHeaders([], ['/embed' => 'ALLOW']);
    }

    public function testAResponseWithoutOverride(): void
    {
        $resolved = new SecurityHeaders(['Referrer-Policy' => 'origin', 'X-Frame-Options' => 'DENY'])->resolve('/page');

        self::assertSame(['Referrer-Policy' => 'origin', 'X-Frame-Options' => 'DENY'], $resolved->defaults);
        self::assertSame([], $resolved->forced);
        self::assertFalse($resolved->reports);
    }

    public function testTheLongestPrefixOfAPathWins(): void
    {
        $headers = new SecurityHeaders(['X-Frame-Options' => 'DENY'], ['/embed' => null, '/embed/strict' => 'SAMEORIGIN']);

        self::assertArrayNotHasKey('X-Frame-Options', $headers->resolve('/embed')->defaults);
        self::assertArrayNotHasKey('X-Frame-Options', $headers->resolve('/embed/map')->defaults);
        self::assertSame('SAMEORIGIN', $headers->resolve('/embed/strict/one')->defaults['X-Frame-Options']);
        self::assertSame('DENY', $headers->resolve('/embedded')->defaults['X-Frame-Options']);
        self::assertSame('DENY', $headers->resolve('/x/embed')->defaults['X-Frame-Options']);
    }

    public function testAPrefixWithItsSlash(): void
    {
        self::assertSame('SAMEORIGIN', new SecurityHeaders([], ['/embed/' => 'SAMEORIGIN'])->resolve('/embed/map')->defaults['X-Frame-Options']);
    }

    public function testAFrameOptionOnlyOnAPath(): void
    {
        $headers = new SecurityHeaders([], ['/embed' => 'DENY']);

        self::assertArrayNotHasKey('X-Frame-Options', $headers->resolve('/page')->defaults);
    }

    public function testAnOverrideTakesTheHeaderFromTheDefaults(): void
    {
        $headers = new SecurityHeaders(['Referrer-Policy' => 'origin', 'X-Frame-Options' => 'DENY']);
        $resolved = $headers->resolve('/page', [new ProtectionOverride(replace: ['Referrer-Policy' => 'no-referrer'])]);

        self::assertSame(['X-Frame-Options' => 'DENY'], $resolved->defaults);
        self::assertSame(['Referrer-Policy' => 'no-referrer'], $resolved->forced);
    }

    public function testARemovedHeaderIsNull(): void
    {
        $resolved = new SecurityHeaders(['Referrer-Policy' => 'origin'])->resolve('/page', [new ProtectionOverride(remove: ['Referrer-Policy'])]);

        self::assertSame([], $resolved->defaults);
        self::assertSame(['Referrer-Policy' => null], $resolved->forced);
    }

    public function testAnEmptiedCollectionIsRemoved(): void
    {
        $resolved = new SecurityHeaders()->resolve('/page', [new ProtectionOverride(replace: ['Clear-Site-Data' => []])]);

        self::assertSame(['Clear-Site-Data' => null], $resolved->forced);
    }

    public function testOverridesApplyInOrderFromTheConfiguredValue(): void
    {
        $resolved = new SecurityHeaders(['Permissions-Policy' => ['camera' => [], 'geolocation' => []]])->resolve('/page', [
            new ProtectionOverride(add: ['Permissions-Policy' => ['camera' => ['self']]]),
            new ProtectionOverride(add: ['Permissions-Policy' => ['usb' => []]]),
        ]);

        self::assertSame(['Permissions-Policy' => 'camera=(self), geolocation=(), usb=()'], $resolved->forced);
    }

    public function testTheFrameOptionOfThePathIsWhatAnOverrideStartsFrom(): void
    {
        $headers = new SecurityHeaders(['X-Frame-Options' => 'DENY'], ['/embed' => null]);
        $untouched = new ProtectionOverride(remove: ['Referrer-Policy']);

        self::assertArrayNotHasKey('X-Frame-Options', $headers->resolve('/embed/a', [$untouched])->defaults);
        self::assertSame(['X-Frame-Options' => 'SAMEORIGIN'], $headers->resolve('/embed/a', [new ProtectionOverride(replace: ['X-Frame-Options' => 'SAMEORIGIN'])])->forced);
    }

    public function testAProtectionThatIsNotAHeaderIsNoHeader(): void
    {
        $resolved = new SecurityHeaders()->resolve('/page', [new ProtectionOverride(add: ['external-redirects' => ['a.example.com']])]);

        self::assertSame([], $resolved->forced);
    }

    public function testStrictTransportSecurityGoesOverHttpsOnly(): void
    {
        $headers = new SecurityHeaders(['Strict-Transport-Security' => 'max-age=60', 'Referrer-Policy' => 'origin']);
        $override = new ProtectionOverride(replace: ['Strict-Transport-Security' => 'max-age=0']);

        self::assertSame(['Strict-Transport-Security' => 'max-age=60', 'Referrer-Policy' => 'origin'], $headers->resolve('/', [], true)->defaults);
        self::assertSame(['Referrer-Policy' => 'origin'], $headers->resolve('/', [], false)->defaults);
        self::assertArrayHasKey('Strict-Transport-Security', $headers->resolve('/')->defaults);
        self::assertSame(['Strict-Transport-Security' => 'max-age=0'], $headers->resolve('/', [$override], true)->forced);
        self::assertSame([], $headers->resolve('/', [$override], false)->forced);
    }

    public function testAReportingHeaderNamesItsEndpoint(): void
    {
        self::assertSame(['Cross-Origin-Opener-Policy-Report-Only' => 'same-origin; report-to="csp"'], new SecurityHeaders(['Cross-Origin-Opener-Policy-Report-Only' => 'same-origin'], [], 'csp')->headers);
    }

    public function testWhetherTheEndpointIsNeeded(): void
    {
        $values = ['Cross-Origin-Opener-Policy-Report-Only' => 'same-origin'];

        self::assertTrue(new SecurityHeaders($values, [], 'csp')->resolve('/')->reports);
        self::assertFalse(new SecurityHeaders($values)->resolve('/')->reports);
        self::assertFalse(new SecurityHeaders(['Referrer-Policy' => 'origin'], [], 'csp')->resolve('/')->reports);
        self::assertTrue(new SecurityHeaders([], [], 'csp')->resolve('/', [new ProtectionOverride(replace: ['Integrity-Policy' => ['script']])])->reports);
        self::assertSame(
            ['Integrity-Policy' => 'blocked-destinations=(script), sources=(inline), endpoints=(csp)'],
            new SecurityHeaders([], [], 'csp')->resolve('/', [new ProtectionOverride(replace: ['Integrity-Policy' => ['script']])])->forced,
        );
        self::assertFalse(new SecurityHeaders($values, [], 'csp')->resolve('/', [new ProtectionOverride(remove: ['Cross-Origin-Opener-Policy-Report-Only'])])->reports);
    }
}
