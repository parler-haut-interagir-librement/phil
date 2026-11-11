<?php

declare(strict_types=1);

namespace Phil\Sitemap\Tests\Unit;

use DateTimeImmutable;
use Phil\Sitemap\Alternate;
use Phil\Sitemap\ChangeFrequency;
use Phil\Sitemap\Image;
use Phil\Sitemap\InvalidSitemap;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use Phil\Sitemap\XmlWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sitemap::class)]
#[CoversClass(XmlWriter::class)]
final class SitemapTest extends TestCase
{
    public function testPagesAreKeptBySection(): void
    {
        $a = new Url('https://example.com/a');
        $b = new Url('https://example.com/b');
        $c = new Url('https://example.com/c');
        $sitemap = new Sitemap();
        $sitemap->add($a, 'news');
        $sitemap->add($b, 'pages');
        $sitemap->add($c, 'news');

        self::assertSame(['news' => [$a, $c], 'pages' => [$b]], $sitemap->sections());
        self::assertSame(['news' => [$a, $c], 'pages' => [$b]], $sitemap->files());
        self::assertSame(['news_0' => [$a], 'news_1' => [$c], 'pages' => [$b]], $sitemap->files(1));
        self::assertNull($sitemap->only);

        $news = new Sitemap('news');
        $news->add($a, 'news');
        $news->add($b, 'pages');
        self::assertSame(['news' => [$a]], $news->sections());
        self::assertSame(['news'], [$news->only]);
        self::assertSame([50000, 'a', str_repeat('a', 64), '0-b_c'], [Sitemap::MAX_PER_FILE, Sitemap::checkSection('a'), Sitemap::checkSection(str_repeat('a', 64)), Sitemap::checkSection('0-b_c')]);
        self::assertSame(['news' => [$a]], $news->files(50000));
        self::assertSame([], new Sitemap()->files());
        self::assertSame(['top10', 'news-1_a', 'b-2', 'news', 'news'], [Sitemap::checkSection('top10'), Sitemap::checkSection('news-1_a'), Sitemap::checkSection('b-2'), Sitemap::sectionOfFile('news'), Sitemap::sectionOfFile('news_12')]);
    }

    /**
     * A part's name is never a section's, so no section can take the place of another's part.
     */
    public function testAPartIsNamedAfterItsSectionOnly(): void
    {
        $sitemap = new Sitemap();
        $sitemap->add(new Url('https://example.com/a'), 'news');
        $sitemap->add(new Url('https://example.com/b'), 'news');

        self::assertSame(['news_0', 'news_1'], array_keys($sitemap->files(1)));

        foreach (array_keys($sitemap->files(1)) as $name) {
            self::assertSame('news', Sitemap::sectionOfFile($name));
        }

        self::assertSame('news_1x', Sitemap::sectionOfFile('news_1x'), 'a name that only starts like a part is its own section');

        foreach (['news_1_2', '_1', 'News_1', "news_1\n", "\nnews_1"] as $name) {
            try {
                Sitemap::sectionOfFile($name);
                self::fail($name.' was taken.');
            } catch (InvalidSitemap) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * A key that ends in an underscore and digits names a section all the same, with a hyphen for that underscore.
     */
    public function testAKeyEndingInDigitsNamesASection(): void
    {
        self::assertSame(['news-2026', 'a_b-1', 'news', 'news-2026', 'top10'], [Sitemap::sectionNamed('news_2026'), Sitemap::sectionNamed('a_b_1'), Sitemap::sectionNamed('news'), Sitemap::sectionNamed('news-2026'), Sitemap::sectionNamed('top10')]);

        $this->expectException(InvalidSitemap::class);
        Sitemap::sectionNamed('News_1');
    }

    public function testBadSectionsAndFilesAreRefused(): void
    {
        foreach ([
            static fn (): Sitemap => new Sitemap('News'),
            static fn () => new Sitemap()->add(new Url('https://example.com'), '-a'),
            static fn (): string => Sitemap::checkSection(str_repeat('a', 65)),
            static fn (): string => Sitemap::checkSection("a\n"),
            static fn (): string => Sitemap::checkSection(''),
        ] as $index => $make) {
            try {
                $make();
                self::fail('Section '.$index.' was taken.');
            } catch (InvalidSitemap $invalidSitemap) {
                self::assertStringStartsWith('A section is 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a letter or a digit; "', $invalidSitemap->getMessage());
            }
        }

        foreach (['news_1', 'top_10', 'a_0'] as $section) {
            try {
                Sitemap::checkSection($section);
                self::fail($section.' was taken.');
            } catch (InvalidSitemap $invalidSitemap) {
                self::assertSame(\sprintf('A section does not end in an underscore and digits, which name the numbered parts of a section; "%s" does.', $section), $invalidSitemap->getMessage());
            }
        }

        foreach ([0, 50001] as $perFile) {
            try {
                new Sitemap()->files($perFile);
                self::fail($perFile.' pages per file were taken.');
            } catch (InvalidSitemap $invalidSitemap) {
                self::assertSame(\sprintf('A file holds 1 to 50000 pages; %d is not.', $perFile), $invalidSitemap->getMessage());
            }
        }
    }

    public function testAQuoteThatStaysInAPathIsEscaped(): void
    {
        // The path keeps an apostrophe; the XML writes it as an entity, in an element as in an attribute.
        $xml = XmlWriter::urlset([new Url("https://example.com/l'a", alternates: [new Alternate('fr', "https://example.com/l'a")])]);

        self::assertStringContainsString('<loc>https://example.com/l&apos;a</loc>', $xml);
        self::assertStringContainsString('href="https://example.com/l&apos;a"', $xml);
    }

    public function testTheXmlOfTheProtocol(): void
    {
        $urls = [
            new Url('https://example.com/fr/a?x=1&y=2', new DateTimeImmutable('2026-11-04 12:00 UTC'), ChangeFrequency::Weekly, 1.0, [new Alternate('fr', 'https://example.com/fr/a'), new Alternate('en_CA', 'https://example.com/en/a?"')], [new Image('https://example.com/a.png')]),
            new Url('https://example.com/b', priority: 0.0),
        ];

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
              <url>
                <loc>https://example.com/fr/a?x=1&amp;y=2</loc>
                <lastmod>2026-11-04T12:00:00+00:00</lastmod>
                <changefreq>weekly</changefreq>
                <priority>1.0</priority>
                <xhtml:link rel="alternate" hreflang="fr" href="https://example.com/fr/a"/>
                <xhtml:link rel="alternate" hreflang="en-CA" href="https://example.com/en/a?%22"/>
                <image:image><image:loc>https://example.com/a.png</image:loc></image:image>
              </url>
              <url>
                <loc>https://example.com/b</loc>
                <priority>0.0</priority>
              </url>
            </urlset>

            XML, XmlWriter::urlset($urls));

        self::assertSame(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
              <sitemap>
                <loc>https://example.com/sitemap.news.xml</loc>
                <lastmod>2026-11-05T08:00:00-05:00</lastmod>
              </sitemap>
              <sitemap>
                <loc>https://example.com/sitemap.pages.xml?a&amp;b</loc>
              </sitemap>
            </sitemapindex>

            XML, XmlWriter::index([
            'https://example.com/sitemap.news.xml' => [
                new Url('https://example.com/a', new DateTimeImmutable('2026-11-04 12:00 UTC')),
                new Url('https://example.com/b', new DateTimeImmutable('2026-11-05 08:00 America/Toronto')),
                new Url('https://example.com/c'),
            ],
            'https://example.com/sitemap.pages.xml?a&b' => [new Url('https://example.com/d')],
        ]));
        self::assertStringNotContainsString('<lastmod>', XmlWriter::index(['https://example.com/sitemap.empty.xml' => []]));
    }

    public function testAnIndexPointsAtAddresses(): void
    {
        $this->expectException(InvalidSitemap::class);
        $this->expectExceptionMessageIsOrContains('A sitemap file is an absolute HTTP or HTTPS URL of 2048 characters at most; "sitemap.news.xml" is not.');

        XmlWriter::index(['sitemap.news.xml' => []]);
    }
}
