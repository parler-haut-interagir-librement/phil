<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProtectionOverride::class)]
#[CoversClass(BrowserSecurity::class)]
final class ProtectionOverrideTest extends TestCase
{
    public function testReplaceThenAdd(): void
    {
        $override = new ProtectionOverride(add: ['Clear-Site-Data' => ['cache']], replace: ['Clear-Site-Data' => ['cookies']]);

        self::assertSame(['cookies', 'cache'], $override->apply(Protection::ClearSiteData, ['storage']));
    }

    public function testAddingToNothingStartsAList(): void
    {
        self::assertSame(['cache'], new ProtectionOverride(add: ['Clear-Site-Data' => ['cache']])->apply(Protection::ClearSiteData, null));
    }

    public function testRemoveComesLast(): void
    {
        self::assertNull(new ProtectionOverride(replace: ['X-Frame-Options' => 'DENY'], remove: ['X-Frame-Options'])->apply(Protection::FrameOptions, 'SAMEORIGIN'));
    }

    public function testWhatIsNotNamedStays(): void
    {
        self::assertSame('SAMEORIGIN', new ProtectionOverride(remove: ['Referrer-Policy'])->apply(Protection::FrameOptions, 'SAMEORIGIN'));
        self::assertNull(new ProtectionOverride()->policy);
    }

    public function testTheProtectionsNamed(): void
    {
        $override = new ProtectionOverride(add: ['Permissions-Policy' => []], replace: ['X-Frame-Options' => 'DENY', 'Permissions-Policy' => []], remove: ['Referrer-Policy']);

        self::assertSame([Protection::PermissionsPolicy, Protection::FrameOptions, Protection::ReferrerPolicy], $override->protections());
    }

    public function testTheContentSecurityPolicyIsOneOfTheNames(): void
    {
        $override = new ProtectionOverride(add: ['Content-Security-Policy' => ['img-src' => ['data:']]]);
        self::assertEquals(new PolicyOverride(add: ['img-src' => ['data:']]), $override->policy);
        self::assertSame([], $override->protections());

        $replaced = new ProtectionOverride(replace: ['Content-Security-Policy' => ['img-src' => ['data:']]]);
        self::assertEquals(new PolicyOverride(replace: ['img-src' => ['data:']]), $replaced->policy);
        self::assertSame([], $replaced->protections());

        $removed = new ProtectionOverride(remove: ['Content-Security-Policy']);
        self::assertEquals(new PolicyOverride(disabled: true), $removed->policy);
        self::assertSame([], $removed->protections());
    }

    public function testNothingIsAddedToAHeaderOfOneValue(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('A page cannot add to X-Frame-Options, which holds one value: replace it.');

        new ProtectionOverride(add: ['X-Frame-Options' => 'DENY']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, list<string>, string}>
     */
    public static function badNames(): iterable
    {
        yield 'add' => [['Nope' => []], [], [], '"Nope" is not something'];
        yield 'replace' => [[], ['Nope' => 'x'], [], '"Nope" is not something'];
        yield 'remove' => [[], [], ['Nope'], '"Nope" is not something'];
        yield 'a bad addition' => [['Clear-Site-Data' => ['nope']], [], [], '"nope" is not a value of Clear-Site-Data'];
        yield 'a bad replacement' => [[], ['X-Frame-Options' => 'ALLOW'], [], '"ALLOW" is not a value of X-Frame-Options'];
    }

    /**
     * @param array<string, mixed> $add
     * @param array<string, mixed> $replace
     * @param list<string>         $remove
     */
    #[DataProvider('badNames')]
    public function testAnUnknownNameOrValueIsRefused(array $add, array $replace, array $remove, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ProtectionOverride($add, $replace, $remove);
    }

    public function testTheDefaultOfARoute(): void
    {
        $override = ProtectionOverride::fromArray(['add' => ['signed-cookies' => ['a']], 'replace' => ['X-Frame-Options' => 'DENY'], 'remove' => ['Referrer-Policy']]);

        self::assertSame(['b', 'a'], $override->apply(Protection::SignedCookies, ['b']));
        self::assertSame('DENY', $override->apply(Protection::FrameOptions, null));
        self::assertNull($override->apply(Protection::ReferrerPolicy, 'origin'));
        self::assertEquals(new ProtectionOverride(), ProtectionOverride::fromArray([]));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badDefaults(): iterable
    {
        $shape = 'The "add" and "replace" of a "_browser_security" default are maps, and its "remove" a list of names.';

        yield 'not a map' => ['DENY', 'The "_browser_security" default of a route is a map of add, replace and remove.'];
        yield 'an unknown key' => [['policy' => 'x'], 'The "_browser_security" default of a route does not take "policy".'];
        yield 'add not a map' => [['add' => 'x'], $shape];
        yield 'replace not a map' => [['replace' => 'x'], $shape];
        yield 'remove not a list' => [['remove' => 'x'], $shape];
        yield 'remove, a map' => [['remove' => ['a' => 'b']], $shape];
        yield 'remove, not strings' => [['remove' => [3]], $shape];
    }

    #[DataProvider('badDefaults')]
    public function testABadDefaultIsRefused(mixed $value, string $message): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains($message);

        ProtectionOverride::fromArray($value);
    }

    #[DoesNotPerformAssertions]
    public function testWhatActsLaterPassesTheRoutingCheck(): void
    {
        new ProtectionOverride(add: ['external-redirects' => ['a.example.com']], remove: ['X-Frame-Options'])->assertAfterRouting();
    }

    public function testWhatActsBeforeTheControllerIsLeftToARoute(): void
    {
        $this->expectException(InvalidPolicy::class);
        $this->expectExceptionMessageIsOrContains('"https-redirect" acts before the controller is known: only the "_browser_security" default of a route can change it.');

        new ProtectionOverride(remove: ['X-Frame-Options', 'https-redirect'])->assertAfterRouting();
    }

    public function testTheAttribute(): void
    {
        $attribute = new BrowserSecurity(add: ['Clear-Site-Data' => ['cache']], replace: ['X-Frame-Options' => 'DENY'], remove: ['Referrer-Policy']);

        self::assertEquals(new ProtectionOverride(['Clear-Site-Data' => ['cache']], ['X-Frame-Options' => 'DENY'], ['Referrer-Policy']), $attribute->toOverride());
    }
}
