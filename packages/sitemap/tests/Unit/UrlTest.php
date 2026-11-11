<?php

declare(strict_types=1);

namespace Phil\Sitemap\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Sitemap\Alternate;
use Phil\Sitemap\ChangeFrequency;
use Phil\Sitemap\Image;
use Phil\Sitemap\InvalidSitemap;
use Phil\Sitemap\Location;
use Phil\Sitemap\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Url::class)]
#[CoversClass(Alternate::class)]
#[CoversClass(Image::class)]
#[CoversClass(Location::class)]
#[CoversClass(InvalidSitemap::class)]
#[CoversClass(ChangeFrequency::class)]
final class UrlTest extends TestCase
{
    public function testAPageSaysWhatTheProtocolLetsIt(): void
    {
        $lastmod = new DateTimeImmutable('2026-11-04 12:00 UTC');
        $url = new Url('https://example.com/fr/a', $lastmod, ChangeFrequency::Weekly, 0.8, [new Alternate('fr_CA', 'https://example.com/fr/a')], [new Image('https://example.com/a.png')]);

        self::assertSame(['https://example.com/fr/a', $lastmod, ChangeFrequency::Weekly, 0.8], [$url->loc, $url->lastmod, $url->changefreq, $url->priority]);
        self::assertSame(['fr-CA', 'https://example.com/fr/a'], [$url->alternates[0]->hreflang, $url->alternates[0]->href]);
        self::assertSame('https://example.com/a.png', $url->images[0]->loc);

        $bare = new Url('HTTP://example.com/'.str_repeat('a', Location::MAX_LENGTH - 19));
        self::assertSame([null, null, null, [], []], [$bare->lastmod, $bare->changefreq, $bare->priority, $bare->alternates, $bare->images]);
        self::assertSame([0.0, 1.0], [new Url('https://example.com', priority: 0.0)->priority, new Url('https://example.com', priority: 1.0)->priority]);
        self::assertCount(1000, new Url('https://example.com', images: array_fill(0, 1000, new Image('https://example.com/a.png')))->images);
        self::assertSame(['x-default', 'zh-Hant-TW', 'ast'], [new Alternate('x-default', 'https://example.com')->hreflang, new Alternate('zh_Hant_TW', 'https://example.com')->hreflang, new Alternate('ast', 'https://example.com')->hreflang]);
        self::assertSame(['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'], array_map(static fn (ChangeFrequency $frequency): string => $frequency->value, ChangeFrequency::cases()));
        self::assertSame([2048, 1000], [Location::MAX_LENGTH, Url::MAX_IMAGES]);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function badPages(): iterable
    {
        $page = 'A page is an absolute HTTP or HTTPS URL of 2048 characters at most; ';

        yield 'relative' => [static fn (): Url => new Url('/fr/a'), $page.'"/fr/a" is not.'];
        yield 'another scheme' => [static fn (): Url => new Url('ftp://example.com/a'), $page.'"ftp://example.com/a" is not.'];
        yield 'too long' => [static fn (): Url => new Url('https://example.com/'.str_repeat('a', 2029)), $page.'"https://example.com/'.str_repeat('a', 2029).'" is not.'];
        yield 'a low priority' => [static fn (): Url => new Url('https://example.com', priority: -0.1), 'A priority is 0.0 to 1.0, in steps of 0.1; -0.1 is not.'];
        yield 'a high priority' => [static fn (): Url => new Url('https://example.com', priority: 1.1), 'A priority is 0.0 to 1.0, in steps of 0.1; 1.1 is not.'];
        yield 'a fine priority' => [static fn (): Url => new Url('https://example.com', priority: 0.85), 'A priority is 0.0 to 1.0, in steps of 0.1; 0.85 is not.'];
        yield 'too many images' => [static fn (): Url => new Url('https://example.com', images: array_fill(0, 1001, new Image('https://example.com/a.png'))), 'A page lists 1000 images at most; 1001 is too many.'];
        yield 'a bad image' => [static fn (): Image => new Image('a.png'), 'An image is an absolute HTTP or HTTPS URL of 2048 characters at most; "a.png" is not.'];
        yield 'a bad alternate' => [static fn (): Alternate => new Alternate('fr', 'fr/a'), 'An alternate is an absolute HTTP or HTTPS URL of 2048 characters at most; "fr/a" is not.'];
        yield 'a bad hreflang' => [static fn (): Alternate => new Alternate('French', 'https://example.com'), 'A hreflang is a language, maybe followed by a script or a region — "fr", "fr-CA" —, or "x-default"; "French" is not.'];
        yield 'a hreflang and more' => [static fn (): Alternate => new Alternate("fr\n", 'https://example.com'), "\"fr\n\" is not."];
        yield 'a hreflang after more' => [static fn (): Alternate => new Alternate('1fr', 'https://example.com'), '"1fr" is not.'];
        yield 'a hreflang too short' => [static fn (): Alternate => new Alternate('f', 'https://example.com'), '"f" is not.'];
        yield 'a region too long' => [static fn (): Alternate => new Alternate('fr-ABCDE', 'https://example.com'), '"fr-ABCDE" is not.'];
        // FILTER_VALIDATE_URL took a user name for a host: this page is on evil.example, not on example.com.
        yield 'credentials before the host' => [static fn (): Url => new Url('https://example.com@evil.example/fr/a'), $page.'"https://example.com@evil.example/fr/a" is not.'];
        yield 'a user and a password' => [static fn (): Image => new Image('https://user:secret@example.com/a.png'), '"https://user:secret@example.com/a.png" is not.'];
        yield 'an IP address' => [static fn (): Url => new Url('https://127.0.0.1/admin'), '"https://127.0.0.1/admin" is not.'];
        yield 'an IP address in decimal' => [static fn (): Alternate => new Alternate('fr', 'https://2130706433/fr/a'), '"https://2130706433/fr/a" is not.'];
        yield 'port 0' => [static fn (): Url => new Url('https://example.com:0/a'), '"https://example.com:0/a" is not.'];
        yield 'a host of one name' => [static fn (): Url => new Url('http://intranet/a'), '"http://intranet/a" is not.'];
        yield 'a sitemap file with credentials' => [static fn (): string => Location::check('https://example.com@evil.example/sitemap.xml', 'A sitemap file'), 'A sitemap file is an absolute HTTP or HTTPS URL'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalisedAddresses(): iterable
    {
        yield 'scheme and host in lower case' => ['HTTPS://Example.COM/Fr/A', 'https://example.com/Fr/A'];
        yield 'the root path' => ['https://example.com', 'https://example.com/'];
        yield 'the default port' => ['https://example.com:443/a', 'https://example.com/a'];
        yield 'dot segments' => ['https://example.com/a/../b/./c', 'https://example.com/b/c'];
        yield 'escaped dot segments' => ['https://example.com/a/%2e%2E/b', 'https://example.com/b'];
        yield 'a backslash' => ['https://example.com/a\\b', 'https://example.com/a/b'];
        yield 'an international host' => ['https://bücher.example/a', 'https://xn--bcher-kva.example/a'];
        yield 'a final dot' => ['https://example.com./a', 'https://example.com/a'];
    }

    #[DataProvider('normalisedAddresses')]
    public function testAnAddressIsWrittenInItsCanonicalForm(string $address, string $canonical): void
    {
        self::assertSame($canonical, new Url($address)->loc);
        self::assertSame($canonical, new Image($address)->loc);
        self::assertSame($canonical, new Alternate('fr', $address)->href);
        self::assertSame($canonical, Location::check($address, 'A page'));
    }

    #[DataProvider('badPages')]
    public function testABadPageIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidSitemap::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }
}
