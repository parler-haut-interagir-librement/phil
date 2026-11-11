<?php

declare(strict_types=1);

namespace Phil\Sitemap;

use DateTimeImmutable;

/**
 * The XML of a sitemap: a `urlset` of pages, with their alternates and images, and the `sitemapindex` of the files.
 */
final class XmlWriter
{
    private const string HEADER = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

    /**
     * @param list<Url> $urls
     */
    public static function urlset(array $urls): string
    {
        $xml = self::HEADER.'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'."\n".'    <loc>'.self::text($url->loc).'</loc>'."\n";

            if (null !== $url->lastmod) {
                $xml .= '    <lastmod>'.$url->lastmod->format(\DATE_ATOM).'</lastmod>'."\n";
            }

            if (null !== $url->changefreq) {
                $xml .= '    <changefreq>'.$url->changefreq->value.'</changefreq>'."\n";
            }

            if (null !== $url->priority) {
                $xml .= '    <priority>'.\sprintf('%.1F', $url->priority).'</priority>'."\n";
            }

            foreach ($url->alternates as $alternate) {
                $xml .= \sprintf('    <xhtml:link rel="alternate" hreflang="%s" href="%s"/>', $alternate->hreflang, self::text($alternate->href))."\n";
            }

            foreach ($url->images as $image) {
                $xml .= '    <image:image><image:loc>'.self::text($image->loc).'</image:loc></image:image>'."\n";
            }

            $xml .= '  </url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }

    /**
     * @param array<string, list<Url>> $files the pages of each file, by the address the file is served at
     */
    public static function index(array $files): string
    {
        $xml = self::HEADER.'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($files as $loc => $urls) {
            $xml .= '  <sitemap>'."\n".'    <loc>'.self::text(Location::check($loc, 'A sitemap file')).'</loc>'."\n";
            $lastmod = self::lastModified($urls);

            if ($lastmod instanceof DateTimeImmutable) {
                $xml .= '    <lastmod>'.$lastmod->format(\DATE_ATOM).'</lastmod>'."\n";
            }

            $xml .= '  </sitemap>'."\n";
        }

        return $xml.'</sitemapindex>'."\n";
    }

    /**
     * @param list<Url> $urls
     */
    private static function lastModified(array $urls): ?DateTimeImmutable
    {
        return max([null, ...array_map(static fn (Url $url): ?DateTimeImmutable => $url->lastmod, $urls)]);
    }

    private static function text(string $text): string
    {
        return htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}
