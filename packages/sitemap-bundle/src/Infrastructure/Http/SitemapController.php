<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure\Http;

use DateTimeImmutable;
use Phil\Sitemap\InvalidSitemap;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use Phil\Sitemap\XmlWriter;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The sitemap served as it is asked for: `/sitemap.xml`, the index of the files, and `/sitemap.{name}.xml`, one file —
 * a section, or a numbered part of one, built from that section alone.
 *
 * Each answer may be kept `$maxAge` seconds by browsers and shared caches, carries the latest `lastmod` of its pages as
 * `Last-Modified` and a hash of its XML as `ETag`, and is a `304` to a request that already holds it: a cache in front
 * of the site then answers most crawls without the sitemap being built. A site with many pages dumps the files instead
 * (`phil:sitemap:dump`) and serves them as static files.
 */
final readonly class SitemapController
{
    public const string INDEX_ROUTE = 'phil_sitemap_index';

    public const string FILE_ROUTE = 'phil_sitemap_file';

    public function __construct(
        private Sitemaps $sitemaps,
        private UrlGeneratorInterface $urls,
        private int $perFile,
        private int $maxAge,
        private bool $allowLocalhost = false,
    ) {
    }

    public function index(Request $request): Response
    {
        $files = [];

        foreach ($this->sitemaps->build()->files($this->perFile) as $name => $urls) {
            $files[$this->urls->generate(self::FILE_ROUTE, ['name' => $name], UrlGeneratorInterface::ABSOLUTE_URL)] = $urls;
        }

        return $this->xml($request, XmlWriter::index($files, $this->allowLocalhost), array_merge(...array_values($files)));
    }

    public function file(Request $request, string $name): Response
    {
        try {
            $section = Sitemap::sectionOfFile($name);
        } catch (InvalidSitemap $invalidSitemap) {
            throw new NotFoundHttpException(\sprintf('The sitemap has no file "%s".', $name), $invalidSitemap);
        }

        $urls = $this->sitemaps->build($section)->files($this->perFile)[$name] ?? throw new NotFoundHttpException(\sprintf('The sitemap has no file "%s".', $name));

        return $this->xml($request, XmlWriter::urlset($urls), $urls);
    }

    /**
     * @param list<Url> $urls the pages the XML lists, whose latest `lastmod` is its own
     */
    private function xml(Request $request, string $xml, array $urls): Response
    {
        $response = new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/xml; charset=UTF-8']);
        $response->setEtag(hash('xxh128', $xml));

        if ($this->maxAge > 0) {
            $response->setPublic();
            $response->setMaxAge($this->maxAge);
        }

        $lastModified = [];

        foreach ($urls as $url) {
            if ($url->lastmod instanceof DateTimeImmutable) {
                $lastModified[] = $url->lastmod->getTimestamp();
            }
        }

        $response->setLastModified([] === $lastModified ? null : new DateTimeImmutable('@'.max($lastModified)));
        $response->isNotModified($request);

        return $response;
    }
}
