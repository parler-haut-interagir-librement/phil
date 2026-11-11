<?php

declare(strict_types=1);

namespace Phil\Sitemap\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\Sitemap\Alternate;
use Phil\Sitemap\ChangeFrequency;
use Phil\Sitemap\Image;
use Phil\Sitemap\Url;
use Phil\Sitemap\XmlWriter;

/**
 * Each file of a sitemap is written as one string, up to 50,000 pages, each with its alternates and its images: the
 * file is rebuilt on every request for it, or on every dump.
 *
 * @BeforeMethods("setUp")
 *
 * @Iterations(5)
 */
final class XmlWriterBench
{
    /** @var list<Url> */
    private array $urls = [];

    /**
     * @param array{pages: int, rich: bool} $params
     */
    public function setUp(array $params): void
    {
        $lastmod = new DateTimeImmutable('2026-11-05 08:00');

        for ($index = 0; $index < $params['pages']; ++$index) {
            $loc = \sprintf('https://example.com/fr/articles/article-%d?ref=a&b', $index);

            $this->urls[] = $params['rich']
                ? new Url(
                    $loc,
                    $lastmod,
                    ChangeFrequency::Weekly,
                    0.5,
                    [
                        new Alternate('fr', $loc),
                        new Alternate('en', \sprintf('https://example.com/en/articles/article-%d', $index)),
                        new Alternate('fr_CA', \sprintf('https://example.com/fr-ca/articles/article-%d', $index)),
                    ],
                    [
                        new Image(\sprintf('https://example.com/media/%d-cover.jpg', $index)),
                        new Image(\sprintf('https://example.com/media/%d-inline.jpg', $index)),
                    ],
                )
                : new Url($loc, $lastmod);
        }
    }

    /**
     * @ParamProviders("files")
     *
     * @Revs(3)
     */
    public function benchUrlset(): string
    {
        return XmlWriter::urlset($this->urls);
    }

    /**
     * @return Generator<string, array{pages: int, rich: bool}>
     */
    public function files(): Generator
    {
        foreach ([1_000, 10_000, 50_000] as $pages) {
            yield \sprintf('%d pages', $pages) => ['pages' => $pages, 'rich' => false];
            yield \sprintf('%d pages, 3 alternates and 2 images each', $pages) => ['pages' => $pages, 'rich' => true];
        }
    }
}
