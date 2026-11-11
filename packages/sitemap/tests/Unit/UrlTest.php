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
    }

    #[DataProvider('badPages')]
    public function testABadPageIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidSitemap::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }
}
